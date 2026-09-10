<aside class="left-sidebar">
    <div>
        <div class="brand-logo d-flex align-items-center justify-content-between">
            <a href="{{ route('dashboard') }}" class="text-nowrap logo-img d-flex align-items-center gap-2" aria-label="SIBLU - Dashboard">
                <img
                    src="{{ asset('sneat/assets/img/favicon/logouin.png') }}"
                    width="40"
                    height="46"
                    class="object-fit-contain"
                    alt="Logo UIN Syahada Padangsidimpuan"
                >
                <span class="fs-7 fw-bold text-dark">SIBLU</span>
            </a>

            <button class="close-btn d-xl-none d-block sidebartoggler border-0 bg-transparent" id="sidebarCollapse" type="button" aria-label="Tutup menu">
                <i class="ti ti-x fs-8"></i>
            </button>
        </div>

        <nav class="sidebar-nav scroll-sidebar" aria-label="Navigasi utama">
            <ul id="sidebarnav">
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}">
                        <span><i class="ti ti-layout-dashboard"></i></span>
                        <span class="hide-menu">Dashboard</span>
                    </a>
                </li>

                @can('reporting-admin')
                    @php
                        $sidebarSections = [
                            [
                                'label' => 'MASTER SIBLU',
                                'items' => [
                                    [['units.*', 'users.*'], 'units.index', 'ti-hierarchy-3', 'Unit Kerja'],
                                    [['categories.*', 'indicators.*', 'metrics.*'], 'categories.index', 'ti-category-2', 'Kategori Indikator'],
                                    [['periods.*', 'assignments.*'], 'periods.index', 'ti-calendar-event', 'Periode Tahun'],
                                ],
                            ],
                            [
                                'label' => 'PELAPORAN KINERJA',
                                'items' => [
                                    ['imports.*', 'imports.index', 'ti-file-import', 'Impor Historis'],
                                    ['reports.*', 'reports.index', 'ti-clipboard-check', 'Verifikasi Laporan'],
                                    ['reporting.dashboard', 'reporting.dashboard', 'ti-chart-bar', 'Pemantauan'],
                                    ['recaps.*', 'recaps.index', 'ti-file-analytics', 'Laporan Custom'],
                                ],
                            ],
                        ];
                    @endphp

                    @foreach($sidebarSections as $section)
                        <li class="nav-small-cap">
                            <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                            <span class="hide-menu">{{ $section['label'] }}</span>
                        </li>

                        @foreach($section['items'] as [$routePattern, $routeName, $icon, $label])
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs($routePattern) ? 'active' : '' }}" href="{{ route($routeName) }}">
                                    <span><i class="ti {{ $icon }}"></i></span>
                                    <span class="hide-menu">{{ $label }}</span>
                                </a>
                            </li>
                        @endforeach
                    @endforeach
                @else
                    @can('reporting-access')
                        <li class="nav-small-cap">
                            <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                            <span class="hide-menu">
                                {{ auth()->user()->role === 'UNIT' ? 'ALUR PELAPORAN UNIT' : 'LAPORAN KINERJA' }}
                            </span>
                        </li>

                        @if(auth()->user()->role === 'UNIT')
                            @php
                                $reportingMenus = [
                                    ['reports.*', 'reports.index', 'ti-edit', '1. Isi Laporan'],
                                    ['reporting.dashboard', 'reporting.dashboard', 'ti-list-check', '2. Pantau Status'],
                                    ['recaps.*', 'recaps.index', 'ti-file-analytics', '3. Lihat Rekap'],
                                ];
                            @endphp
                        @else
                            @php
                                $reportingMenus = [
                                    ['reporting.dashboard', 'reporting.dashboard', 'ti-chart-bar', 'Pemantauan'],
                                    ['reports.*', 'reports.index', 'ti-clipboard-text', 'Laporan Unit'],
                                    ['recaps.*', 'recaps.index', 'ti-file-analytics', 'Laporan Custom'],
                                ];
                            @endphp
                        @endif

                        @foreach($reportingMenus as [$routePattern, $routeName, $icon, $label])
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs($routePattern) ? 'active' : '' }}" href="{{ route($routeName) }}">
                                    <span><i class="ti {{ $icon }}"></i></span>
                                    <span class="hide-menu">{{ $label }}</span>
                                </a>
                            </li>
                        @endforeach
                    @endcan
                @endcan
            </ul>
        </nav>
    </div>
</aside>
