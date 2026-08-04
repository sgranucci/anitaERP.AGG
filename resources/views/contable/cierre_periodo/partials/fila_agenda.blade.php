@php
    $prog = $fila['programado'] ?? null;
    $estado = $fila['estado'] ?? '';
    $badge = match ($estado) {
        'pendiente' => 'badge-info',
        'ejecutado' => 'badge-success',
        'cancelado' => 'badge-secondary',
        'error' => 'badge-danger',
        default => 'badge-light',
    };
    $editable = $puede_ejecutar_cierre && $estado !== 'ejecutado';
    $esModulo = ! empty($fila['es_modulo']);
    $formId = 'form-prog-'.$fila['alcance'];
    $grupoCodigo = $grupo_codigo ?? ($esModulo ? ($fila['alcance'] ?? '') : '');
    $cantHijos = (int) ($cantidad_hijos ?? 0);
@endphp
<tr
    @if ($esModulo)
        class="table-primary font-weight-bold agenda-fila-modulo"
        data-grupo="{{ $grupoCodigo }}"
    @else
        class="agenda-fila-submodulo d-none"
        data-grupo-padre="{{ $grupoCodigo }}"
        hidden
    @endif
>
    <td class="align-middle">
        @if ($esModulo)
            <button type="button"
                class="btn btn-sm btn-outline-secondary agenda-toggle-submodulos mr-1"
                data-grupo="{{ $grupoCodigo }}"
                aria-expanded="false"
                title="Mostrar / ocultar submódulos">
                <i class="fa fa-chevron-right agenda-toggle-icon"></i>
            </button>
            <i class="fa fa-folder mr-1"></i>
            <strong>{{ $etiqueta_modulo ?? $fila['etiqueta'] }}</strong>
            @if ($cantHijos > 0)
                <span class="badge badge-secondary ml-1">{{ $cantHijos }}</span>
            @endif
            <br><small class="text-muted font-weight-normal pl-4">Cierra todos los submódulos</small>
        @else
            <span class="pl-4">{{ $fila['etiqueta'] }}</span>
            @if ($fila['alcance'] === 'facturacion')
                <br><small class="text-muted pl-4">Valida por fecha de jornada</small>
            @endif
        @endif
    </td>
    @if ($editable)
        <td class="align-middle">
            <input type="date" name="fecha_ejecucion" form="{{ $formId }}"
                class="form-control form-control-sm" required
                value="{{ old('fecha_ejecucion', $fila['fecha_ejecucion'] ?? date('Y-m-d')) }}">
        </td>
        <td class="align-middle">
            <input type="text" name="hora_ejecucion" form="{{ $formId }}"
                class="form-control form-control-sm"
                style="min-width:72px;"
                maxlength="5"
                placeholder="24:00"
                title="HH:MM o 24:00 (fin de día). Vacío = 24:00"
                pattern="^([01]?\d|2[0-3]):[0-5]\d$|^24:00$"
                value="{{ old('hora_ejecucion', $fila['hora_ejecucion'] ?? '24:00') }}">
        </td>
        <td class="align-middle">
            <input type="date" name="fecha_hasta" form="{{ $formId }}"
                class="form-control form-control-sm" required
                max="{{ date('Y-m-d') }}"
                value="{{ old('fecha_hasta', $fila['fecha_hasta']) }}">
        </td>
        <td class="align-middle">
            @if ($estado !== '')
                <span class="badge {{ $badge }}">{{ $estado }}</span>
                @if (!empty($fila['error_mensaje']))
                    <small class="text-danger d-block">{{ $fila['error_mensaje'] }}</small>
                @endif
            @else
                <span class="text-muted">sin programar</span>
            @endif
        </td>
        <td class="align-middle">
            <input type="text" name="observacion" form="{{ $formId }}"
                class="form-control form-control-sm" maxlength="2000"
                placeholder="Observación"
                value="{{ old('observacion', $fila['observacion'] ?? '') }}">
        </td>
        <td class="align-middle text-nowrap">
            <button type="submit" form="{{ $formId }}"
                class="btn btn-primary btn-sm" title="Guardar programación">
                <i class="fa fa-save"></i>
            </button>
            @if ($prog && ($fila['puede_ejecutar_ahora'] || $estado === 'error'))
                <form method="post"
                    action="{{ route('ejecutar_programado_cierre_periodo_contable', $prog->id) }}"
                    class="d-inline form-proceso-cierre"
                    data-overlay-titulo="Ejecutando cierre…">
                    @csrf
                    <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                    <input type="hidden" name="mes" value="{{ $mes }}">
                    <input type="hidden" name="anio" value="{{ $anio }}">
                    <button type="submit" class="btn btn-success btn-sm"
                        onclick="return confirm('¿Aplicar ahora el cierre de {{ $fila['etiqueta'] }}?');"
                        title="Aplicar ahora">
                        <i class="fa fa-play"></i>
                    </button>
                </form>
            @endif
            @if ($prog && in_array($estado, ['pendiente', 'error'], true))
                <form method="post"
                    action="{{ route('cancelar_programado_cierre_periodo_contable', $prog->id) }}"
                    class="d-inline">
                    @csrf
                    <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                    <input type="hidden" name="mes" value="{{ $mes }}">
                    <input type="hidden" name="anio" value="{{ $anio }}">
                    <button type="submit" class="btn btn-outline-secondary btn-sm"
                        onclick="return confirm('¿Cancelar la programación?');"
                        title="Cancelar">
                        <i class="fa fa-ban"></i>
                    </button>
                </form>
            @endif
        </td>
    @else
        <td class="align-middle">
            {{ $fila['fecha_ejecucion'] ? \Carbon\Carbon::parse($fila['fecha_ejecucion'])->format('d/m/Y') : '—' }}
        </td>
        <td class="align-middle">
            {{ $fila['hora_ejecucion'] ?? '24:00' }}
            @if (($fila['hora_ejecucion'] ?? '24:00') === '24:00')
                <small class="text-muted d-block">fin de día</small>
            @endif
        </td>
        <td class="align-middle">
            {{ \Carbon\Carbon::parse($fila['fecha_hasta'])->format('d/m/Y') }}
        </td>
        <td class="align-middle">
            @if ($estado !== '')
                <span class="badge {{ $badge }}">{{ $estado }}</span>
            @else
                <span class="text-muted">sin programar</span>
            @endif
        </td>
        <td class="align-middle">{{ $fila['observacion'] ?? '' }}</td>
        @if ($puede_ejecutar_cierre)
            <td></td>
        @endif
    @endif
</tr>
