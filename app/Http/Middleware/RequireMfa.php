<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireMfa
{
    public function handle(Request $request, Closure $next)
    {
        if (config('medica.require_mfa') && $request->session()->get('mfa_user_id') !== $request->user()->id) {
            return redirect()->route('mfa');
        }

        return $next($request);
    }
}
