<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Un compte désactivé par l'ADM est déconnecté immédiatement, même s'il était déjà connecté. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->is_active || $user->trashed())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Votre compte a été désactivé.'], 401)
                : redirect()->route('login')->withErrors(['email' => 'Votre compte a été désactivé. Contactez l\'administrateur.']);
        }

        return $next($request);
    }
}
