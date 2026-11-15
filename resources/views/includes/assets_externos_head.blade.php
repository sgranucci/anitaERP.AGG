{{-- CSS externos (CDN) o locales según config('app.assets_cdn'). --}}
@php
    $assetsCdn = filter_var(config('app.assets_cdn', true), FILTER_VALIDATE_BOOLEAN);
    $themeAssets = $theme ?? ($themeV ?? 'lte');
    $incluirIonicons = $incluirIonicons ?? true;
    $incluirFuentes = $incluirFuentes ?? true;
    $incluirToastr = $incluirToastr ?? true;
    $incluirIeShims = $incluirIeShims ?? false;
@endphp
@if ($assetsCdn)
    @if ($incluirIonicons)
        <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    @endif
    @if ($incluirFuentes)
        <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
    @endif
    @if ($incluirToastr)
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    @endif
    @if ($incluirIeShims)
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
          <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
    @endif
@else
    {{-- Sin CDN: Font Awesome ya cubre iconos; tipografía del sistema. --}}
    @if ($incluirToastr)
        <link rel="stylesheet" href="{{ asset("assets/{$themeAssets}/plugins/toastr/toastr.min.css") }}">
    @endif
@endif
