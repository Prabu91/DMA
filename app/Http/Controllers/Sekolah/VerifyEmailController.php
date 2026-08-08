<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\Sekolah;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Verifikasi email sekolah lewat tautan bertanda-tangan (signed).
 * Tidak butuh sesi login: identitas & keaslian dijamin id + hash + signature,
 * sehingga tautan bisa dibuka di perangkat/browser mana pun.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        $sekolah = Sekolah::withoutGlobalScopes()->findOrFail($id);

        abort_unless(
            hash_equals(sha1($sekolah->getEmailForVerification()), $hash),
            403
        );

        if (! $sekolah->hasVerifiedEmail()) {
            $sekolah->markEmailAsVerified();
            event(new Verified($sekolah));
        }

        Auth::guard('sekolah')->login($sekolah);

        return redirect()->route('sekolah.riwayat.index')->with('status', 'verified');
    }
}
