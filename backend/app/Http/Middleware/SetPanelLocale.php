<?php

namespace App\Http\Middleware;

use App\Http\Controllers\LocaleController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Applica la lingua scelta: profilo utente -> sessione -> default app. */
class SetPanelLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? config('app.locale');

        if (in_array($locale, LocaleController::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
