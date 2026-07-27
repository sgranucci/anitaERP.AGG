@php
    use App\Support\Solicitudpago\SolicitudpagoEstados;

    $estadoValor = trim((string) ($estado ?? ''));
    $clase = SolicitudpagoEstados::badgeClass($estadoValor);
    $texto = SolicitudpagoEstados::label($estadoValor);
@endphp
<span class="{{ $clase }}">{{ $texto }}</span>
