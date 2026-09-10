<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · SIBLU</title>
    <link rel="shortcut icon" type="image/png" href="{{ asset('modernize/src/assets/images/logos/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('modernize/src/assets/css/styles.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @livewireStyles
</head>
<body>
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full" data-sidebar-position="fixed" data-header-position="fixed">
        @include('Layout.Dashboard.partial.sidebar')

        <div class="body-wrapper">
            @include('Layout.Dashboard.partial.navbar')

            <main class="container-fluid">
                @include('Layout.Dashboard.partial.alert')
                @yield('content')
            </main>
        </div>
    </div>

    @include('Layout.Dashboard.partial.scripts')
</body>
</html>
