# Deploy ke VPS

Catatan rilis DMA ke `https://8mataair.com` (panel staf + storefront) dan
`https://app.8mataair.com` (Kanban). Ditulis 2026-10-05 setelah rilis `d754818`.

## Alur rilis

1. Kerja di `develop`.
2. Saat rilis: `develop` di-merge (fast-forward) ke `main`, lalu `main` di-push.
3. Server menarik `main`. **Server tidak pernah menarik `develop`.**

```bash
git checkout main && git merge --ff-only develop && git push origin main && git checkout develop
```

## Deploy

Di VPS sudah ada skrip `/var/www/dma/deploy.sh`. **Pakai skrip itu**, jangan
menyusun perintah sendiri:

```bash
sh /var/www/dma/deploy.sh
```

Isinya (salinan per 2026-10-05 — skripnya **hanya ada di server**, tidak ikut repo):

```sh
#!/bin/sh
set -e
cd /var/www/dma
echo "==> Tarik kode terbaru (main)"; git pull origin main
echo "==> Composer";  docker compose exec -T app composer install --no-dev --optimize-autoloader
echo "==> Build asset"; docker run --rm -v /var/www/dma:/app -w /app node:20 sh -c "npm ci && npm run build"
echo "==> Migrasi";   docker compose exec -T app php artisan migrate --force
echo "==> Cache";     docker compose exec -T app php artisan optimize:clear
echo "==> Izin";      docker compose exec -T app chown -R www-data:www-data storage bootstrap/cache
echo "Deploy selesai."
```

Kalau **`Dockerfile` berubah**, bangun ulang image dulu sebelum menjalankan skrip:

```bash
cd /var/www/dma && docker compose build && docker compose up -d
```

## Kenapa asetnya dibangun di container

Node di host VPS **v10.19.0**, sedangkan proyek memakai **Vite 8** yang butuh
Node ≥ 20.19. Jadi `npm run build` langsung di host pasti gagal — itu sebabnya
skrip memakai container `node:20` sekali pakai.

`public/build` **tidak ikut git** (ada di `.gitignore`), jadi setiap rilis yang
menyentuh Blade, CSS, atau kelas Tailwind **wajib** melewati langkah build. Kalau
dilewati, kelas baru tidak ada di CSS dan tampilannya rusak — bukan sekadar
kurang rapi. `npm ci` memakai `package-lock.json` yang ikut repo, jadi versinya
terkunci.

## Keadaan server

| | |
|---|---|
| Path proyek | `/var/www/dma` |
| Servis PHP di compose | `app` |
| Skrip deploy | `/var/www/dma/deploy.sh` |
| `docker-compose.yml`, `Dockerfile`, `docker/nginx.conf`, `docker/php.ini`, `.env` | **hanya ada di server**, tidak ikut repo |

**Jangan sentuh `.env` di server.**

## Verifikasi setelah deploy

Dijalankan dari mana saja, tidak perlu SSH. Nama berkas aset mengandung hash isi,
jadi inilah cara paling cepat memastikan rilisnya benar-benar tayang:

```bash
curl -sL https://app.8mataair.com/ | grep -o 'assets/app-[A-Za-z0-9_-]*\.css'
```

Hasilnya harus sama dengan nama berkas yang dicetak Vite saat build. Lalu
pastikan berkasnya terkirim dan gzip masih aktif:

```bash
curl -s -o /dev/null -w '%{http_code} %{size_download}B\n' -H 'Accept-Encoding: gzip' \
  https://app.8mataair.com/build/assets/<nama-berkas>.css
```

Ukuran unduhnya harus mendekati angka gzip yang dicetak Vite, bukan ukuran mentah.
Kalau yang keluar ukuran mentah, berarti gzip di nginx mati — lihat catatan di
bawah.

Cek log kalau ada yang mencurigakan. Produksi menulis **`production.ERROR`**,
bukan `local.ERROR`:

```bash
docker compose exec -T app sh -c 'tail -n 80 storage/logs/laravel.log'
```

## Jebakan yang pernah kena

- **Mengubah `docker/nginx.conf` butuh restart, bukan reload.** `nginx -s reload`
  tidak membaca ulang bind-mount; harus `docker compose restart web`.
- **Memeriksa batas unggah PHP lewat `php -r` di CLI hanya sah** kalau setelannya
  ada di `conf.d` (lewat `docker/php.ini` yang di-mount). `public/.user.ini` yang
  ikut repo hanya berlaku untuk permintaan web, tidak terbaca CLI.
- **Service worker** (`public/sw.js`, cache `dma-v1`) kadang masih menyajikan
  halaman lama. Navigasi sudah network-first, tapi kalau ada yang melapor
  tampilannya belum berubah, minta **reload sekali** (Ctrl+Shift+R) sebelum
  menyimpulkan deploy-nya gagal. Aset CSS/JS tidak terdampak karena namanya
  ber-hash.
