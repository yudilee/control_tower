<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LdapService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Available roles for assignment
     */
    protected array $roles = [
        'user' => 'User (No Access)',
        'sa' => 'Service Advisor',
        'foreman' => 'Foreman',
        'sparepart' => 'Sparepart',
        'finance' => 'Finance',
        'control_tower' => 'Control Tower',
        'audit' => 'Audit',
        'manager' => 'Workshop Manager',
        'admin' => 'Administrator',
    ];

    /**
     * Display a listing of users with roles
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->orderBy('name')->paginate(20)->withQueryString();
        $roles = $this->roles;

        return view('admin.users.index', compact('users', 'roles'));
    }

    /**
     * Show form to create a new local user
     */
    public function create()
    {
        $roles = $this->roles;
        return view('admin.users.create', compact('roles'));
    }

    /**
     * Store a new local user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:' . implode(',', array_keys($this->roles)),
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'auth_source' => 'local',
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$user->name}' created with role '{$this->roles[$validated['role']]}'");
    }

    /**
     * Show form to edit user role
     */
    public function edit(User $user)
    {
        $roles = $this->roles;
        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * Update user role
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|in:' . implode(',', array_keys($this->roles)),
        ]);

        $user->update(['role' => $validated['role']]);

        return redirect()->route('admin.users.index')
            ->with('success', "Role updated for {$user->name} to {$this->roles[$validated['role']]}");
    }

    /**
     * Search LDAP users
     */
    public function searchLdap(Request $request)
    {
        $request->validate(['search' => 'required|min:2']);

        try {
            $ldapService = app(LdapService::class);
            $results = $ldapService->searchUsers($request->search);

            return response()->json([
                'success' => true,
                'users' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'LDAP search failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign role to LDAP user (creates user if doesn't exist)
     */
    public function assignRole(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'name' => 'required|string',
            'email' => 'required|email',
            'role' => 'required|in:' . implode(',', array_keys($this->roles)),
        ]);

        // Find or create user
        $user = User::updateOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'],
                'role' => $validated['role'],
                'password' => bcrypt(str()->random(32)), // Random password, user uses LDAP
                'auth_source' => 'ldap', // Mark as LDAP user
            ]
        );

        return redirect()->route('admin.users.index')
            ->with('success', "Role '{$this->roles[$validated['role']]}' assigned to {$user->name}");
    }

    /**
     * Delete an internal database user
     */
    public function destroy(User $user)
    {
        // Only allow deleting internal database users
        if ($user->auth_source !== 'local') {
            return redirect()->route('admin.users.index')
                ->with('error', 'Cannot delete LDAP users. Remove them from LDAP instead.');
        }

        // Cannot delete yourself
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$name}' deleted successfully.");
    }

    /**
     * Reset password for an internal user (admin only)
     */
    public function resetPassword(Request $request, User $user)
    {
        // Only allow resetting password for internal database users
        if ($user->auth_source && $user->auth_source !== 'local') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reset password for LDAP users.',
            ], 400);
        }

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => bcrypt($validated['password']),
        ]);

        // Log activity
        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->withProperties(['action' => 'admin_password_reset', 'target_user' => $user->email])
            ->log('Admin reset password for user');

        return response()->json([
            'success' => true,
            'message' => "Password reset successfully for {$user->name}",
        ]);
    }
}
