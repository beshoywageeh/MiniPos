<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Auth\LoginRequest;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function login(LoginRequest $request)
    {
        if (Auth::attempt($request->only('email', 'password'))) {
            return redirect()->intended('/dashboard')->with('message', 'Login successful.');
        }
        return back()->with('message', 'Invalid credentials.');
    }
    public function logout(Request $request)
    {
        Auth::logout();
        return redirect('/login')->with('message', 'Logged out successfully.');
    }
}
