@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif
<div id="livewire-request-error" class="alert alert-danger shadow position-fixed top-0 end-0 m-3 w-auto" style="z-index: 1100; max-width: min(28rem, calc(100% - 2rem))" role="alert" tabindex="-1" hidden>
    <p class="mb-2" data-request-error-message></p>
    <button class="btn btn-sm btn-outline-danger" type="button" onclick="this.parentElement.hidden = true">Tutup</button>
</div>
