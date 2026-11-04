@extends('layouts.app')

@section('title', 'Doorprize RAT')
@section('pretitle', 'Pengelolaan Admin')

@section('content')
    <div class="alert alert-info">
        <i class="ti ti-info-circle me-1"></i>
        Undian hanya memakai peserta yang sudah tercatat hadir. Satu peserta satu hasil per rapat: menang atau gugur
        (sudah pulang). Jumlah pemenang diatur di <b>Manajemen RAT → tab Doorprize</b>.
    </div>

    <livewire:doorprize-undian />
@endsection
