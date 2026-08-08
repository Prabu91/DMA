<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        return $request->user('sekolah')->hasVerifiedEmail()
            ? redirect()->route('sekolah.riwayat.index')
            : view('storefront.auth.verify-email');
    }
}
