<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify your identity · Medica</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-page">
<section class="login-art">
    <a class="brand" href="{{ route('login') }}"><span class="brand-mark">✚</span>medica<span class="brand-dot">.</span></a>
    <div class="login-art-content">
        <div class="eyebrow">ONE MORE STEP FOR SAFER CARE</div>
        <h1>Your workspace.<br>Protected.</h1>
        <p>An extra layer of verification helps keep patient information in the right hands.</p>
    </div>
    <footer>MEDICA · CLINIC MANAGEMENT PLATFORM</footer>
</section>
<main class="login-content">
    <div class="login-form">
        <div class="eyebrow">TWO-FACTOR AUTHENTICATION</div>
        <h2>{{ $enrolling ? 'Set up your authenticator.' : 'Verify your identity.' }}</h2>
        <p>{{ $enrolling ? 'Scan the QR code with your authenticator app, then enter its six-digit code below.' : 'Enter the current six-digit code from your authenticator app.' }}</p>
        @if($enrolling)
            <div class="authenticator-setup">
                <strong>Account: {{ auth()->user()->email }}</strong>
                <img class="authenticator-qr" src="{{ $qrCodeDataUri }}" width="256" height="256" alt="QR code to add your Medica account to an authenticator app">
                <p class="authenticator-hint">In your authenticator, choose “Add account” and scan this code.</p>
                <details class="authenticator-manual">
                    <summary>Can’t scan? Enter the setup key manually</summary>
                    <code class="setup-key">{{ $secret }}</code>
                    <p>Type: time-based · SHA-1 · 6 digits · 30 seconds</p>
                </details>
            </div>
        @endif
        @if($errors->any())
            <div class="notice error" role="alert">{{ $errors->first() }}</div>
        @endif
        <form method="post" action="{{ route('mfa') }}">
            @csrf
            <div class="field">
                <label for="code">Authenticator code</label>
                <input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required autofocus>
            </div>
            <button class="button primary full">Verify & continue <x-icon name="arrow" size="17"/></button>
        </form>
        <div class="login-footnote">If you cannot access your authenticator, contact your clinic administrator.</div>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="button secondary full">Sign out</button>
        </form>
    </div>
</main>
</body>
</html>
