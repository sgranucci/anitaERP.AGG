@extends("theme.$theme.layout")
@section('titulo')
    Conciliaci&oacute;n flash estacionamiento
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
@if (can('ejecutar-cierre-rendicion-estacionamiento-contable', false))
<script>
    window.CIERRE_REND_EST_CONC = {
        urlEjecutarJornada: @json(route('api_cierre_rendicion_estacionamiento_ejecutar_jornada')),
        urlEjecutarPeriodo: @json(route('api_cierre_rendicion_estacionamiento_ejecutar_rango')),
    };
</script>
<script src="{{ asset('assets/pages/scripts/contable/cierre_rendicion_estacionamiento/conciliacion_flash.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cierre_rendicion_estacionamiento/conciliacion_flash.js')) ?: time() }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Conciliaci&oacute;n rendiciones vs flash (estacionamiento)</h3>
                <div class="card-tools ml-auto">
                    <a href="{{ route('cierre_rendicion_estacionamiento_contable', $retornoListadoQuery ?? []) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info py-2 small mb-3">
                    <strong>C&oacute;mo leer la conciliaci&oacute;n</strong>
                    <ul class="mb-0 pl-3">
                        <li><strong>Fact. neta</strong> (cobrado + invitaciones) se compara con <code>flash_estac</code> → diferencia &ldquo;fact. − flash&rdquo;.</li>
                        <li><strong>Venta total</strong> (fact. neta + notas de cr&eacute;dito) se compara con &Sigma; debe de asientos → diferencia &ldquo;venta total − asientos&rdquo;.</li>
                        <li>Si hay NC, el asiento suele ser mayor que el cobrado/flash: eso no es error; mir&aacute; la columna <em>Venta total</em>.</li>
                    </ul>
                    Tolerancia flash: {{ number_format((float) config('estacionamiento.cierre_rendicion_contable.conciliacion_flash_tolerancia', 0.02), 2, ',', '.') }}.
                </div>
                <form method="get" action="{{ route('cierre_rendicion_estacionamiento_conciliacion_flash') }}" class="mb-4">
                    @foreach ($retornoListadoQuery ?? [] as $retornoKey => $retornoVal)
                        <input type="hidden" name="retorno[{{ $retornoKey }}]" value="{{ $retornoVal }}">
                    @endforeach
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4">
                            <label for="empresa_id">Empresa</label>
                            <select name="empresa_id" id="empresa_id" class="form-control" required>
                                <option value="">— Seleccione —</option>
                                @foreach ($empresa_query as $emp)
                                    <option value="{{ $emp->id }}" @selected((int) ($empresa_id ?? 0) === (int) $emp->id)>
                                        {{ $emp->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="fecha_desde">Jornada desde</label>
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                   value="{{ $fecha_desde ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="fecha_hasta">Jornada hasta</label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                   value="{{ $fecha_hasta ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-2">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </form>

                @if (! empty($error_flash))
                    <div class="alert alert-danger">{{ $error_flash }}</div>
                @endif

                @if ($consultar && empty($error_flash) && $resultado !== null)
                    @php
                        $resumen = $resultado['resumen'] ?? [];
                        $tol = (float) ($resultado['tolerancia'] ?? 0.02);
                    @endphp
                    <div class="alert alert-light border">
                        <div class="d-flex flex-wrap align-items-start justify-content-between">
                            <div>
                                <strong>{{ $resultado['empresa_nombre'] ?? '' }}</strong>
                                — {{ \Carbon\Carbon::parse($resultado['fecha_desde'])->format('d/m/Y') }}
                                al {{ \Carbon\Carbon::parse($resultado['fecha_hasta'])->format('d/m/Y') }}
                                <br>
                                <span class="small text-muted">
                                    {{ (int) ($resumen['total_dias'] ?? 0) }} jornada(s) con actividad —
                                    {{ (int) ($resumen['dias_ok'] ?? 0) }} OK,
                                    {{ (int) ($resumen['dias_dif'] ?? 0) }} con diferencia
                                    @if ((int) ($resumen['total_pendiente_cierre'] ?? 0) > 0)
                                        — <span class="text-warning font-weight-bold">
                                            {{ (int) $resumen['total_pendiente_cierre'] }} rend. pendiente(s)
                                            ({{ (int) ($resumen['total_grupos_pendientes'] ?? 0) }} asiento(s))
                                        </span>
                                    @endif
                                </span>
                            </div>
                            <div class="d-flex flex-wrap align-items-center">
                                @if (can('exportar-cierre-rendicion-estacionamiento-contable', false))
                                    <div class="mr-2 mb-1">
                                        @include('includes.exportar-tabla-queryparams', [
                                            'ruta' => 'listar_cierre_rendicion_estacionamiento_conciliacion_flash',
                                            'queryparams' => $filtrosQueryConciliacion ?? [],
                                        ])
                                    </div>
                                @endif
                                @if (can('ejecutar-cierre-rendicion-estacionamiento-contable', false)
                                    && (int) ($resumen['total_grupos_pendientes'] ?? 0) > 0)
                                    <button type="button"
                                            class="btn btn-success btn-sm mt-2 mt-md-0"
                                            id="btn-cerrar-periodo-conc"
                                            data-empresa-id="{{ (int) ($resultado['empresa_id'] ?? 0) }}"
                                            data-fecha-desde="{{ $resultado['fecha_desde'] ?? '' }}"
                                            data-fecha-hasta="{{ $resultado['fecha_hasta'] ?? '' }}"
                                            data-grupos="{{ (int) ($resumen['total_grupos_pendientes'] ?? 0) }}"
                                            data-pendientes="{{ (int) ($resumen['total_pendiente_cierre'] ?? 0) }}"
                                            data-jornadas="{{ (int) ($resumen['jornadas_con_pendientes'] ?? 0) }}"
                                            title="Generar asientos de todos los grupos pendientes del periodo">
                                        <i class="fa fa-lock"></i> Cerrar periodo completo
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if (empty($resultado['dias']))
                        <p class="text-muted text-center py-4">Sin rendiciones ni flash en el rango indicado.</p>
                    @else
                        <div class="accordion" id="accordion-conciliacion-dias">
                            @foreach ($resultado['dias'] as $idx => $dia)
                                @php
                                    $estado = (string) ($dia['estado'] ?? '');
                                    $badgeClass = match ($estado) {
                                        'OK' => 'badge-success',
                                        'DIF' => 'badge-danger',
                                        default => 'badge-secondary',
                                    };
                                    $collapseId = 'dia-collapse-'.$idx;
                                    $difFlash = (float) ($dia['diferencia'] ?? 0);
                                    $difVentaAsientos = (float) ($dia['diferencia_venta_total_asientos'] ?? 0);
                                    $hayNc = (float) ($dia['total_rendiciones_notas_credito'] ?? 0) > 0;
                                @endphp
                                <div class="card mb-1 {{ $estado === 'DIF' ? 'border-danger' : '' }}">
                                    <div class="card-header p-2" id="heading-{{ $idx }}">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                                            <button class="btn btn-link text-left p-0 collapsed" type="button"
                                                    data-toggle="collapse" data-target="#{{ $collapseId }}"
                                                    aria-expanded="false" aria-controls="{{ $collapseId }}">
                                                <strong>{{ $dia['fecha_jornada_fmt'] ?? '' }}</strong>
                                                <span class="badge {{ $badgeClass }} ml-2">{{ $estado !== '' ? $estado : '—' }}</span>
                                                <small class="text-muted ml-2">
                                                    {{ (int) ($dia['cantidad_rendiciones'] ?? 0) }} rend.
                                                </small>
                                            </button>
                                            <div class="text-right small d-flex flex-wrap align-items-center justify-content-end">
                                                @if (can('ejecutar-cierre-rendicion-estacionamiento-contable', false)
                                                    && (int) ($dia['cantidad_grupos_pendientes'] ?? 0) > 0)
                                                    <button type="button"
                                                            class="btn btn-success btn-sm mr-2 mb-1 js-cerrar-jornada-conc"
                                                            data-empresa-id="{{ (int) ($resultado['empresa_id'] ?? 0) }}"
                                                            data-fecha-jornada="{{ $dia['fecha_jornada'] ?? '' }}"
                                                            data-fecha-fmt="{{ $dia['fecha_jornada_fmt'] ?? '' }}"
                                                            data-grupos="{{ (int) ($dia['cantidad_grupos_pendientes'] ?? 0) }}"
                                                            data-pendientes="{{ (int) ($dia['cantidad_pendiente'] ?? 0) }}"
                                                            title="Generar asientos de la jornada (fecha + PV)">
                                                        <i class="fa fa-lock"></i> Cerrar jornada
                                                    </button>
                                                @endif
                                                <span class="mr-3">Fact. neta: <strong>{{ number_format((float) ($dia['total_rendiciones_facturacion'] ?? 0), 2, ',', '.') }}</strong></span>
                                                @if ($hayNc)
                                                    <span class="mr-3">NC: <strong>{{ number_format((float) ($dia['total_rendiciones_notas_credito'] ?? 0), 2, ',', '.') }}</strong></span>
                                                    <span class="mr-3">Venta total: <strong>{{ number_format((float) ($dia['total_rendiciones_ventas_brutas'] ?? 0), 2, ',', '.') }}</strong></span>
                                                @endif
                                                <span class="mr-3">Asientos: <strong>{{ number_format((float) ($dia['total_asientos_debe'] ?? 0), 2, ',', '.') }}</strong></span>
                                                <span class="mr-3">Flash: <strong>{{ number_format((float) ($dia['total_flash_estac'] ?? 0), 2, ',', '.') }}</strong></span>
                                                <span class="mr-2 {{ abs($difFlash) > $tol ? 'text-danger font-weight-bold' : 'text-success' }}">
                                                    Fact.−flash: {{ number_format($difFlash, 2, ',', '.') }}
                                                </span>
                                                <span class="{{ abs($difVentaAsientos) > $tol ? 'text-danger font-weight-bold' : 'text-success' }}"
                                                      title="Venta total (neta + NC) menos Σ debe de asientos">
                                                    Venta−asientos: {{ number_format($difVentaAsientos, 2, ',', '.') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="{{ $collapseId }}" class="collapse" data-parent="#accordion-conciliacion-dias">
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead style="background:#85C1E9;color:#17202A;">
                                                    <tr>
                                                        <th>Punto de venta</th>
                                                        <th class="text-center">Cant.</th>
                                                        <th class="text-right">Cobrado</th>
                                                        <th class="text-right">Invit.</th>
                                                        <th class="text-right">Fact. neta</th>
                                                        <th class="text-right">NC</th>
                                                        <th class="text-right">Venta total</th>
                                                        <th class="text-right">Asientos (&Sigma; debe)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($dia['puntos_venta'] ?? [] as $pv)
                                                        <tr>
                                                            <td>
                                                                <strong>{{ $pv['pv_codigo'] ?? '' }}</strong>
                                                                @if (! empty($pv['pv_nombre']) && ($pv['pv_nombre'] ?? '') !== ($pv['pv_codigo'] ?? ''))
                                                                    — {{ $pv['pv_nombre'] }}
                                                                @endif
                                                            </td>
                                                            <td class="text-center">{{ (int) ($pv['cantidad'] ?? 0) }}</td>
                                                            <td class="text-right">{{ number_format((float) ($pv['total_cobrado'] ?? 0), 2, ',', '.') }}</td>
                                                            <td class="text-right">
                                                                @if ((float) ($pv['total_invitaciones'] ?? 0) > 0)
                                                                    {{ number_format((float) $pv['total_invitaciones'], 2, ',', '.') }}
                                                                @else
                                                                    —
                                                                @endif
                                                            </td>
                                                            <td class="text-right">{{ number_format((float) ($pv['total_facturacion'] ?? 0), 2, ',', '.') }}</td>
                                                            <td class="text-right">
                                                                @if ((float) ($pv['total_notas_credito'] ?? 0) > 0)
                                                                    {{ number_format((float) $pv['total_notas_credito'], 2, ',', '.') }}
                                                                @else
                                                                    —
                                                                @endif
                                                            </td>
                                                            <td class="text-right">{{ number_format((float) ($pv['total_ventas_brutas'] ?? 0), 2, ',', '.') }}</td>
                                                            <td class="text-right">
                                                                @if ((float) ($pv['total_asientos_debe'] ?? 0) > 0)
                                                                    {{ number_format((float) $pv['total_asientos_debe'], 2, ',', '.') }}
                                                                @elseif ((int) ($pv['cantidad_legacy'] ?? 0) > 0 && (int) ($pv['cantidad_asientos'] ?? 0) === 0 && (int) ($pv['cantidad_pendiente'] ?? 0) === 0)
                                                                    <span class="badge badge-secondary">Hist&oacute;rico</span>
                                                                @elseif ((int) ($pv['cantidad_pendiente'] ?? 0) > 0)
                                                                    <span class="badge badge-warning">Pendiente</span>
                                                                @else
                                                                    —
                                                                @endif
                                                                @if (! empty($pv['asientos']))
                                                                    <br><small>
                                                                        @foreach ($pv['asientos'] as $asi)
                                                                            @if (! empty($asi['legacy']))
                                                                                <span class="text-muted" title="Rend. #{{ $asi['rendicion_id'] ?? '' }}">Hist.</span>@if (! $loop->last), @endif
                                                                            @elseif (! empty($asi['asiento_id']) && can('listar-asiento', false))
                                                                                <a href="{{ route('editar_asiento', ['id' => $asi['asiento_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                                                                   class="text-primary" target="_blank" rel="noopener"
                                                                                   title="Rend. #{{ $asi['rendicion_id'] ?? '' }} — Σ debe {{ number_format((float) ($asi['total_debe'] ?? 0), 2, ',', '.') }}">
                                                                                    {{ $asi['numeroasiento'] ?? '' }}
                                                                                </a>@if (! $loop->last), @endif
                                                                            @elseif (! empty($asi['asiento_id']))
                                                                                {{ $asi['numeroasiento'] ?? '' }}@if (! $loop->last), @endif
                                                                            @endif
                                                                        @endforeach
                                                                    </small>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="8" class="text-center text-muted py-2">Sin rendiciones en esta jornada.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                                <tfoot class="font-weight-bold bg-light">
                                                    <tr>
                                                        <td>Total jornada (rendiciones)</td>
                                                        <td class="text-center">{{ (int) ($dia['cantidad_rendiciones'] ?? 0) }}</td>
                                                        <td class="text-right">{{ number_format((float) ($dia['total_rendiciones_cobrado'] ?? 0), 2, ',', '.') }}</td>
                                                        <td class="text-right">
                                                            @if ((float) ($dia['total_rendiciones_invitaciones'] ?? 0) > 0)
                                                                {{ number_format((float) $dia['total_rendiciones_invitaciones'], 2, ',', '.') }}
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                        <td class="text-right">{{ number_format((float) ($dia['total_rendiciones_facturacion'] ?? 0), 2, ',', '.') }}</td>
                                                        <td class="text-right">
                                                            @if ((float) ($dia['total_rendiciones_notas_credito'] ?? 0) > 0)
                                                                {{ number_format((float) $dia['total_rendiciones_notas_credito'], 2, ',', '.') }}
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                        <td class="text-right">{{ number_format((float) ($dia['total_rendiciones_ventas_brutas'] ?? 0), 2, ',', '.') }}</td>
                                                        <td class="text-right">{{ number_format((float) ($dia['total_asientos_debe'] ?? 0), 2, ',', '.') }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="7">Flash estacionamiento (&Sigma; flash_estac) — comparable a <em>Fact. neta</em></td>
                                                        <td class="text-right">{{ number_format((float) ($dia['total_flash_estac'] ?? 0), 2, ',', '.') }}</td>
                                                    </tr>
                                                    @if (abs((float) ($dia['diferencia_cobrado_flash'] ?? 0)) > $tol
                                                        && abs($difFlash) <= $tol)
                                                        <tr class="text-muted">
                                                            <td colspan="7">
                                                                Diferencia solo por invitaciones (cobrado − flash)
                                                                <small class="font-weight-normal">— tickets $0,01 sin cobranza</small>
                                                            </td>
                                                            <td class="text-right">{{ number_format((float) ($dia['diferencia_cobrado_flash'] ?? 0), 2, ',', '.') }}</td>
                                                        </tr>
                                                    @endif
                                                    <tr class="{{ $estado === 'DIF' ? 'table-danger' : 'table-success' }}">
                                                        <td colspan="7">Diferencia facturaci&oacute;n neta − flash</td>
                                                        <td class="text-right">{{ number_format($difFlash, 2, ',', '.') }}</td>
                                                    </tr>
                                                    <tr class="{{ abs($difVentaAsientos) > $tol ? 'table-warning' : 'table-success' }}">
                                                        <td colspan="7">
                                                            Diferencia venta total − asientos
                                                            <small class="font-weight-normal">(neta + NC vs &Sigma; debe)</small>
                                                            @if ((int) ($dia['cantidad_pendiente'] ?? 0) > 0)
                                                                <br><small class="text-warning font-weight-normal">{{ (int) $dia['cantidad_pendiente'] }} sin asiento</small>
                                                            @endif
                                                            @if ((int) ($dia['cantidad_legacy'] ?? 0) > 0)
                                                                <br><small class="text-muted font-weight-normal">{{ (int) $dia['cantidad_legacy'] }} hist&oacute;rico(s)</small>
                                                            @endif
                                                        </td>
                                                        <td class="text-right">{{ number_format($difVentaAsientos, 2, ',', '.') }}</td>
                                                    </tr>
                                                    @if ($hayNc && abs((float) ($dia['diferencia_rend_asientos'] ?? 0)) > $tol)
                                                        <tr class="text-muted">
                                                            <td colspan="7">
                                                                Referencia: cobrado − asientos
                                                                <small class="font-weight-normal">— suele diferir por NC; us&aacute; venta total − asientos</small>
                                                            </td>
                                                            <td class="text-right">{{ number_format((float) ($dia['diferencia_rend_asientos'] ?? 0), 2, ',', '.') }}</td>
                                                        </tr>
                                                    @endif
                                                </tfoot>
                                            </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
