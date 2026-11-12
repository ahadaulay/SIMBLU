@extends('Layout.Auth.master')

@section('title', 'Login')

@section('content')
    <h5 class="mb-2">Selamat datang di SIBLU</h5>
    <p class="mb-4">Silakan login untuk mengakses pelaporan kinerja unit.</p>

    <form id="login-form" action="{{ route('login.store') }}" method="POST" class="mb-3">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Email atau username</label>
            <input id="email" name="email" type="text" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="Masukkan email atau username" autocomplete="username" required autofocus>
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3 form-password-toggle">
            <label for="password" class="form-label">Password</label>
            <div class="input-group input-group-merge">
                <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" placeholder="••••••••" autocomplete="current-password" required>
                <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="mb-3 form-check">
            <input id="remember" name="remember" type="checkbox" value="1" class="form-check-input">
            <label for="remember" class="form-check-label">Ingat saya</label>
        </div>
        <button class="btn btn-primary d-grid w-100" type="submit">Login</button>
    </form>

    <p class="text-center mb-0">Belum punya akun? <a href="{{ route('register') }}">Daftar</a></p>

    <script>
        document.getElementById('login-form').addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.isComposing) {
                event.preventDefault();
                event.currentTarget.requestSubmit();
            }
        });
    </script>
@endsection
