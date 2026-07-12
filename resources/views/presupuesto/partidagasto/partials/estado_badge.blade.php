@php
    $nombre = trim((string) ($estado ?? ''));
    $clase = 'badge badge-light';
    $texto = $nombre;

    switch ($nombre) {
        case 'ACTIVA':
            $clase = 'badge badge-success';
            break;
        case 'CERRADA':
            $clase = 'badge badge-secondary';
            break;
        case 'ANULADA':
            $clase = 'badge badge-dark';
            break;
    }
@endphp
<span class="{{ $clase }}">{{ $texto }}</span>
