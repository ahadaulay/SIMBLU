# SIBLU

Sistem pelaporan kinerja berbasis Laravel. Dokumen ini menjelaskan cara menjalankan aplikasi secara lokal dan cara men-deploy-nya dengan Docker.

## Menjalankan aplikasi secara lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Sesuaikan koneksi database pada `.env` sebelum menjalankan migrasi. Aplikasi tersedia di `http://localhost:8000`.

## Pengujian

```bash
php artisan test
```

## Deployment dengan Docker

SIBLU dijalankan sebagai satu container aplikasi PHP 8.3 + Apache. Database memakai **MySQL bersama (shared)** yang berjalan terpisah dan diakses lewat Docker network eksternal `shared-net`. SIBLU tidak membangun container database sendiri. Hanya `storage` aplikasi yang disimpan pada named volume. phpMyAdmin tidak dipasang pada production.

File yang dipakai:

| File | Fungsi |
|---|---|
| `Dockerfile` | Membangun image aplikasi PHP 8.3 + Apache (multi-stage: `php-base` -> `vendor` -> `runtime`). |
| `compose.production.yaml` | Mendefinisikan service `app` yang tersambung ke network `shared-net`. |
| `apache/000-default.conf` | Virtual host Apache dengan document root `public`. |
| `docker/php.ini` | Setelan PHP produksi (OPcache, batas unggah). |
| `docker/docker-entrypoint.sh` | Menyiapkan storage dan membangun ulang cache saat start. Tidak menjalankan migrasi otomatis. |
| `.env.production` | Environment aplikasi (dibuat dari `.env.production.example`). |

Prasyarat pada server: Docker Engine 24+ dan Docker Compose v2. Verifikasi dengan:

```powershell
docker version
docker compose version
```

Semua perintah dijalankan dari root proyek (folder yang memuat `compose.production.yaml`).

### 1. Pastikan shared MySQL dan network siap

SIBLU tidak menjalankan database. Sebelum deploy, pastikan MySQL bersama sudah berjalan dan tersambung ke network `shared-net`.

```powershell
# Buat network bersama jika belum ada
docker network create shared-net

# Sambungkan container MySQL bersama ke network yang sama (lewati jika sudah)
docker network connect shared-net <nama-container-mysql>

# Verifikasi anggota network
docker network inspect shared-net
```

Di dalam shared MySQL, buat database dan user khusus SIBLU dengan hak akses terbatas pada database tersebut. Nilai host, nama database, user, dan password ini dipakai pada `.env.production`.

### 2. Siapkan environment

Windows PowerShell:

```powershell
Copy-Item .env.production.example .env.production
```

Linux/macOS:

```bash
cp .env.production.example .env.production
```

Isi nilai berikut sebelum melanjutkan:

- `APP_URL`: URL aplikasi. Uji lokal HTTP pakai `http://localhost:8800`; production pakai `https://domain-anda`.
- `DB_HOST`: hostname container shared MySQL pada `shared-net` (mis. `shared-mysql`).
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: kredensial database SIBLU pada shared MySQL.
- `SESSION_SECURE_COOKIE`: `false` saat uji lokal HTTP, `true` di production HTTPS.

Untuk membuat password acak yang kuat:

```powershell
# Windows PowerShell
[Convert]::ToBase64String((1..24 | ForEach-Object { Get-Random -Maximum 256 }))
```

```bash
# Linux/macOS
openssl rand -base64 24
```

Jangan commit `.env.production`. Kredensial dari konfigurasi lama harus dirotasi dan tidak boleh digunakan kembali.

### 3. Build image dan buat APP_KEY

```powershell
docker compose --env-file .env.production -f compose.production.yaml build app
docker compose --env-file .env.production -f compose.production.yaml run --rm app php artisan key:generate --show
```

Salin hasil perintah kedua ke `APP_KEY` di `.env.production`.

### 4. Jalankan migrasi

Migrasi dijalankan manual terhadap shared MySQL, dari image yang sama dengan yang akan dijalankan.

```powershell
docker compose --env-file .env.production -f compose.production.yaml run --rm app php artisan migrate --force
```

Untuk instalasi baru yang memerlukan data awal (akun admin, unit, dan akun unit), jalankan seeder sekali saja:

```powershell
docker compose --env-file .env.production -f compose.production.yaml run --rm app php artisan db:seed --force
```

> Seeder membuat akun administrator dan akun unit awal. Ganti password akun tersebut segera sebelum aplikasi dibuka ke publik. Jangan menjalankan seeder otomatis pada setiap deployment.

### 5. Jalankan aplikasi

```powershell
docker compose --env-file .env.production -f compose.production.yaml up -d app
```

Secara default aplikasi tersedia di `http://localhost:8800`. Atur `APP_PORT` di `.env.production` jika port host perlu diubah. Untuk production publik, tempatkan reverse proxy/load balancer HTTPS di depan port ini.

### 6. Verifikasi

Pastikan container `app` berstatus `healthy`:

```powershell
docker compose --env-file .env.production -f compose.production.yaml ps
```

Uji koneksi ke shared MySQL berhasil:

```powershell
docker compose --env-file .env.production -f compose.production.yaml exec app php artisan migrate:status
```

Uji aplikasi merespons:

```powershell
# Windows PowerShell
Invoke-WebRequest http://localhost:8800 -UseBasicParsing | Select-Object StatusCode
```

```bash
# Linux/macOS
curl -I http://localhost:8800
```

Jika ada masalah, periksa log:

```powershell
docker compose --env-file .env.production -f compose.production.yaml logs app
```

### Operasional

```powershell
# Status dan health container
docker compose --env-file .env.production -f compose.production.yaml ps

# Log aplikasi
docker compose --env-file .env.production -f compose.production.yaml logs -f app

# Terapkan perubahan .env (config cache dibuat ulang saat start)
docker compose --env-file .env.production -f compose.production.yaml up -d --force-recreate app

# Hentikan aplikasi
docker compose --env-file .env.production -f compose.production.yaml down
```

Gunakan migrasi yang backward-compatible karena container versi lama masih dapat melayani request saat migrasi berjalan. `down --volumes` tidak menghapus data database karena database berada di luar project, tetapi tetap menghapus volume `app_storage`. Selalu backup database sebelum migrasi atau upgrade.

### Build ulang SIBLU saja (database tidak tersentuh)

Karena database eksternal, SIBLU tidak pernah membangun atau memulai ulang container database. Setelah ada perubahan kode, cukup bangun ulang dan ganti container `app`.

```powershell
# Perubahan kode tanpa migration baru: build lalu ganti container app sekaligus
docker compose --env-file .env.production -f compose.production.yaml up -d --build app
```

Jika ada migration baru, build dulu, backup database, jalankan migrasi dari image baru, lalu ganti container:

```powershell
# 1. Build ulang image aplikasi
docker compose --env-file .env.production -f compose.production.yaml build app

# 2. Backup database, lalu migrasi dari image baru
docker compose --env-file .env.production -f compose.production.yaml run --rm app php artisan migrate --force

# 3. Ganti container aplikasi dengan image yang baru dibangun
docker compose --env-file .env.production -f compose.production.yaml up -d --no-build app
```

Setelah selesai, pastikan container kembali `healthy`:

```powershell
docker compose --env-file .env.production -f compose.production.yaml ps app
```

### Rotasi password database

Password diubah pada shared MySQL, bukan lewat SIBLU. Masuk ke shared MySQL sesuai prosedur pengelola database tersebut, lalu ubah password user SIBLU:

```sql
ALTER USER 'siblu_user'@'%' IDENTIFIED BY 'PASSWORD-BARU-YANG-KUAT';
FLUSH PRIVILEGES;
```

Perbarui `DB_PASSWORD` di `.env.production`, buat ulang container aplikasi, lalu verifikasi koneksi:

```powershell
docker compose --env-file .env.production -f compose.production.yaml up -d --force-recreate app
docker compose --env-file .env.production -f compose.production.yaml exec app php artisan migrate:status
```

## Struktur data referensi

Seeder berada di direktori `database/seeders` dan dipanggil lewat `DatabaseSeeder`:

- `AdminSeeder.php` - akun administrator awal.
- `UnitSeeder.php` - daftar unit.
- `UnitAccountSeeder.php` - akun login tiap unit.

Seeder contoh data pelaporan tersedia namun dinonaktifkan secara default di `DatabaseSeeder`:

- `ReportingExampleSeeder.php`
- `CompleteWorkbookSeeder.php`

Kredensial akun awal diambil dari `.env` (`SIBLU_SEED_ADMIN_EMAIL`, `SIBLU_SEED_ADMIN_PASSWORD`, `SIBLU_SEED_UNIT_PASSWORD`). Isi nilai tersebut sebelum menjalankan seeder, dan ganti password setelah instalasi pertama.

### Deploy cepat dengan migrasi dan seed

Alur ringkas untuk server yang sudah berjalan (container harus sudah pernah dibuat). Perintah dijalankan di dalam container yang sedang berjalan:

```powershell
docker compose --env-file .env.production -f compose.production.yaml up -d --build app
docker compose --env-file .env.production -f compose.production.yaml exec app php artisan optimize:clear
docker compose --env-file .env.production -f compose.production.yaml exec app php artisan migrate --seed --force
docker compose --env-file .env.production -f compose.production.yaml exec app php artisan optimize
```

> `--seed` menjalankan seeder di atas. Untuk upgrade (bukan instalasi baru) sebaiknya jalankan `migrate --force` saja tanpa `--seed` agar data referensi tidak ditimpa, dan selalu backup database lebih dulu.

## Lisensi

Proyek menggunakan komponen Laravel yang tersedia di bawah lisensi MIT.
