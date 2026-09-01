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
        case 'ENVIADO':
            $clase = 'badge badge-info';
            $texto = 'Enviado';
            break;
        case 'APROBADO':
            $clase = 'badge badge-primary';
            $texto = 'En custodia';
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
        case 'CERRADO':
            $clase = 'badge badge-dark';
            $texto = 'Cerrado';
            break;
        case 'CANCELADO':
            $clase = 'badge badge-dark';
            $texto = 'Cancelado';
            break;
    }
@endphp
<span class="{{ $clase }}">{{ $texto }}</span>
