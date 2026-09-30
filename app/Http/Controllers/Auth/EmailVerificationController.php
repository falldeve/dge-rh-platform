<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /** Page « vérifiez votre email ». */
    public function notice(Request $request): RedirectResponse|View
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->to($this->home($request))
            : view('auth.verify-email');
    }

    /** Lien signé cliqué depuis l'email. */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill(); // marque vérifié + dispatch Verified
        }

        return redirect()->to($this->home($request))->with('ok', 'Email vérifié. Bienvenue.');
    }

    /** Renvoi du lien. */
    public function resend(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('ok', 'Lien de vérification renvoyé.');
    }

    private function home(Request $request): string
    {
        return \App\Support\Accueil::pour($request->user());
    }
}
