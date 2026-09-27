@extends('layouts.error')

@section('title', 'Akses Ditolak')

@section('main')
    <div class="empty">
        <div class="empty-header">
            403
        </div>
        <p class="empty-title">
            Anda tidak punya akses ke halaman ini
        </p>
        <p class="empty-subtitle text-muted">
            Menu ini tidak termasuk dalam hak akses role Anda.
            Hubungi administrator bila Anda merasa ini keliru.
        </p>
        <div>
            <a href="{{ url('/home') }}" class="btn btn-primary mt-3">
                <i class="fas fa-home"></i>&nbsp; Kembali ke Dashboard
            </a>
        </div>
    </div>
@endsection
