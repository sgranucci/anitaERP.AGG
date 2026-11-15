{{-- JS externos (CDN) o locales según config('app.assets_cdn'). API: sweetalert v1 (swal). --}}
@php
    $assetsCdn = filter_var(config('app.assets_cdn', true), FILTER_VALIDATE_BOOLEAN);
    $themeAssets = $theme ?? ($themeV ?? 'lte');
    $incluirSweetalert = $incluirSweetalert ?? true;
    $incluirToastr = $incluirToastr ?? true;
@endphp
@if ($assetsCdn)
    @if ($incluirSweetalert)
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    @endif
    @if ($incluirToastr)
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    @endif
@else
    @if ($incluirSweetalert)
        <script src="{{ asset('assets/vendor/sweetalert/sweetalert.min.js') }}"></script>
    @endif
    @if ($incluirToastr)
        <script src="{{ asset("assets/{$themeAssets}/plugins/toastr/toastr.min.js") }}"></script>
    @endif
@endif
