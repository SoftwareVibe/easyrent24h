<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Ex Contact Form 7: salva il messaggio e notifica via email. */
class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:5000'],
            'locale' => ['nullable', 'in:en,it,es'],
        ]);

        $message = ContactMessage::create($validated + ['locale' => $validated['locale'] ?? 'en']);

        if ($admin = config('mail.from.address')) {
            try {
                Mail::raw(
                    "Nuovo messaggio dal sito\n\nNome: {$message->name}\nEmail: {$message->email}\nTelefono: {$message->phone}\n\n{$message->message}",
                    fn ($mail) => $mail->to($admin)->subject('Contatto sito - '.$message->name),
                );
            } catch (\Throwable $e) {
                Log::warning('Invio notifica contatto fallito: '.$e->getMessage());
            }
        }

        return response()->json(['received' => true], 201);
    }
}
