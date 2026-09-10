@php
    use App\Support\Compras\OrdencompraEstados;

    $nombre = trim((string) ($estado ?? ''));
    $clase = 'oc-pill oc-pill-neutro';

    switch ($nombre) {
        case OrdencompraEstados::PENDIENTE:
            $clase = 'oc-pill oc-pill-pendiente';
            break;
        case OrdencompraEstados::APROBADA:
            $clase = 'oc-pill oc-pill-aprobada';
            break;
        case OrdencompraEstados::CUMPLIDA:
            $clase = 'oc-pill oc-pill-cumplida';
            break;
        case OrdencompraEstados::SUSPENDIDA:
            $clase = 'oc-pill oc-pill-suspendida';
            break;
        case OrdencompraEstados::CERRADA:
            $clase = 'oc-pill oc-pill-cerrada';
            break;
    }
@endphp
<span class="{{ $clase }}">{{ $nombre !== '' ? $nombre : '—' }}</span>
