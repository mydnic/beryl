<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class HasUserMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->guest() && User::count() === 0) {
            return redirect()->route('register');
        }
        if (auth()->guest() && User::count() > 0) {
            return redirect()->route('login');
        }
        return $next($request);
    }
}
