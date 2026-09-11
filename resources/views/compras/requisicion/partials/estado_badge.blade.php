@php
    $nombre = trim((string) ($estado ?? ''));
    $clase = 'oc-pill oc-pill-neutro';

    switch ($nombre) {
        case 'PROVISORIO':
            $clase = 'oc-pill oc-pill-provisorio';
            break;
        case 'PENDIENTE':
            $clase = 'oc-pill oc-pill-pendiente';
            break;
        case 'EN COMPRAS':
            $clase = 'oc-pill oc-pill-en-compras';
            break;
        case 'EN ARBOL APROBACION':
            $clase = 'oc-pill oc-pill-en-arbol';
            break;
        case 'APROBADA':
            $clase = 'oc-pill oc-pill-aprobada';
            break;
        case 'CUMPLIDA':
            $clase = 'oc-pill oc-pill-cumplida';
            break;
        case 'SUSPENDIDA':
            $clase = 'oc-pill oc-pill-suspendida';
            break;
        case 'GENERO ORDEN COMPRA':
        case 'GENERO OC':
            $clase = 'oc-pill oc-pill-genero-oc';
            break;
    }
@endphp
<span class="{{ $clase }}">{{ $nombre !== '' ? $nombre : '—' }}</span>
