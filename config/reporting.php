<?php

return [
    'seed_admin' => [
        'email' => env('SIBLU_SEED_ADMIN_EMAIL', 'superadmin@uinsyahada.ac.id'),
        'password' => env('SIBLU_SEED_ADMIN_PASSWORD'),
    ],
    'seed_unit' => [
        'password' => env('SIBLU_SEED_UNIT_PASSWORD'),
    ],
    'attachment_max_kb' => 10240,
    'attachment_types' => ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'docx'],
    'statuses' => ['DRAFT' => 'Draf', 'SUBMITTED' => 'Diajukan', 'REVISION' => 'Perlu revisi', 'VERIFIED' => 'Terverifikasi'],
    'aggregations' => ['SUM' => 'Jumlah realisasi', 'LAST' => 'Posisi terakhir', 'AVERAGE' => 'Rata-rata', 'MIN' => 'Minimum', 'MAX' => 'Maksimum', 'RATIO' => 'Rasio dari total sumber', 'DISTINCT' => 'Jumlah identitas unik', 'DETAIL' => 'Rincian tanpa total'],
];
