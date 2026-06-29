@php
    $nombre = trim((string) ($estado ?? ''));
    $clase = 'badge badge-light';
    $texto = $nombre;

    switch ($nombre) {
        case 'PENDIENTE':
            $clase = 'badge badge-info';
            break;
        case 'EN LABORATORIO':
        case 'EN ARBOL APROBACION':
        case 'A AUTORIZAR':
            $clase = 'badge badge-primary';
            break;
        case 'APROBADA':
        case 'CUMPLIDO':
            $clase = 'badge badge-success';
            break;
        case 'PARCIAL':
        case 'AUTORIZACION ESPECIAL':
            $clase = 'badge badge-warning';
            break;
        case 'SUSPENDIDO':
            $clase = 'badge badge-dark';
            break;
        case 'RECHAZADA':
            $clase = 'badge badge-danger';
            break;
        case 'GENERO ORDEN COMPRA':
            $clase = 'badge badge-secondary';
            break;
    }
@endphp
<span class="{{ $clase }}">{{ $texto }}</span>
