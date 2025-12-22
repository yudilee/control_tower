<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LdapServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        $ldapServers = LdapServer::where('active', true)->get();
        return view('auth.login', compact('ldapServers'));
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ]);

        $user = null;

        // Step 1: Try local database first
        $localUser = User::where('email', $request->email)->first();
        
        if ($localUser && $localUser->auth_source === 'local' && Hash::check($request->password, $localUser->password)) {
            $user = $localUser;
        }

        // Step 2: If not authenticated locally, try all active LDAP servers
        if (!$user) {
            $ldapServers = LdapServer::where('active', true)->get();
            
            foreach ($ldapServers as $server) {
                $ldapResult = $this->tryLdapAuth($server, $request->email, $request->password);
                if ($ldapResult) {
                    $user = $ldapResult;
                    break; // Stop on first successful auth
                }
            }
        }

        // If user found and authenticated
        if ($user) {
            // Check if 2FA is enabled
            if ($user->two_factor_enabled) {
                // Store user ID in session for 2FA challenge
                $request->session()->put('2fa_user_id', $user->id);
                $request->session()->put('2fa_remember', $request->boolean('remember'));
                return redirect()->route('2fa.challenge');
            }
            
            // No 2FA - complete login
            $this->completeLogin($user, $request);
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Try to authenticate user via LDAP server
     */
    protected function tryLdapAuth(LdapServer $server, string $email, string $password): ?User
    {
        try {
            $ldapService = new \App\Services\LdapService();
            
            if (!$ldapService->connect($server->host, $server->port)) {
                return null;
            }

            // 1. Bind with service account (if configured) or anonymous to search for user DN
            if ($server->bind_dn) {
                $bind = $ldapService->bind($server->bind_dn, $server->bind_password);
            } else {
                $bind = $ldapService->bind(); // Anonymous
            }

            if (!$bind) {
                return null;
            }

            // 2. Search for user DN
            $username = $email;
            $filter = sprintf($server->user_filter, $username);
            $results = $ldapService->search($server->base_dn, $filter);

            // If no results and input looks like email, try extracting username
            if ((!$results || $results['count'] === 0) && filter_var($username, FILTER_VALIDATE_EMAIL)) {
                $extractedUser = explode('@', $username)[0];
                $filter = sprintf($server->user_filter, $extractedUser);
                $results = $ldapService->search($server->base_dn, $filter);
            }

            if (!$results || $results['count'] === 0) {
                return null;
            }

            $entry = $results[0];
            $userDn = $entry['dn'];

            // 3. Bind with found User DN and Password
            if (!$ldapService->bind($userDn, $password)) {
                return null;
            }

            // Auth Successful - Sync/Create Local User
            $cn = $entry['cn'][0] ?? $username;
            $mail = $entry['mail'][0] ?? $username . '@example.com';

            $user = User::updateOrCreate(
                ['email' => $mail],
                [
                    'name' => $cn,
                    'password' => Hash::make(\Illuminate\Support\Str::random(32)),
                    'email_verified_at' => now(),
                    'auth_source' => 'ldap:' . $server->id,
                ]
            );

            return $user;
        } catch (\Exception $e) {
            // Log error but continue trying other servers
            \Log::warning("LDAP auth failed for server {$server->name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Complete login and record session
     */
    protected function completeLogin($user, Request $request): void
    {
        Auth::login($user, $request->boolean('remember') ?? session('2fa_remember', false));
        $request->session()->regenerate();
        
        // Record session for session management
        \App\Models\UserSession::recordLogin(
            $user->id,
            session()->getId(),
            $request->ip(),
            $request->userAgent()
        );
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'auth_source' => 'local', // Internal database user
        ]);

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        // Mark the current session as logged out
        \App\Models\UserSession::where('session_id', session()->getId())
            ->update(['is_current' => false]);
        
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
