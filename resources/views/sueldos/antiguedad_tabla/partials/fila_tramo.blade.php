@php
    $t = is_array($tramo ?? null) ? (object) $tramo : ($tramo ?? null);
@endphp
<tr class="item-antiguedad-tramo">
    <td>
        <input type="number" name="tramos[{{ $idx }}][nro_linea]" class="form-control form-control-sm nro-linea" min="1"
               value="{{ old('tramos.'.$idx.'.nro_linea', $t->nro_linea ?? '') }}"/>
    </td>
    <td>
        <input type="number" name="tramos[{{ $idx }}][anio]" class="form-control form-control-sm" min="1" max="80"
               value="{{ old('tramos.'.$idx.'.anio', $t->anio ?? '') }}" placeholder="Años"/>
    </td>
    <td>
        <input type="number" name="tramos[{{ $idx }}][porcentaje]" class="form-control form-control-sm" step="0.000001"
               value="{{ old('tramos.'.$idx.'.porcentaje', $t->porcentaje ?? '') }}" placeholder="0"/>
    </td>
    <td>
        <input type="number" name="tramos[{{ $idx }}][cantidad]" class="form-control form-control-sm" step="0.01"
               value="{{ old('tramos.'.$idx.'.cantidad', $t->cantidad ?? '') }}" placeholder="0"/>
    </td>
    <td class="text-center align-middle">
        <a href="#" class="btn-accion-tabla eliminar_antiguedad_tramo tooltipsC" title="Quitar tramo">
            <i class="fa fa-times-circle text-danger"></i>
        </a>
    </td>
</tr>
