@extends('Layout.Auth.master')

@section('title', 'Register')

@section('content')
    <h5 class="mb-2">Buat akun SIBLU</h5>
    <p class="mb-4">Daftar untuk mengakses Sistem Pelaporan Kinerja Unit.</p>

    <form action="{{ route('register.store') }}" method="POST" class="mb-3">
        @csrf
        <div class="mb-3">
            <label for="name" class="form-label">Nama</label>
            <input id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Nama lengkap" autocomplete="name" required autofocus>
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="Masukkan email" autocomplete="email" required>
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" placeholder="Minimal 10 karakter" autocomplete="new-password" required>
            <div class="form-text">Gunakan huruf besar, huruf kecil, dan angka.</div>
            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Konfirmasi Password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" placeholder="Ulangi password" autocomplete="new-password" required>
        </div>
        <button class="btn btn-primary d-grid w-100" type="submit">Daftar</button>
    </form>

    <p class="text-center mb-0">Sudah punya akun? <a href="{{ route('login') }}">Login</a></p>
@endsection
