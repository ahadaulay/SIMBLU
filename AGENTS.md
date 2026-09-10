# Aturan Pengembangan Proyek Laravel

Dokumen ini adalah sumber aturan utama bagi AI dan developer yang bekerja pada proyek ini. Baca seluruh dokumen sebelum menganalisis, menambah, mengubah, memindahkan, atau menghapus kode.

## 1. Prosedur Wajib Sebelum Mengubah Kode

1. Baca `AGENTS.md` ini sampai selesai.
2. Periksa struktur proyek, `composer.json`, versi PHP dan Laravel, serta file konfigurasi yang berkaitan.
3. Cari implementasi serupa di dalam proyek dan ikuti pola yang sudah digunakan.
4. Periksa status Git. Jangan menimpa atau menghapus perubahan milik pengguna yang tidak berkaitan dengan tugas.
5. Jelaskan singkat rencana perubahan sebelum mulai jika perubahan menyentuh banyak file, database, autentikasi, otorisasi, pembayaran, atau integrasi eksternal.
6. Jangan membuat asumsi diam-diam. Tanyakan kepada pengguna jika kebutuhan bisnis, target file, atau dampaknya masih ambigu.

## 2. Prinsip Umum

-gunakan techstack yang sama dan alur yang sama. contoh jika pakai liviwire untuk crud nya maka pakai itu juga untuk yang ingin di buat dan ikuti alur dari crud sebelum nya
- Gunakan bahasa Indonesia untuk penjelasan kepada pengguna, kecuali diminta sebaliknya.
- Gunakan bahasa Inggris untuk nama class, method, variable, database field, route name, dan komentar kode.
- Buat solusi dan kode **sesederhana mungkin** selama tetap benar, aman, rapi, mudah dipahami, mudah dirawat, dan memiliki performa yang baik.
- Jika beberapa solusi sama-sama benar, pilih solusi dengan alur paling jelas, file paling sedikit, dependency paling sedikit, dan layer paling sedikit.
- Jangan mengejar kode yang paling pendek jika hasilnya sulit dibaca, rawan bug, tidak aman, atau lebih lambat. Sederhana berarti mudah dipahami dan dirawat, bukan sekadar sedikit baris.
- Jangan membuat interface, repository, service, action, trait, helper, DTO, event, atau design pattern tambahan jika fitur dapat diselesaikan dengan baik menggunakan pola Laravel yang sudah ada.
- Abstraksi baru hanya dibuat jika benar-benar dipakai ulang, mengurangi kerumitan nyata, atau sudah menjadi pola konsisten proyek.
- Gunakan nama yang jelas, alur lurus, dan early return jika membantu mengurangi nesting. Hindari kondisi bertingkat yang sulit dibaca.
- Jangan menyimpan logic yang sama di banyak tempat jika dapat dirapikan tanpa memecah kode menjadi terlalu banyak file kecil.
- Keamanan, validasi, otorisasi, integritas data, dan penanganan error tidak boleh dikurangi demi membuat kode terlihat lebih sederhana.
- Perhatikan performa yang nyata: hindari N+1 query, query di dalam loop, pengambilan data berlebihan, proses berulang, dan pemuatan seluruh data ketika pagination atau chunking lebih tepat.
- Jangan melakukan optimasi rumit tanpa bukti kebutuhan. Gunakan cache, queue, eager loading khusus, atau optimasi lanjutan hanya jika manfaatnya jelas dan tidak membuat alur sulit dirawat.
- Jika kebutuhan memang memerlukan solusi yang lebih kompleks, jelaskan alasannya sebelum memperkenalkan kompleksitas tersebut.
- Utamakan perubahan sekecil mungkin yang menyelesaikan kebutuhan dengan benar.
- Ikuti gaya, arsitektur, dan konvensi yang sudah ada. Jangan memperkenalkan pola, package, layer, atau abstraksi baru tanpa kebutuhan yang jelas.
- Jangan melakukan refactor di luar ruang lingkup tugas.
- Jangan mengubah public API, kontrak data, nama route, schema database, atau perilaku lama tanpa menjelaskan dampaknya.
- Hindari duplikasi, tetapi jangan membuat abstraksi sebelum benar-benar diperlukan.
- Kode harus mudah dibaca. Komentar hanya dipakai untuk menjelaskan alasan atau keputusan yang tidak terlihat dari kode, bukan mengulang isi kode.

## 3. Standar PHP dan Laravel

- Ikuti versi PHP dan Laravel yang tercantum pada `composer.json`; jangan memakai fitur dari versi yang lebih baru.
- Ikuti PSR-12 dan formatter proyek. Gunakan Laravel Pint jika tersedia.
- Gunakan type declaration, return type, enum, readonly, atau fitur PHP lain hanya jika didukung versi proyek dan sesuai pola yang ada.
- Gunakan fitur bawaan Laravel sebelum membuat solusi khusus.
- Gunakan dependency injection dan container Laravel. Hindari membuat dependency utama secara manual di dalam method.
- Jangan membaca `env()` di luar file konfigurasi. Akses nilai melalui `config()`.
- Jangan mengubah file di `vendor/`.
- Jangan menambahkan dependency Composer atau NPM tanpa persetujuan pengguna dan alasan yang jelas.
- Jangan menjalankan `composer update` untuk tugas yang tidak memerlukannya.

## 4. Struktur dan Tanggung Jawab Kode

- Route hanya menangani pemetaan endpoint dan middleware; hindari business logic di route closure.
- Controller harus tipis: menerima request, memanggil proses aplikasi, lalu mengembalikan response.
- Sebelum membuat CRUD baru, cari CRUD yang paling mirip dan sudah berjalan di proyek, lalu jadikan implementasi tersebut sebagai acuan utama. Ikuti pola arsitektur, alur halaman, komponen, interaksi, pencarian, sorting, pagination, modal atau halaman form, validasi, notifikasi, otorisasi, dan pengujiannya selama kebutuhan bisnis tidak menuntut perbedaan.
- Jika CRUD acuan menggunakan Livewire satu halaman dengan modal tambah dan edit, CRUD baru yang sejenis juga harus menggunakan pola tersebut. Jangan otomatis membuat halaman `create` dan `edit` terpisah hanya karena Laravel menyediakan resource controller.
- Penyimpangan dari pola CRUD yang sudah ada hanya diperbolehkan jika ada kebutuhan bisnis atau teknis yang nyata. Jelaskan alasannya sebelum menerapkan pola yang berbeda.
- Gunakan **satu fitur atau resource = satu controller**. Contoh: seluruh proses daftar, tambah, simpan, detail, edit, update, dan hapus user ditangani oleh `UserController` melalui method `index`, `create`, `store`, `show`, `edit`, `update`, dan `destroy`.
- Jangan memecah satu fitur menjadi banyak controller kecil seperti `ListUserController`, `CreateUserController`, `StoreUserController`, atau controller satu aksi lainnya.
- Untuk fitur khusus suatu role, controller boleh dikelompokkan berdasarkan role, misalnya `App\\Http\\Controllers\\Admin\\UserController`, tetapi tetap satu controller untuk satu fitur.
- Controller baru hanya boleh dipecah jika menangani fitur atau domain bisnis yang benar-benar berbeda, atau jika pengguna secara eksplisit meminta pemisahan.
- Gunakan Form Request untuk validasi yang tidak sederhana atau digunakan oleh endpoint produksi.
- Tempatkan business logic sesuai pola proyek yang sudah ada, misalnya pada service, action, model, job, atau domain class. Jangan membuat folder/layer baru hanya berdasarkan preferensi pribadi.
- Gunakan API Resource untuk format response JSON jika proyek sudah menggunakannya.
- Gunakan Job untuk proses lambat atau asynchronous bila queue memang tersedia dan sesuai kebutuhan.
- Gunakan Event dan Listener hanya jika membantu memisahkan efek samping atau mengikuti pola proyek.
- Hindari penggunaan Facade berlebihan jika dependency injection membuat kode lebih mudah diuji, tetapi tetap ikuti gaya proyek.

## 5. Database dan Eloquent

- Semua perubahan schema harus melalui migration baru. Jangan mengedit migration lama yang mungkin sudah pernah dijalankan, kecuali proyek masih tahap awal dan pengguna menyetujuinya.
- Migration harus memiliki operasi `down()` yang aman jika memungkinkan.
- Jangan menghapus table, column, index, atau data tanpa persetujuan eksplisit dan penjelasan risiko.
- Cegah N+1 query dengan eager loading yang tepat.
- Pilih hanya data yang diperlukan untuk query besar, tetapi jangan merusak hydration model atau relasi.
- Gunakan transaction untuk rangkaian perubahan data yang harus berhasil atau gagal sebagai satu kesatuan.
- Gunakan mass assignment dengan aman. Periksa `$fillable`, `$guarded`, cast, accessor, dan mutator.
- Jangan meletakkan data rahasia atau credential dalam seeder, factory, fixture, atau source code.
- Perubahan database harus mempertimbangkan data lama, nilai `null`, default, index, foreign key, serta proses deployment tanpa downtime jika relevan.

## 6. Validasi, Keamanan, dan Privasi

- Anggap semua input pengguna tidak tepercaya dan validasi di sisi server.
- Terapkan authentication dan authorization menggunakan middleware, Policy, Gate, atau mekanisme proyek yang sudah ada.
- Jangan hanya menyembunyikan tombol di UI; endpoint tetap wajib melakukan pemeriksaan izin.
- Hindari raw SQL. Jika terpaksa menggunakannya, selalu gunakan parameter binding dan jelaskan alasannya.
- Jangan menonaktifkan CSRF, signature validation, rate limit, SSL verification, atau pemeriksaan keamanan lain hanya agar fitur berjalan.
- Jangan mencatat password, token, session, secret, data pribadi sensitif, atau payload penuh yang mengandung data rahasia.
- Jangan memasukkan `.env`, key, credential, atau token ke repository maupun output percakapan.
- Escape output Blade secara default dengan `{{ }}`. Gunakan output tanpa escape hanya untuk konten yang sudah disanitasi dan benar-benar tepercaya.
- Untuk upload file, validasi tipe, ukuran, nama, lokasi penyimpanan, dan hak aksesnya.

## 7. API dan Response

- Pertahankan format response dan status code yang sudah digunakan proyek.
- Gunakan status code HTTP yang sesuai dan pesan error yang tidak membocorkan detail internal.
- Jangan mengubah nama field, tipe data, pagination, atau struktur error pada API yang sudah ada tanpa persetujuan.
- Jika perubahan berpotensi breaking, jelaskan dan tawarkan strategi kompatibilitas atau versi baru.

## 8. Frontend Laravel

- Jika proyek memakai Blade, Livewire, Inertia, Vue, atau React, ikuti stack dan pola yang sudah ada.
- Jangan mencampur framework frontend baru ke proyek tanpa persetujuan.
- Untuk Blade, kelompokkan halaman terlebih dahulu berdasarkan role, kemudian berdasarkan fitur. Gunakan nama folder role dan fitur dengan PascalCase, sedangkan nama file halaman menggunakan lowercase.
- Contoh halaman daftar user untuk admin adalah `resources/views/Admin/User/index.blade.php` dan dipanggil dengan `view('Admin.User.index')`.
- Terapkan pola yang sama untuk halaman lain, misalnya:

```text
resources/views/Admin/Dashboard/index.blade.php
resources/views/Admin/User/index.blade.php
resources/views/Admin/User/create.blade.php
resources/views/Admin/User/edit.blade.php
resources/views/User/Beranda/index.blade.php
```

- `master.blade.php` bukan pengganti file halaman. Setiap layar atau halaman nyata tetap harus memiliki file Blade sendiri, misalnya dashboard admin berada di `Admin/Dashboard/index.blade.php`, beranda user berada di `User/Beranda/index.blade.php`, dan daftar user berada di `Admin/User/index.blade.php`.
- Gunakan prinsip **satu halaman = satu file Blade**. Isi halaman, form, tabel, modal, filter, dan bagian lain yang hanya dipakai halaman tersebut tetap berada dalam file halaman yang sama.
- Jangan memecah satu halaman menjadi banyak partial, component, atau include kecil hanya untuk merapikan ukuran file.
- Partial hanya dibuat untuk bagian template yang dipakai bersama oleh banyak halaman dengan tampilan yang sama, seperti navbar, sidebar, footer, alert global, dan aset layout.
- Layout master ditentukan berdasarkan kesamaan struktur tampilan, bukan otomatis berdasarkan role. Beberapa role yang memakai bentuk dashboard yang sama harus menggunakan master dan partial yang sama.
- Contoh: Admin dan Pimpinan sama-sama memakai tampilan dashboard, sedangkan halaman umum user memiliki tampilan beranda yang berbeda:

```text
resources/views/Layout/Dashboard/master.blade.php
resources/views/Layout/Dashboard/partial/navbar.blade.php
resources/views/Layout/Dashboard/partial/sidebar.blade.php
resources/views/Layout/Dashboard/partial/footer.blade.php
resources/views/Admin/Dashboard/index.blade.php
resources/views/Pimpinan/Dashboard/index.blade.php

resources/views/Layout/Beranda/master.blade.php
resources/views/Layout/Beranda/partial/navbar.blade.php
resources/views/Layout/Beranda/partial/footer.blade.php
resources/views/User/Beranda/index.blade.php
```

- Dashboard Admin dan Pimpinan sama-sama menggunakan `@extends('Layout.Dashboard.master')`. Master dashboard memanggil partial bersama seperti `@include('Layout.Dashboard.partial.navbar')` dan `@include('Layout.Dashboard.partial.sidebar')`.
- Beranda User menggunakan `@extends('Layout.Beranda.master')` karena struktur tampilannya berbeda dari dashboard. Master beranda hanya memanggil partial milik tampilan beranda.
- File halaman seperti dashboard, beranda, index, create, dan edit hanya memanggil master yang sesuai dengan jenis tampilannya. File halaman tidak boleh memanggil navbar, sidebar, footer, atau partial layout secara langsung.
- Jangan membuat master baru hanya karena role berbeda. Buat master baru hanya jika struktur utama tampilan memang berbeda, misalnya susunan navigasi, sidebar, header, atau kerangka halaman berbeda.
- Jika perbedaan antar-role hanya pada menu, data, atau hak akses, tetap gunakan master yang sama dan atur perbedaannya melalui authorization atau kondisi yang sederhana.
- Jangan membuat master terpisah untuk setiap halaman atau fitur yang masih menggunakan struktur tampilan sama.
- Alur Blade yang wajib adalah: controller memanggil file halaman -> file halaman melakukan `@extends` ke master tampilannya -> master menggabungkan partial yang sesuai dan menampilkan konten halaman melalui `@yield`.
- Penulisan huruf besar dan kecil pada path serta pemanggilan view harus konsisten karena server Linux bersifat case-sensitive.
- Pertahankan aksesibilitas dasar: label form, navigasi keyboard, focus state, semantic HTML, dan pesan validasi yang jelas.
- Jangan menaruh business rule penting hanya di JavaScript; server tetap menjadi sumber kebenaran.
- Jalankan formatter, linter, type check, atau build frontend yang tersedia jika file frontend diubah.

## 9. Testing

- Setiap bug fix harus disertai regression test jika infrastruktur testing tersedia.
- Setiap fitur baru harus memiliki test untuk perilaku utama, validasi, kegagalan, dan otorisasi yang relevan.
- Gunakan Feature Test untuk perilaku endpoint/alur aplikasi dan Unit Test untuk logic yang benar-benar terisolasi.
- Jangan menghapus, melewati, atau melemahkan test hanya untuk membuat suite lulus.
- Gunakan factory dan state yang jelas. Hindari test yang bergantung pada urutan eksekusi, waktu nyata, network nyata, atau data lokal developer.
- Jalankan test paling spesifik terlebih dahulu, kemudian suite yang lebih luas jika waktu memungkinkan.

Perintah yang diprioritaskan jika tersedia:

```bash
php artisan test --filter=NamaTest
php artisan test
vendor/bin/pint --test
```

Jika proyek memakai alat lain seperti Pest, PHPStan, Larastan, Psalm, ESLint, atau Prettier, jalankan perintah yang didefinisikan oleh proyek.

## 10. Operasi yang Dilarang Tanpa Persetujuan

- Menghapus data, table, column, file penting, branch, atau riwayat Git.
- Menjalankan migration destructive atau command produksi.
- Menjalankan `migrate:fresh`, `db:wipe`, `schema:drop`, atau perintah sejenis.
- Mengubah `.env`, credential, permission server, deployment, DNS, queue produksi, cache produksi, atau layanan eksternal.
- Melakukan force push, hard reset, atau menimpa perubahan yang belum di-commit.
- Menambahkan package, service eksternal, telemetry, tracking, atau pengiriman data keluar.
- Mengubah konfigurasi autentikasi, otorisasi, session, CORS, CSRF, atau encryption tanpa penjelasan dan persetujuan.

## 11. Pemeriksaan Sebelum Menyatakan Selesai

AI wajib:

1. Meninjau diff dan memastikan hanya perubahan terkait tugas yang masuk.
2. Memeriksa error syntax dan import yang tidak terpakai.
3. Menjalankan formatter/linter pada file yang berubah jika tersedia.
4. Menjalankan test yang relevan dan mencatat hasil sebenarnya.
5. Memastikan tidak ada secret, debug statement (`dd`, `dump`, `ray`, `console.log`), atau file sementara yang tertinggal.
6. Memastikan migration, validation, authorization, error handling, dan backward compatibility telah dipertimbangkan.
7. Menyampaikan ringkasan file yang berubah, hasil pemeriksaan, dan risiko atau hal yang belum dapat diverifikasi.

Jika suatu pemeriksaan tidak dapat dijalankan, AI harus menyebutkan alasannya dengan jujur dan tidak boleh mengklaim bahwa pemeriksaan tersebut berhasil.

## 12. Prioritas Instruksi

Jika terjadi konflik, ikuti urutan berikut:

1. Permintaan terbaru pengguna.
2. Aturan pada `AGENTS.md` yang paling dekat dengan file yang diubah.
3. Aturan pada dokumen ini.
4. Konvensi dan pola kode yang sudah ada.
5. Praktik umum Laravel.

Aturan yang lebih spesifik boleh dibuat dalam `AGENTS.md` tambahan di subfolder tertentu, misalnya untuk `app/`, `database/`, atau `resources/js/`.
