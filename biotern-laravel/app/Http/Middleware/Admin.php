<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class Admin
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('login.show');
        }

        $user = Auth::user();
        $isAdmin = false;
        if (isset($user->email) && $user->email === 'admin@biotern.com') {
            $isAdmin = true;
        }
        if (isset($user->role) && $user->role === 'admin') {
            $isAdmin = true;
        }

        if (! $isAdmin) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
