@php
    use App\Support\Compras\OrdencompraEstados;

    $nombre = trim((string) ($estado ?? ''));
    $clase = 'badge badge-light';
    $texto = $nombre;

    switch ($nombre) {
        case OrdencompraEstados::PENDIENTE:
            $clase = 'badge badge-info';
            break;
        case OrdencompraEstados::APROBADA:
            $clase = 'badge badge-primary';
            break;
        case OrdencompraEstados::CUMPLIDA:
            $clase = 'badge badge-success';
            break;
        case OrdencompraEstados::SUSPENDIDA:
            $clase = 'badge badge-warning text-dark';
            break;
        case OrdencompraEstados::CERRADA:
            $clase = 'badge badge-secondary';
            break;
    }
@endphp
<span class="{{ $clase }}">{{ $texto }}</span>
