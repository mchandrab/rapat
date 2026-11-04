<?php

// Satu sumber menu per role. Otorisasi tetap wajib di server melalui middleware.
return [
    'admin' => [
        ['label' => 'Utama', 'items' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'layout-dashboard'],
        ]],
        ['label' => 'Operasional', 'items' => [
            ['label' => 'Anggota & Undangan', 'route' => 'anggota.index', 'icon' => 'users'],
            ['label' => 'Riwayat Undangan', 'route' => 'undangan.index', 'icon' => 'mail-forward'],
            ['label' => 'Manajemen Rapat', 'route' => 'rapat.index', 'icon' => 'calendar-event'],
            ['label' => 'Kas Scanner', 'route' => 'kas.index', 'icon' => 'cash'],
            ['label' => 'Presensi & Bukti', 'route' => 'presensi.index', 'icon' => 'id-badge-2'],
            ['label' => 'Kuorum', 'route' => 'kuorum.index', 'icon' => 'chart-histogram'],
            ['label' => 'SHU', 'route' => 'shu.index', 'icon' => 'report-money'],
            ['label' => 'Doorprize', 'route' => 'doorprize.index', 'icon' => 'gift'],
        ]],
        ['label' => 'Pelaporan', 'items' => [
            ['label' => 'Laporan', 'route' => 'laporan.index', 'icon' => 'file-analytics'],
            ['label' => 'Audit', 'route' => 'audit.index', 'icon' => 'shield-check'],
        ]],
        ['label' => 'Sistem', 'items' => [
            [
                'label' => 'Pengaturan',
                'route' => 'pengguna.index',
                'active' => ['pengguna.*', 'integrasi.*', 'pengaturan.aplikasi'],
                'icon' => 'settings',
            ],
        ]],
    ],

    'helpdesk' => [
        ['label' => 'Utama', 'items' => [
            ['label' => 'Ringkasan', 'route' => 'dashboard', 'icon' => 'layout-dashboard'],
        ]],
        ['label' => 'Pelayanan', 'items' => [
            ['label' => 'Anggota & Undangan', 'route' => 'anggota.index', 'icon' => 'users'],
            ['label' => 'Riwayat Undangan', 'route' => 'undangan.index', 'icon' => 'mail-forward'],
        ]],
        ['label' => 'Pelaporan', 'items' => [
            ['label' => 'Laporan', 'route' => 'laporan.index', 'icon' => 'file-analytics'],
        ]],
    ],

    'scanner' => [
        ['label' => 'Utama', 'items' => [
            ['label' => 'Scan QR', 'route' => 'dashboard', 'icon' => 'scan'],
        ]],
        ['label' => 'Operasional', 'items' => [
            ['label' => 'Kas Saya', 'route' => 'kas-saya', 'icon' => 'wallet'],
            ['label' => 'Status Koneksi', 'route' => 'sinkron', 'icon' => 'wifi'],
        ]],
    ],

    'keuangan' => [
        ['label' => 'Utama', 'items' => [
            ['label' => 'Dashboard Kas', 'route' => 'dashboard', 'icon' => 'layout-dashboard'],
        ]],
        ['label' => 'Kas & Transport', 'items' => [
            ['label' => 'Alokasi & Monitor', 'route' => 'kas.index', 'icon' => 'cash'],
            ['label' => 'Presensi & Bukti', 'route' => 'presensi.index', 'icon' => 'id-badge-2'],
            ['label' => 'Kuorum', 'route' => 'kuorum.index', 'icon' => 'chart-histogram'],
            ['label' => 'Rekonsiliasi', 'route' => 'rekonsiliasi', 'icon' => 'scale'],
        ]],
        ['label' => 'Pelaporan', 'items' => [
            ['label' => 'Laporan Transport', 'route' => 'transport', 'icon' => 'report-analytics'],
            ['label' => 'Laporan', 'route' => 'laporan.index', 'icon' => 'file-analytics'],
        ]],
    ],
];
