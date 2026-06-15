@php
    $textoMostrar = (string) ($texto ?? '');
    $entityId = (int) ($id ?? 0);
    $puedeVer = (bool) ($puede ?? false);
    $routeName = $routeName ?? null;
@endphp
@if (($mostrarLinks ?? false) && $puedeVer && $entityId > 0 && $routeName)
    <a href="{{ route($routeName, ['id' => $entityId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
       target="_blank"
       rel="noopener"
       class="text-primary">{{ $textoMostrar }}</a>
@else
    {{ $textoMostrar }}
@endif
