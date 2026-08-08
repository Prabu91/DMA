<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CabangController extends Controller
{
    public function index(): View
    {
        $cabang = Cabang::withCount(['users', 'sekolah', 'orders'])
            ->orderBy('nama')
            ->get();

        return view('cabang.index', compact('cabang'));
    }

    public function create(): View
    {
        return view('cabang.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Cabang::create($this->validated($request));

        return redirect()->route('app.cabang.index')->with('success', 'Cabang ditambahkan.');
    }

    public function edit(Cabang $cabang): View
    {
        return view('cabang.edit', compact('cabang'));
    }

    public function update(Request $request, Cabang $cabang): RedirectResponse
    {
        $cabang->update($this->validated($request));

        return redirect()->route('app.cabang.index')->with('success', 'Cabang diperbarui.');
    }

    public function destroy(Cabang $cabang): RedirectResponse
    {
        // Cegah hapus bila masih dipakai data transaksional (jaga integritas FK).
        $dipakai = $cabang->users()->exists()
            || $cabang->sekolah()->exists()
            || $cabang->orders()->exists();

        if ($dipakai) {
            return back()->with('error', 'Cabang tidak bisa dihapus karena masih memiliki pengguna, sekolah, atau order.');
        }

        // Kota hanya pemetaan area (FK nullable) — lepaskan dulu agar tak melanggar FK.
        $kotaLepas = $cabang->kota()->count();

        DB::transaction(function () use ($cabang) {
            $cabang->kota()->update(['cabang_id' => null]);
            $cabang->delete();
        });

        $pesan = 'Cabang dihapus.'.($kotaLepas > 0 ? " {$kotaLepas} kota dilepas dari cabang ini." : '');

        return redirect()->route('app.cabang.index')->with('success', $pesan);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'kode_area' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
