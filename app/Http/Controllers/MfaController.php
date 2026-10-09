<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuthenticatorQrCode;
use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class MfaController extends Controller
{
    public function show(Request $request, Totp $totp, AuthenticatorQrCode $qrCode)
    {
        if ($request->session()->get('mfa_user_id') === $request->user()->id) {
            return redirect()->route('dashboard');
        }
        $enrolling = ! $request->user()->mfa_confirmed_at;
        if ($enrolling && ! $request->session()->has('mfa_setup_secret')) {
            $request->session()->put('mfa_setup_secret', $totp->generateSecret());
        }
        $secret = $enrolling ? $request->session()->get('mfa_setup_secret') : null;
        $qrCodeDataUri = $enrolling ? $qrCode->dataUri($request->user()->email, $secret) : null;

        return view('auth.mfa', compact('enrolling', 'secret', 'qrCodeDataUri'));
    }

    public function verify(Request $request, Totp $totp)
    {
        $data = $request->validate(['code' => 'required|digits:6']);
        $key = 'mfa:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Try again in a minute.']);
        }
        RateLimiter::hit($key, 60);
        DB::transaction(function () use ($request, $totp, $data) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            $secret = $user->mfa_confirmed_at ? $user->mfa_secret : $request->session()->get('mfa_setup_secret');
            $step = $secret ? $totp->verify($secret, $data['code'], $user->mfa_last_step) : null;
            if ($step === null) {
                throw ValidationException::withMessages(['code' => 'Invalid or already-used code. Use the current code from your authenticator.']);
            }
            $user->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => $user->mfa_confirmed_at ?? now(), 'mfa_last_step' => $step])->save();
            AuditEvent::create(['clinic_id' => $user->clinic_id, 'user_id' => $user->id, 'action' => 'session.mfa_verified']);
        });
        RateLimiter::clear($key);
        $request->session()->forget('mfa_setup_secret');
        $request->session()->regenerate();
        $request->session()->put('mfa_user_id', $request->user()->id);

        return redirect()->route('dashboard');
    }
}
