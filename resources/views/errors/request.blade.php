<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Permintaan belum berhasil · SIBLU</title>
    <link rel="stylesheet" href="{{ asset('modernize/src/assets/css/styles.min.css') }}">
</head>
<body class="bg-light">
    <main class="container py-5">
        <h1 class="h3">Permintaan belum berhasil</h1>
        <p>{{ __('http.messages')[$exception->getStatusCode()] ?? __('http.messages.'.($exception->getStatusCode() >= 500 ? 'server' : 'client')) }}</p>
        <a class="btn btn-primary" href="{{ url('/') }}">Ke beranda SIBLU</a>
    </main>
</body>
</html>
