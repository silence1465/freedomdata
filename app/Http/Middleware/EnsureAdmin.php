<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return redirect('/')->with('error', 'Admin access only.');
        }
        return $next($request);
    }
}
