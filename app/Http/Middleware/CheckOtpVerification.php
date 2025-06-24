<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckOtpVerification
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the user is authenticated
        if (Auth::check()) {
            // Check if the user's OTP column is not verified (e.g., `otp != 1`)
            if (Auth::user()->otp != 1) {
                return response()->json(['message' => 'You should verify your email first.'], 403);
                // Or redirect (if using web routes):
                // return redirect()->route('verification.notice')->with('error', 'You should verify your email first.');
            }
        }

        return $next($request);
    }
}
