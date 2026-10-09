<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title', 'Overview') · Medica</title><link rel="stylesheet" href="{{ asset('css/app.css') }}"><script src="{{ asset('js/app.js') }}" defer></script></head>
<body>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">✚</span> medica<span class="brand-dot">.</span></a>
    <div class="workspace"><span class="workspace-icon">{{ mb_substr($clinicName, 0, 1) }}</span><div><strong>{{ $clinicName }}</strong><small>Clinic workspace</small></div><span class="workspace-chevron">⌄</span></div>
    <div class="nav-label">WORKSPACE</div>
    <nav aria-label="Main navigation">
        <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><x-icon name="grid"/> Overview</a>
        @if(auth()->user()->permits('patients.read'))<a class="nav-item {{ request()->routeIs('patients.*') ? 'active' : '' }}" href="{{ route('patients.index') }}"><x-icon name="patients"/> Patients</a>@endif
        @if(auth()->user()->permits('appointments.write'))<a class="nav-item {{ request()->routeIs('appointments*') ? 'active' : '' }}" href="{{ route('appointments') }}"><x-icon name="calendar"/> Appointments</a>@endif
        @if(auth()->user()->permits('billing.read'))<a class="nav-item {{ request()->routeIs('billing') ? 'active' : '' }}" href="{{ route('billing') }}"><x-icon name="wallet"/> Billing & payments</a>@endif
        @if(auth()->user()->permits('team.read') || auth()->user()->permits('audit.read'))<div class="nav-label administration">ADMINISTRATION</div>@endif
        @if(auth()->user()->permits('team.read'))<a class="nav-item {{ request()->routeIs('team') ? 'active' : '' }}" href="{{ route('team') }}"><x-icon name="patients"/> Team & permissions</a>@endif
        @if(auth()->user()->permits('audit.read'))<a class="nav-item {{ request()->routeIs('audit') ? 'active' : '' }}" href="{{ route('audit') }}"><x-icon name="shield"/> Audit trail</a>@endif
    </nav>
    <div class="sidebar-bottom"><div class="care-card"><span class="care-icon"><x-icon name="heart"/></span><strong>More care. Less admin.</strong><p>Your practice, working together.</p><span class="care-caption">MEDICA CLINIC PLATFORM</span></div><div class="sidebar-user"><span class="avatar teal">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ ucfirst(auth()->user()->role) }}</small></div><form method="post" action="{{ route('logout') }}">@csrf<button class="icon-button" aria-label="Sign out"><x-icon name="logout" size="18"/></button></form></div></div>
</aside>
<div class="app-shell">
    <header class="topbar"><div class="breadcrumb"><button class="icon-button mobile-menu" data-menu aria-label="Toggle navigation"><x-icon name="menu"/></button><span>Workspace</span><span class="slash">/</span><strong>@yield('title', 'Overview')</strong></div><div class="topbar-right"><span class="demo-pill"><span class="live-dot"></span>Demo workspace</span><span class="topbar-date">{{ now('America/New_York')->format('D, M j, Y') }}</span><span class="avatar small">{{ mb_substr(auth()->user()->name, 0, 1) }}</span></div></header>
    <main id="main-content">
        @if(session('success'))<div class="notice success" role="status"><x-icon name="check"/>{{ session('success') }}</div>@endif
        @if($errors->any())<div class="notice error" role="alert"><div><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
        @yield('content')
        <footer class="page-footer"><span>© {{ now()->year }} Medica · Built around better care</span><span><span class="live-dot"></span> Synthetic demonstration data</span></footer>
    </main>
</div>
</body></html>
