<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth-login-cover');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = trim($request->input('login'));
        $password = $request->input('password');

        // Determine whether user supplied an email or a username.
        // If the input contains an '@' and is a valid email, search by email.
        // Otherwise, if a `username` column exists, search by username first,
        // then fall back to email as a secondary check.
        $user = null;
        $hasUsername = Schema::hasColumn('users', 'username');

        if (strpos($login, '@') !== false && filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $user = DB::table('users')->where('email', $login)->first();
        } else {
            if ($hasUsername) {
                $user = DB::table('users')->where('username', $login)->first();
            }
            // If no user found by username, try email as a fallback (covers users
            // who may login with their email without including an '@' by mistake)
            if (! $user) {
                $user = DB::table('users')->where('email', $login)->first();
            }
        }

        if ($user && Hash::check($password, $user->password)) {
            $remember = $request->filled('remember');
            Auth::loginUsingId($user->id, $remember);
            $request->session()->regenerate();

            // If the authenticated user is an admin, redirect to the admin dashboard.
            $isAdmin = false;
            if (isset($user->email) && $user->email === 'admin@biotern.com') {
                $isAdmin = true;
            }
            if (isset($user->role) && $user->role === 'admin') {
                $isAdmin = true;
            }
            if ($isAdmin) {
                return redirect()->route('dashboard');
            }

            return redirect()->intended('/');
        }

        return back()->withErrors(['login' => 'The provided credentials do not match our records.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
