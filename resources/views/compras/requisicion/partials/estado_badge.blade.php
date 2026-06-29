@php
    $nombre = trim((string) ($estado ?? ''));
    $clase = 'badge badge-light';
    $texto = $nombre;

    switch ($nombre) {
        case 'PROVISORIO':
            $clase = 'badge badge-secondary';
            break;
        case 'PENDIENTE':
            $clase = 'badge badge-info';
            break;
        case 'EN COMPRAS':
            $clase = 'badge badge-primary';
            break;
        case 'EN ARBOL APROBACION':
            $clase = 'badge badge-warning';
            break;
        case 'APROBADA':
        case 'CUMPLIDA':
            $clase = 'badge badge-success';
            break;
        case 'SUSPENDIDA':
            $clase = 'badge badge-dark';
            break;
        case 'GENERO ORDEN COMPRA':
            $clase = 'badge badge-info';
            break;
    }
@endphp
<span class="{{ $clase }}">{{ $texto }}</span>
