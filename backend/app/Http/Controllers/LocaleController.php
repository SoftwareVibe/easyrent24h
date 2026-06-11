<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public const SUPPORTED = ['en', 'it', 'es'];

    /** Cambia la lingua dei pannelli e la persiste sull'utente. */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, self::SUPPORTED, true), 404);

        if (! $request->user()) {
            return redirect('/admin/login');
        }

        $request->session()->put('locale', $locale);
        $request->user()->update(['locale' => $locale]);

        return back(fallback: '/admin');
    }
}
