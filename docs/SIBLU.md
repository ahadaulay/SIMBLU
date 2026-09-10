# SIBLU — Pelaporan Kinerja Unit

## Implementasi

Stack proyek dipertahankan: Laravel 13, Livewire 3.8, Blade, dan Bootstrap dari template lama. Tidak ada dependency baru. Pola Gedung digunakan: controller membuka halaman, halaman memakai master dashboard bersama, komponen Livewire menangani tabel dan modal tambah/edit. Komponen tidak dipecah menjadi halaman create/edit.

Menu meliputi pengguna, hierarki unit, periode, kategori, formulir indikator, penugasan, pemetaan rekap, impor historis, laporan, dashboard, dan laporan custom.

## Penggunaan awal

1. Jalankan migration tambahan proyek jika belum diterapkan. Jangan memakai migrate:fresh pada database yang berisi data.
2. Tentukan admin dari akun yang sudah terdaftar dengan `php artisan reporting:admin email-akun`. Perintah meminta konfirmasi dan tidak mengubah password. Akun lama tidak otomatis mendapatkan role admin; registrasi publik tidak menerima role dari input.
3. Admin membuat unit, pengguna unit/pimpinan, kategori, indikator, dan periode.
4. Tentukan penugasan indikator untuk setiap pasangan unit–periode sebelum laporan dibuat.
5. Buka periode. Unit membuat laporan, mengisi, menyimpan draf, dan mengajukan.
6. Admin mencatat koreksi, mengembalikan laporan, atau memverifikasi. Catatan terbuka harus diselesaikan admin sebelum verifikasi.
7. Pilih periode di Laporan Custom untuk menampilkan atau mengekspor data resmi.

Login menerima email maupun username melalui kolom yang sama. Akun nonaktif ditolak. Unit hanya dapat mengakses laporan dan bukti miliknya. Pimpinan dapat membaca seluruh laporan dan rekap, tanpa mengubah data.

## Struktur data dan histori

Entitas transaksi, pengguna, unit, kategori, periode, indikator, penugasan, catatan, lampiran, dan histori disimpan secara relasional. Definisi kolom, opsi, butir, serta baris nilai dinamis disimpan sebagai JSON terstruktur dengan validasi server. Ini menyederhanakan tabel konfigurasi dari diagram awal tanpa menghilangkan tipe data dan hubungan laporan.

Setiap entri laporan menyimpan salinan definisi indikator. Mengubah master menambah versi dan tidak mengubah laporan yang sudah dibuat. Setiap penyimpanan draf, pengajuan, perubahan status, dan penambahan lampiran menyimpan arsip isi. Riwayat dapat dibuka melalui halaman laporan.

Versi laporan diperiksa saat penyimpanan dan transisi, di dalam transaksi dengan penguncian. Perubahan dari tab/akun lain ditolak agar data tidak tertimpa. Penugasan suatu unit tidak dapat diubah setelah laporan untuk periode tersebut dibuat.

## Aturan periode dan verifikasi

- DRAF: belum dapat diisi; DIBUKA: pengisian dan pengajuan tersedia; DITUTUP: hanya verifikasi; DIKUNCI: perubahan laporan dan verifikasi ditolak.
- Tenggat merupakan peringatan; pengisian tetap tersedia selama periode DIBUKA.
- Revisi setelah periode ditutup memerlukan pembukaan kembali periode oleh admin disertai alasan.
- Pembukaan kembali laporan terverifikasi memerlukan alasan. Laporan keluar dari rekap resmi sampai diverifikasi kembali; arsip versi sebelumnya tetap tersedia.
- Unit dapat menyimpan draf belum lengkap. Pengajuan dan verifikasi memvalidasi indikator wajib serta indikator opsional yang diisi.
- Tabel dinamis kosong dapat ditandai tidak ada kegiatan, wajib disertai penjelasan.
- Kelengkapan adalah jumlah indikator wajib yang valid dibagi jumlah indikator wajib, termasuk penjelasan dan lampiran yang diwajibkan. Angka yang terlihat merupakan keadaan terakhir yang disimpan.

## Formulir dan rumus

Formulir mendukung tunggal, tabel tetap, dan tabel dinamis. Field mendukung angka, teks/identitas, tanggal, pilihan, boolean, serta hasil perhitungan. Persentase dan rasio menggunakan field angka dengan satuan yang sesuai atau field perhitungan.

Rumus baris menggunakan jumlah, selisih, perkalian, pembagian, atau persentase. Sumber rumus ditulis sebagai kode field sebelumnya, dipisahkan koma. Rumus tidak menjalankan kode PHP atau ekspresi bebas. Pembagian dengan nol tidak diubah menjadi angka nol; pengajuan ditolak jika hasil yang diperlukan tidak valid.

Butir tabel tetap tersusun sesuai urutan konfigurasi. Untuk rincian berulang per prodi, gunakan tabel dinamis dengan kolom pilihan prodi, identitas mahasiswa, tingkat prestasi, dan informasi kegiatan. Identitas stabil diperlukan apabila ingin menghitung mahasiswa unik lintas periode.

## Rekap custom

Periode dipilih sebagai pasangan tahun–triwulan, termasuk pilihan tidak berurutan atau rentang lintas tahun. Filter unit dan indikator dapat digabungkan. Hasil dapat disusun per triwulan, per tahun, atau seluruh periode pilihan.

- Jumlah: jumlah nilai baris pada periode pilihan.
- Posisi terakhir: jumlah nilai pada periode terakhir yang tersedia; periode sumber ditampilkan.
- Rata-rata/minimum/maksimum: berdasarkan total setiap periode yang tersedia.
- Rasio: total pembilang dibagi total penyebut, dengan skala 1 atau 100.
- Identitas unik: jumlah nilai identitas berbeda dari seluruh baris dalam cakupan.
- Rincian: tanpa total numerik.

Pemetaan rekap memungkinkan satu formulir menghasilkan beberapa ukuran, misalnya menyaring tingkat Nasional dan Internasional lalu menghitung identitas mahasiswa unik. Pemetaan dapat menggabungkan unit; admin harus memastikan cakupan unit tidak menduplikasi data. Pemetaan terikat pada versi indikator sumber. Versi berbeda tidak dicampur dan dilaporkan sebagai cakupan yang tidak sesuai.

Perbandingan memakai triwulan yang sama satu tahun sebelumnya dan versi indikator yang sama. Persentase perubahan tidak tersedia bila pembanding nol/tidak tersedia. Arah perubahan angka dibedakan dari membaik/menurunnya kinerja.

Data belum diverifikasi ditampilkan sebagai cakupan yang belum tersedia, bukan nol. Pilihan hasil selalu menyertakan periode, status sumber, waktu pembuatan, dan tautan laporan pembentuknya.

Ekspor Excel berupa `.xlsx` dengan sheet Rekap, Rincian, dan Belum terverifikasi. Teks pengguna ditulis sebagai string untuk mencegah formula injection. PDF menggunakan halaman cetak dan fasilitas Simpan sebagai PDF pada browser, bukan generator PDF server.

## Impor historis

Admin mengunggah `.xlsx`, memilih unit, periode, indikator, sheet, rentang baris, dan kolom sumber untuk setiap field. Pratinjau wajib dibuat sebelum menyimpan. Perubahan pilihan membatalkan pratinjau. Impor hanya mengisi indikator yang belum memiliki nilai dalam draf; laporan diajukan oleh unit dan diverifikasi melalui alur biasa.

Header, subtotal, sel formula, dan sel error tidak dimasukkan sebagai input. Rasio/total dihitung ulang dari field sumber. Tanda `-` tidak otomatis dianggap nol. Jumlah/urutan baris untuk tabel tetap harus cocok dengan butir. File sumber pengguna tidak dimodifikasi.

Workbook lama belum diimpor otomatis ke database: pemetaan blok dan definisi seperti jumlah posisi vs penambahan, cakupan akreditasi, dan identitas unik harus ditinjau oleh admin pada pratinjau.

## Pemeriksaan dan operasi

Jalankan `php artisan test` dan `php vendor/bin/pint --test`. Jika PHP Windows belum mengaktifkan SQLite, jalankan pengujian dengan `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit`; database pengujian berada di memori.

Lampiran disimpan privat di disk local dan diunduh melalui pemeriksaan akses. Batas awal 10 MB dengan PDF, JPG, PNG, XLSX, DOCX; tersedia di konfigurasi reporting. Tidak ada email/notifikasi eksternal yang ditambahkan.

Ekspor langsung tersedia untuk laporan kecil. Ekspor latar belakang memakai koneksi queue `reporting` dan tabel `reporting_jobs`. Jalankan worker dengan `php artisan queue:work reporting --timeout=300 --tries=1`. Pemilik permintaan memeriksa status dan mengunduh hasil dari halaman Laporan Custom. Status gagal ditampilkan tanpa membocorkan data internal. File hasil antrean disimpan privat dan hak akses diperiksa kembali ketika diunduh.

Impor interaktif dibatasi 500 baris per blok dan workbook 10 MB. Lakukan pengujian kapasitas sesuai volume operasional sebelum produksi. PDF server dan notifikasi email belum ditambahkan; PDF menggunakan fasilitas cetak browser. Backup database dan lampiran serta uji pemulihan tetap perlu dikonfigurasi pada deployment.
