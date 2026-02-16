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

        $login = $request->input('login');
        $password = $request->input('password');

        $query = DB::table('users')->where('email', $login);
        if (Schema::hasColumn('users', 'username')) {
            $query->orWhere('username', $login);
        }
        $user = $query->first();

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
