@extends('Layout.Dashboard.master')

@section('title', 'Profil')

@section('content')
    <div class="mb-4">
        <h4 class="fw-semibold mb-1">Profil Saya</h4>
        <p class="text-muted mb-0">Informasi akun dan pengaturan keamanan Anda.</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <img src="{{ asset('modernize/src/assets/images/profile/user-1.jpg') }}" alt="Foto profil" width="72" height="72" class="rounded-circle">
                        <div>
                            <h5 class="fw-semibold mb-1 text-dark">{{ $user->name }}</h5>
                            <span class="badge bg-light-primary text-primary">Pengguna SIPASET</span>
                        </div>
                    </div>

                    <div class="border-top pt-4">
                        <p class="text-muted mb-1">Nama lengkap</p>
                        <p class="fw-semibold text-dark mb-4">{{ $user->name }}</p>

                        <p class="text-muted mb-1">Alamat email</p>
                        <p class="fw-semibold text-dark mb-4">{{ $user->email }}</p>

                        <p class="text-muted mb-1">Terdaftar sejak</p>
                        <p class="fw-semibold text-dark mb-0">{{ $user->created_at?->translatedFormat('d F Y') ?: '—' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-4">
                        <span class="bg-light-primary rounded-3 p-2 d-flex"><i class="ti ti-lock fs-6 text-primary"></i></span>
                        <div>
                            <h5 class="fw-semibold mb-1 text-dark">Ganti Password</h5>
                            <p class="text-muted mb-0">Gunakan password yang kuat dan tidak dipakai di akun lain.</p>
                        </div>
                    </div>

                    <form action="{{ route('profile.password.update') }}" method="POST">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label for="current_password" class="form-label text-dark">Password saat ini</label>
                            <input id="current_password" name="current_password" type="password" class="form-control @error('current_password', 'passwordUpdate') is-invalid @enderror" autocomplete="current-password" required>
                            @error('current_password', 'passwordUpdate')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label text-dark">Password baru</label>
                            <input id="password" name="password" type="password" class="form-control @error('password', 'passwordUpdate') is-invalid @enderror" autocomplete="new-password" required>
                            <div class="form-text">Minimal 10 karakter, berisi huruf besar, huruf kecil, dan angka.</div>
                            @error('password', 'passwordUpdate')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label text-dark">Konfirmasi password baru</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" required>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i>Simpan Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
