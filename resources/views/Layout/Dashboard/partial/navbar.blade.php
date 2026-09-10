<header class="app-header">
    <nav class="navbar navbar-expand-lg navbar-light" aria-label="Navigasi pengguna">
        <ul class="navbar-nav">
            <li class="nav-item d-block d-xl-none">
                <button class="nav-link sidebartoggler nav-icon-hover border-0 bg-transparent" id="headerCollapse" type="button" aria-label="Buka menu">
                    <i class="ti ti-menu-2"></i>
                </button>
            </li>
        </ul>
        <div class="navbar-collapse justify-content-end px-0">
            <ul class="navbar-nav flex-row ms-auto align-items-center justify-content-end">
                <li class="nav-item me-3 d-none d-sm-block">
                    <span class="fw-semibold text-dark">{{ auth()->user()->name }}</span>
                </li>
                <li class="nav-item dropdown">
                    <button class="nav-link nav-icon-hover border-0 bg-transparent" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Buka menu profil">
                        <img src="{{ asset('modernize/src/assets/images/profile/user-1.jpg') }}" alt="" width="35" height="35" class="rounded-circle">
                    </button>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-animate-up">
                        <div class="message-body">
                            <a href="{{ route('profile.show') }}" class="d-flex align-items-center gap-2 dropdown-item">
                                <i class="ti ti-user fs-6"></i>
                                <span class="fs-3">Profil</span>
                            </a>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button class="btn btn-outline-primary mx-3 mt-2 d-block" type="submit">Logout</button>
                            </form>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </nav>
</header>
