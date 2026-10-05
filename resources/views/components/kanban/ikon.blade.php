{{--
    Ikon garis 24x24 untuk kartu & papan Kanban. Dipakai agar judul bagian dan
    tombol aksi punya penanda visual seperti Trello.
--}}
@props(['name', 'kelas' => 'h-4 w-4'])

@php
    $jalur = match ($name) {
        'anggota' => '<circle cx="12" cy="8" r="3.2" /><path d="M5 19.5c1.6-3 4-4.5 7-4.5s5.4 1.5 7 4.5" />',
        'deskripsi' => '<path d="M4 7h16M4 12h16M4 17h10" />',
        'checklist' => '<rect x="3.5" y="3.5" width="17" height="17" rx="3.5" /><path d="M8 12.4l2.6 2.6L16.2 9.4" />',
        'lampiran' => '<path d="M19 11.5l-7.3 7.3a3.6 3.6 0 11-5.1-5.1l7.8-7.8a2.4 2.4 0 113.4 3.4l-7.8 7.8a1.2 1.2 0 11-1.7-1.7l7-7" />',
        'aktivitas' => '<circle cx="12" cy="12" r="8.5" /><path d="M12 7.4V12l3 1.8" />',
        'komentar' => '<path d="M20.5 11.8a7.6 7.6 0 01-11 6.8L4.5 20l1.5-4.4a7.6 7.6 0 1114.5-3.8z" />',
        'bidang' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5" /><path d="M7.5 9.5h9M7.5 14h5" />',
        'label' => '<path d="M11.7 3.6H5.6a2 2 0 00-2 2v6.1a2 2 0 00.6 1.4l7.4 7.4a2 2 0 002.8 0l6.1-6.1a2 2 0 000-2.8L13.1 4.2a2 2 0 00-1.4-.6z" /><circle cx="8.3" cy="8.3" r="1.2" />',
        'jam' => '<rect x="3.5" y="5" width="17" height="15" rx="2.5" /><path d="M3.5 9.5h17M8 3.5V6M16 3.5V6" />',
        'gambar' => '<rect x="3.5" y="5" width="17" height="14" rx="2.5" /><circle cx="9" cy="10" r="1.5" /><path d="M4.5 17.5l4.6-4.3a2 2 0 012.7 0l7.7 6.6" />',
        'tautan' => '<path d="M10.4 13.6a4 4 0 005.7 0l2.3-2.3a4 4 0 10-5.7-5.7l-1.2 1.2M13.6 10.4a4 4 0 00-5.7 0l-2.3 2.3a4 4 0 105.7 5.7l1.2-1.2" />',
        'pindah' => '<path d="M4 12h14M13 7l5 5-5 5" />',
        'salin' => '<rect x="9" y="9" width="11" height="11" rx="2.5" /><path d="M15 9V6.5A2.5 2.5 0 0012.5 4h-6A2.5 2.5 0 004 6.5v6A2.5 2.5 0 006.5 15H9" />',
        'templat' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5" /><path d="M3.5 9.5h17M9.5 9.5v10" />',
        'mata' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" /><circle cx="12" cy="12" r="2.5" />',
        'arsip' => '<rect x="3.5" y="4.5" width="17" height="4.5" rx="1.5" /><path d="M5 9v9a2 2 0 002 2h10a2 2 0 002-2V9M10 13.5h4" />',
        'sampah' => '<path d="M5 7h14M10 7V5.6A1.6 1.6 0 0111.6 4h.8A1.6 1.6 0 0114 5.6V7M6.6 7l.8 12A2 2 0 009.4 21h5.2a2 2 0 002-1.9L17.4 7" />',
        'tambah' => '<path d="M12 5.5v13M5.5 12h13" />',
        'pesanan' => '<path d="M6.5 3.5h11a1 1 0 011 1V20l-2.5-1.5L13.5 20 11 18.5 8.5 20 6 18.5 5.5 20V4.5a1 1 0 011-1z" /><path d="M8.5 8.5h7M8.5 12.5h5" />',
        'titik' => '<path stroke-width="2.6" d="M5 12h.01M12 12h.01M19 12h.01" />',
        'tutup' => '<path d="M6.5 6.5l11 11M17.5 6.5l-11 11" />',
        'panah-bawah' => '<path d="M7 10l5 5 5-5" />',
        default => '',
    };
@endphp

<svg class="{{ $kelas }} shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $jalur !!}</svg>
