<a href="{{ route('editar_tesoreria_crown_mesa', $mov->id) }}" class="btn-accion-tabla tooltipsC" title="{{ $mov->esBorrador() ? 'Editar' : 'Consultar' }}">
    <i class="fa fa-edit"></i>
</a>
@if ($mov->esBorrador() && $jornada->estaAbierta() && can('eliminar-borrador-tesoreria-crown-mesa', false))
    <form method="POST" action="{{ route('eliminar_tesoreria_crown_mesa', $mov->id) }}" class="d-inline" onsubmit="return confirm('¿Eliminar este borrador? No queda una reversa.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn-accion-tabla tooltipsC" title="Eliminar borrador">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </form>
@endif
@if ($mov->puedeRevertir() && $jornada->estaAbierta() && can('editar-tesoreria-crown-mesa', false))
    <button type="button" class="btn-accion-tabla tooltipsC js-revertir-mesa"
            title="Revertir"
            data-url="{{ route('revertir_tesoreria_crown_mesa', $mov->id) }}"
            data-numero="{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($mov->numero) }}">
        <i class="fa fa-undo text-warning"></i>
    </button>
@endif
@if ($mov->reversa)
    <a href="{{ route('editar_tesoreria_crown_mesa', $mov->reversa->id) }}" class="btn-accion-tabla tooltipsC" title="Ver reversa">
        <i class="fa fa-undo"></i>
    </a>
@endif
