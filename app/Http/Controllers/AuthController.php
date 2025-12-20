<?php

namespace App\Http\Controllers;

use App\Models\User;
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
        $ldapServers = \App\Models\LdapServer::where('active', true)->get();
        return view('auth.login', compact('ldapServers'));
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
            'login_source' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        $loginSource = $request->input('login_source');
        $user = null;

        if ($loginSource === 'local') {
            // Local authentication - verify credentials manually first
            $user = User::where('email', $request->email)->first();
            
            if (!$user || !Hash::check($request->password, $user->password)) {
                return back()->withErrors([
                    'email' => 'The provided credentials do not match our records.',
                ])->onlyInput('email');
            }
        } else {
            // LDAP Login
            $server = \App\Models\LdapServer::find($loginSource);
            if ($server && $server->active) {
                $ldapService = new \App\Services\LdapService();
                
                if ($ldapService->connect($server->host, $server->port)) {
                    // 1. Bind with service account (if configured) or anonymous to search for user DN
                    if ($server->bind_dn) {
                        $bind = $ldapService->bind($server->bind_dn, $server->bind_password);
                    } else {
                        $bind = $ldapService->bind(); // Anonymous
                    }

                    if ($bind) {
                        // 2. Search for user DN
                        $username = $request->email;
                        $filter = sprintf($server->user_filter, $username);
                        $results = $ldapService->search($server->base_dn, $filter);

                        // If no results and input looks like email, try extracting username
                        if ((!$results || $results['count'] === 0) && filter_var($username, FILTER_VALIDATE_EMAIL)) {
                             $extractedUser = explode('@', $username)[0];
                             $filter = sprintf($server->user_filter, $extractedUser);
                             $results = $ldapService->search($server->base_dn, $filter);
                        }

                        if ($results && $results['count'] > 0) {
                            $entry = $results[0];
                            $userDn = $entry['dn'];

                            // 3. Bind with found User DN and Password
                            if ($ldapService->bind($userDn, $request->password)) {
                                // Auth Successful - Sync/Create Local User
                                $cn = $entry['cn'][0] ?? $username;
                                $mail = $entry['mail'][0] ?? $username . '@example.com';

                                $user = User::updateOrCreate(
                                    ['email' => $mail],
                                    [
                                        'name' => $cn,
                                        'password' => Hash::make(\Illuminate\Support\Str::random(32)),
                                        'email_verified_at' => now(),
                                    ]
                                );
                            }
                        }
                    }
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        return redirect()->route('dashboard')->with('success', 'Account created successfully!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
