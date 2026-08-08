<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $sekolah = $request->user('sekolah');

        if ($sekolah->hasVerifiedEmail()) {
            return redirect()->route('sekolah.riwayat.index');
        }

        $sekolah->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
