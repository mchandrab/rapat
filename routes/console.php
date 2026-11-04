<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// BR-SHU-LOG-01: retensi log pencarian SHU publik 14 hari, dibersihkan setiap hari.
Schedule::command('cek-shu:purge')->daily();

// Fase 10: cadangan harian setelah acara selesai (retensi 7 hari).
Schedule::command('erat:backup')->dailyAt('23:00');
