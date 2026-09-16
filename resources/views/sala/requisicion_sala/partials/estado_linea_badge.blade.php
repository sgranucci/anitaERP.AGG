@php
    use App\Models\Sala\RequisicionSalaArticulo;

    $valor = (string) ($estado ?? ' ');
    if (trim($valor) === '') {
        $valor = ' ';
    }
    $nombreCompleto = RequisicionSalaArticulo::estadoLineaNombrePorValor($valor);

    switch ($nombreCompleto) {
        case 'ENTREGADO':
        case 'CERRADO':
            $clase = 'badge badge-success';
            $texto = 'Cumplida';
            break;
        case 'ENTREGADO PARCIAL':
            $clase = 'badge badge-warning';
            $texto = 'Parcial';
            break;
        case 'PARA RETIRAR':
            $clase = 'badge badge-primary';
            $texto = 'A retirar';
            break;
        case 'PENDIENTE REP':
            $clase = 'badge badge-secondary';
            $texto = 'Pend. rep';
            break;
        default:
            $clase = 'badge badge-info';
            $texto = 'Pendiente';
            break;
    }
@endphp
<span class="{{ $clase }}" style="font-size:0.65rem;padding:0.15em 0.4em;vertical-align:middle;" title="{{ $nombreCompleto }}">{{ $texto }}</span>
