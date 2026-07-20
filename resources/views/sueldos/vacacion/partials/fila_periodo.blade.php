@php
    use App\Support\Sueldos\VacacionTipoDia;
    $idxLinea = $idxLinea ?? 0;
    $nroLinea = $linea->nro_linea ?? '';
    $fechaDesde = '';
    if (! empty($linea->fecha_desde)) {
        $fechaDesde = $linea->fecha_desde instanceof \Carbon\Carbon
            ? $linea->fecha_desde->format('Y-m-d')
            : (string) $linea->fecha_desde;
    }
    $fechaHasta = '';
    if (! empty($linea->fecha_hasta)) {
        $fechaHasta = $linea->fecha_hasta instanceof \Carbon\Carbon
            ? $linea->fecha_hasta->format('Y-m-d')
            : (string) $linea->fecha_hasta;
    }
    $tipoDia = VacacionTipoDia::normalizar($linea->tipo_dia ?? null) ?? '';
    $cantidadDias = $linea->cantidad_dias ?? '';
    $opcionesTipo = VacacionTipoDia::OPCIONES;
    if ($tipoDia !== '' && ! isset($opcionesTipo[$tipoDia])) {
        $opcionesTipo[$tipoDia] = $tipoDia;
    }
@endphp
<tr class="item-vacacion-periodo">
    <td>
        <input type="number" name="nro_linea[]" class="form-control nro-linea" min="1" step="1"
               value="{{ old('nro_linea.'.$idxLinea, $nroLinea) }}">
    </td>
    <td>
        <input type="date" name="fecha_desde[]" class="form-control fecha-desde"
               value="{{ old('fecha_desde.'.$idxLinea, $fechaDesde) }}">
    </td>
    <td>
        <input type="date" name="fecha_hasta[]" class="form-control fecha-hasta"
               value="{{ old('fecha_hasta.'.$idxLinea, $fechaHasta) }}">
    </td>
    <td>
        <select name="tipo_dia[]" class="form-control tipo-dia">
            <option value="">—</option>
            @foreach ($opcionesTipo as $codigoTipo => $etiquetaTipo)
                <option value="{{ $codigoTipo }}" {{ $tipoDia === $codigoTipo ? 'selected' : '' }}>
                    {{ $etiquetaTipo }}
                </option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="number" name="cantidad_dias[]" class="form-control cantidad-dias" min="0" step="1"
               value="{{ old('cantidad_dias.'.$idxLinea, $cantidadDias) }}">
    </td>
    <td class="text-center">
        <button type="button" title="Elimina esta l&iacute;nea" class="btn-accion-tabla eliminar_vacacion_periodo tooltipsC">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
