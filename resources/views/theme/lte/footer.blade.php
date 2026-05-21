<footer class="main-footer">
    <div class="float-right d-none d-sm-block">
        <b>Version</b> 1.0.0
    </div>
    @if (config('app.empresa') == 'AGG')
        <img src="{{ asset('storage/imagenes/logos/AGG.png') }}" alt="AGG" class="mr-2 align-middle" style="max-height: 22px;">
    @elseif (config('app.empresa') == 'EL BIERZO')
        <img src="{{ asset('storage/imagenes/logos/logo-bierzo.png') }}" alt="El Bierzo" class="mr-2 align-middle" style="max-height: 22px;">
    @endif
    <strong class="align-middle">Copyright Sysgran SRL 2021-2026</strong>
</footer>
