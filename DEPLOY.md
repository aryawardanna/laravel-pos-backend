# Deploy ke Hosting (cPanel / Shared Hosting)

Panduan ini disesuaikan dengan setup yang sekarang dipakai:

```
/home/devr8224/web_disenja     <- aplikasi Laravel (isi repo ini)
/home/devr8224/public_html     <- document root, index.php menunjuk ke web_disenja
```

## Tiga aturan penting

1. **Jangan upload folder `vendor/` hasil `composer install` di lokal.**
   Di lokal `vendor/` berisi paket `require-dev` (PHPUnit, Collision, Pint, Pail,
   Sail, dll). Kalau ikut ter-upload, aplikasi gagal boot — lihat bagian
   "Kalau muncul error lagi" di bawah.
   `vendor/` juga sudah ada di `.gitignore`, jadi memang tidak boleh ikut repo.

2. **Jangan upload `bootstrap/cache/*.php` dari lokal**
   (`packages.php`, `services.php`, `config.php`, `routes-v7.php`).
   Isinya daftar service provider paket dev; kalau ter-upload, Laravel mencoba
   me-load provider yang tidak ada di hosting → fatal error lain lagi.

3. **`.env` di hosting jangan ditimpa** saat upload (berisi `APP_KEY` dan
   kredensial database produksi).

## Cara A — hosting punya SSH / Terminal cPanel

```bash
cd /home/devr8224/web_disenja

php -v                                   # pastikan PHP >= 8.2
composer install --no-dev --optimize-autoloader

# buang cache lama (isi cache lokal masih menyebut provider paket dev)
rm -f bootstrap/cache/*.php
php artisan optimize:clear
php artisan package:discover

# migrasi database (WAJIB tiap kali ada migrasi baru)
php artisan migrate --force

# opsional: cache untuk produksi
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Kalau `composer` tidak tersedia di hosting, pakai Cara B.

## Cara B — hanya bisa upload lewat FTP / File Manager

`vendor/` produksi dibangun di komputer lokal, di folder terpisah, supaya
`vendor/` lokal (yang dipakai untuk `php artisan test`) tidak ikut berubah.

```bash
# 1. Salin project tanpa vendor / node_modules / .git / .env
rsync -a --delete \
  --exclude 'vendor' --exclude 'node_modules' --exclude '.git' \
  --exclude '.env' --exclude 'bootstrap/cache/*.php' \
  ~/Documents/laravel-pos-backend/ /tmp/pos-prod/

# 2. Install dependensi produksi saja di folder sementara itu
cd /tmp/pos-prod
cp ~/Documents/laravel-pos-backend/.env .env    # supaya artisan bisa boot
composer install --no-dev --optimize-autoloader
```

Lalu **upload isi `/tmp/pos-prod/` ke `/home/devr8224/web_disenja/`**,
termasuk `vendor/` dan `bootstrap/cache/` hasil langkah 2 (keduanya sudah bersih
dari paket dev). Jangan upload `.env` lokal — biarkan `.env` hosting apa adanya.

Setelah upload, kalau ada migrasi baru, jalankan dari Terminal cPanel:

```bash
php artisan migrate --force
```

## Checklist `.env` produksi

```
APP_ENV=production
APP_DEBUG=false        # WAJIB false, kalau true stack trace + path server bocor ke user
APP_URL=https://domain-anda.com
APP_KEY=base64:...     # jangan dikosongkan

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true   # aktifkan kalau sudah HTTPS

POS_STORE_NAME="NAMA TOKO"    # ikut tercetak di struk
```

Setelah `.env` diubah: `php artisan config:clear` (atau `optimize:clear`).

## Kalau muncul error lagi

| Gejala | Penyebab | Solusi |
|---|---|---|
| `Class "NunoMaduro\Collision\..." not found` di `vendor/composer/autoload_real.php` | Autoloader masih memuat paket dev, tapi filenya tidak lengkap di hosting | Cara A: `composer install --no-dev --optimize-autoloader`. Cara B: upload `vendor/` hasil build `--no-dev` |
| `Class "Laravel\Pail\PailServiceProvider" not found` / `Laravel\Sail\...` | `bootstrap/cache/packages.php` dari lokal ikut ter-upload | `rm -f bootstrap/cache/*.php` lalu `php artisan package:discover` |
| `Class "NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider" not found` di `bootstrap/cache/services.php` | Sama seperti di atas (`services.php` dari lokal) | Sama seperti di atas |
| `SQLSTATE... Unknown column 'last_login_at'` | Migrasi baru belum dijalankan | `php artisan migrate --force` |
| Halaman putih / 500 tanpa pesan | `APP_DEBUG=false` dan ada error tersembunyi | Cek `storage/logs/laravel.log` |
| Upload/storage gagal | Permission folder | `chmod -R 775 storage bootstrap/cache` |

## Setelah deploy (cek cepat)

```bash
php artisan about              # ringkasan env, cache, driver
php artisan route:list | head  # pastikan route terdaftar
php artisan migrate:status     # pastikan tidak ada migrasi "Pending"
```

Terakhir, buka aplikasi di browser dan login — kalau halaman dashboard muncul,
deploy sudah benar.
