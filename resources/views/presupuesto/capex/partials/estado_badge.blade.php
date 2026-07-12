@php
    $nombre = trim((string) ($estado ?? ''));
    $clase = 'badge badge-light';
    $texto = $nombre;

    switch ($nombre) {
        case 'ACTIVO':
            $clase = 'badge badge-success';
            break;
        case 'CERRADO':
            $clase = 'badge badge-secondary';
            break;
        case 'ANULADO':
            $clase = 'badge badge-dark';
            break;
    }
@endphp
<span class="{{ $clase }}">{{ $texto }}</span>
