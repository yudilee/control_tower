<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    /**
     * Show 2FA settings page
     */
    public function index()
    {
        $user = auth()->user();
        $sessions = UserSession::where('user_id', $user->id)
            ->orderBy('last_active_at', 'desc')
            ->get();
            
        return view('auth.two-factor', compact('user', 'sessions'));
    }

    /**
     * Enable 2FA - Generate secret and show QR code
     */
    public function enable(Request $request)
    {
        $user = auth()->user();
        
        if ($user->two_factor_enabled) {
            return back()->with('error', '2FA is already enabled.');
        }
        
        // Generate a simple TOTP secret (base32 encoded)
        $secret = $this->generateSecret();
        
        // Store temporarily (not confirmed yet)
        $user->update([
            'two_factor_secret' => encrypt($secret),
        ]);
        
        // Generate otpauth URL for QR code
        $appName = config('app.name', 'Control Tower');
        $otpauthUrl = "otpauth://totp/{$appName}:{$user->email}?secret={$secret}&issuer={$appName}&algorithm=SHA1&digits=6&period=30";
        
        return view('auth.two-factor-setup', compact('secret', 'otpauthUrl', 'user'));
    }

    /**
     * Confirm 2FA setup with a code
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);
        
        $user = auth()->user();
        
        if (!$user->two_factor_secret) {
            return back()->with('error', 'Please start the 2FA setup first.');
        }
        
        $secret = decrypt($user->two_factor_secret);
        
        if (!$this->verifyCode($secret, $request->code)) {
            return back()->with('error', 'Invalid verification code. Please try again.');
        }
        
        // Generate recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();
        
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);
        
        return view('auth.two-factor-recovery', compact('recoveryCodes'));
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);
        
        $user = auth()->user();
        
        if (!Hash::check($request->password, $user->password)) {
            return back()->with('error', 'Incorrect password.');
        }
        
        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
        
        return back()->with('success', 'Two-factor authentication has been disabled.');
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);
        
        $user = auth()->user();
        
        if (!Hash::check($request->password, $user->password)) {
            return back()->with('error', 'Incorrect password.');
        }
        
        if (!$user->two_factor_enabled) {
            return back()->with('error', '2FA is not enabled.');
        }
        
        $recoveryCodes = $this->generateRecoveryCodes();
        
        $user->update([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);
        
        return view('auth.two-factor-recovery', compact('recoveryCodes'));
    }

    /**
     * Show 2FA challenge during login
     */
    public function challenge()
    {
        if (!session('2fa_user_id')) {
            return redirect()->route('login');
        }
        
        return view('auth.two-factor-challenge');
    }

    /**
     * Verify 2FA code during login
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);
        
        $userId = session('2fa_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }
        
        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }
        
        $code = $request->code;
        $secret = decrypt($user->two_factor_secret);
        
        // Check TOTP code
        if ($this->verifyCode($secret, $code)) {
            $this->completeLogin($user, $request);
            return redirect()->intended('/');
        }
        
        // Check recovery codes
        $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
        if (in_array($code, $recoveryCodes)) {
            // Remove used recovery code
            $recoveryCodes = array_diff($recoveryCodes, [$code]);
            $user->update([
                'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes))),
            ]);
            
            $this->completeLogin($user, $request);
            return redirect()->intended('/')->with('warning', 'You used a recovery code. Please regenerate your codes.');
        }
        
        return back()->with('error', 'Invalid verification code.');
    }

    /**
     * Terminate a specific session
     */
    public function terminateSession(UserSession $session)
    {
        if ($session->user_id !== auth()->id()) {
            abort(403);
        }
        
        $session->delete();
        
        return back()->with('success', 'Session terminated.');
    }

    /**
     * Terminate all other sessions
     */
    public function terminateOtherSessions(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);
        
        if (!Hash::check($request->password, auth()->user()->password)) {
            return back()->with('error', 'Incorrect password.');
        }
        
        UserSession::where('user_id', auth()->id())
            ->where('is_current', false)
            ->delete();
        
        return back()->with('success', 'All other sessions have been terminated.');
    }

    // Helper methods
    
    private function generateSecret(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(Str::random(4) . '-' . Str::random(4));
        }
        return $codes;
    }

    private function verifyCode(string $secret, string $code): bool
    {
        // Simple TOTP verification
        $timeSlice = floor(time() / 30);
        
        // Check current and adjacent time slices (allows for clock drift)
        for ($i = -1; $i <= 1; $i++) {
            $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $timeSlice + $i), $this->base32Decode($secret), true);
            $offset = ord(substr($hash, -1)) & 0x0F;
            $binary = (
                ((ord($hash[$offset]) & 0x7F) << 24) |
                ((ord($hash[$offset + 1]) & 0xFF) << 16) |
                ((ord($hash[$offset + 2]) & 0xFF) << 8) |
                (ord($hash[$offset + 3]) & 0xFF)
            );
            $otp = $binary % 1000000;
            
            if (str_pad($otp, 6, '0', STR_PAD_LEFT) === $code) {
                return true;
            }
        }
        
        return false;
    }

    private function base32Decode(string $input): string
    {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper($input);
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $value = strpos($map, $input[$i]);
            if ($value === false) continue;
            
            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;
            
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        
        return $output;
    }

    private function completeLogin($user, Request $request): void
    {
        auth()->login($user, session('2fa_remember', false));
        session()->forget(['2fa_user_id', '2fa_remember']);
        
        // Record session
        UserSession::recordLogin(
            $user->id,
            session()->getId(),
            $request->ip(),
            $request->userAgent()
        );
    }
}
