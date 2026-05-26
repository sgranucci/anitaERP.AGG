@php
    $clase = 'badge badge-secondary';
    $texto = $estado;
    switch ($estado) {
        case 'BORRADOR':
            $clase = 'badge badge-secondary';
            $texto = 'Borrador';
            break;
        case 'PENDIENTE_APROBACION':
            $clase = 'badge badge-warning';
            $texto = 'Pend. aprobación';
            break;
        case 'APROBADO':
            $clase = 'badge badge-info';
            $texto = 'Aprobado';
            break;
        case 'RECHAZADO':
            $clase = 'badge badge-danger';
            $texto = 'Rechazado';
            break;
        case 'DEVUELTO':
            $clase = 'badge badge-success';
            $texto = 'Devuelto';
            break;
        case 'DEVUELTO_PARCIAL':
            $clase = 'badge badge-primary';
            $texto = 'Devuelto parcial';
            break;
        case 'CANCELADO':
            $clase = 'badge badge-dark';
            $texto = 'Cancelado';
            break;
    }
@endphp
<span class="{{ $clase }}">{{ $texto }}</span>
