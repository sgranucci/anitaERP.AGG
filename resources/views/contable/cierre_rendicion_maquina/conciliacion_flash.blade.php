@extends("theme.$theme.layout")
@section('titulo')
    Conciliaci&oacute;n flash m&aacute;quinas
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
@if (can('ejecutar-cierre-rendicion-maquina-contable', false))
<script>
    window.CIERRE_REND_MAQ_CONC = {
        urlEjecutarJornada: @json(route('api_cierre_rendicion_maquina_ejecutar')),
        urlEjecutarPeriodo: @json(route('api_cierre_rendicion_maquina_ejecutar_rango')),
    };
</script>
<script src="{{ asset('assets/pages/scripts/contable/cierre_rendicion_maquina/conciliacion_flash.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cierre_rendicion_maquina/conciliacion_flash.js')) ?: time() }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
@php
    $tolConfig = (float) config('rendicion_maquina_anita.cierre_rendicion_contable.conciliacion_flash_tolerancia', 0.02);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Conciliaci&oacute;n rendiciones m&aacute;quinas vs flash (slot + ruleta)</h3>
                <div class="card-tools ml-auto">
                    <a href="{{ route('cierre_rendicion_maquina_contable', $retornoListadoQuery ?? []) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info py-2 small mb-3">
                    Compara <strong>win_ol_slot + win_ol_rul</strong> del m&oacute;dulo flash ERP con la recaudaci&oacute;n online de rendiciones turno C.
                    El tilde verde a la derecha del monto indica que el flash de esa jornada fue validado.
                    Tolerancia: {{ number_format($tolConfig, 2, ',', '.') }}.
                </div>
                <form method="get" action="{{ route('cierre_rendicion_maquina_conciliacion_flash') }}" class="mb-4">
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
                        $dias = $resultado['dias'] ?? [];
                    @endphp
                    <div class="d-flex flex-wrap align-items-start justify-content-between mb-3">
                        <div>
                            <strong>{{ $resultado['empresa_nombre'] ?? '' }}</strong>
                            — {{ \Carbon\Carbon::parse($resultado['fecha_desde'])->format('d/m/Y') }}
                            al {{ \Carbon\Carbon::parse($resultado['fecha_hasta'])->format('d/m/Y') }}
                            <br>
                            <span class="text-muted">
                                {{ (int) ($resumen['total_dias'] ?? 0) }} jornada(s) —
                                {{ (int) ($resumen['dias_ok'] ?? 0) }} OK,
                                {{ (int) ($resumen['dias_dif'] ?? 0) }} con diferencia
                                @if ((int) ($resumen['total_grupos_pendientes'] ?? 0) > 0)
                                    — <span class="text-warning font-weight-bold">
                                        {{ (int) $resumen['total_pendiente_cierre'] }} rend. pendiente(s)
                                    </span>
                                @endif
                            </span>
                        </div>
                        <div class="d-flex flex-wrap align-items-center">
                            @if (can('exportar-cierre-rendicion-maquina-contable', false))
                                <div class="mr-2 mb-1">
                                    @include('includes.exportar-tabla-queryparams', [
                                        'ruta' => 'listar_cierre_rendicion_maquina_conciliacion_flash',
                                        'queryparams' => $filtrosQueryConciliacion ?? [],
                                    ])
                                </div>
                            @endif
                            @if (can('ejecutar-cierre-rendicion-maquina-contable', false)
                                && (int) ($resumen['total_grupos_pendientes'] ?? 0) > 0)
                                <button type="button"
                                        class="btn btn-success btn-sm mb-1"
                                        id="btn-cerrar-periodo-conc"
                                        data-empresa-id="{{ (int) ($resultado['empresa_id'] ?? 0) }}"
                                        data-fecha-desde="{{ $resultado['fecha_desde'] ?? '' }}"
                                        data-fecha-hasta="{{ $resultado['fecha_hasta'] ?? '' }}"
                                        data-grupos="{{ (int) ($resumen['total_grupos_pendientes'] ?? 0) }}"
                                        data-pendientes="{{ (int) ($resumen['total_pendiente_cierre'] ?? 0) }}"
                                        title="Generar cierres pendientes del periodo">
                                    <i class="fa fa-lock"></i> Cerrar periodo completo
                                </button>
                            @endif
                        </div>
                    </div>

                    @if (empty($dias))
                        <p class="text-muted text-center py-4">Sin rendiciones ni flash en el rango indicado.</p>
                    @else
                        <div class="table-responsive">
                            <table id="tabla-paginada" class="table table-bordered table-hover mb-0" style="font-size: 0.9rem;">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Jornada</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Rend.</th>
                                        <th class="text-right">Flash total</th>
                                        <th class="text-right">Flash slot</th>
                                        <th class="text-right">Flash ruleta</th>
                                        <th class="text-right">Rend. online</th>
                                        <th class="text-right">Rend. real</th>
                                        <th class="text-right">Flash&minus;Rend.</th>
                                        <th class="text-right">Real&minus;Online</th>
                                        <th class="text-center">Cierre</th>
                                        <th class="text-center" style="width: 7rem;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($dias as $dia)
                                        @php
                                            $estado = (string) ($dia['estado'] ?? '');
                                            $badgeClass = match ($estado) {
                                                'OK' => 'badge-success',
                                                'DIF' => 'badge-danger',
                                                default => 'badge-secondary',
                                            };
                                            $difFlash = (float) ($dia['diferencia_flash_rendicion'] ?? 0);
                                            $difReal = (float) ($dia['diferencia_real_online'] ?? 0);
                                            $filaClase = $estado === 'DIF' ? 'table-danger' : '';
                                        @endphp
                                        <tr class="{{ $filaClase }}">
                                            <td>
                                                <strong>{{ $dia['fecha_fmt'] ?? '' }}</strong>
                                                @if ((int) ($dia['cantidad_pendiente'] ?? 0) > 0)
                                                    <br><small class="text-warning">{{ (int) $dia['cantidad_pendiente'] }} sin cierre</small>
                                                @endif
                                            </td>
                                            <td class="text-center"><span class="badge {{ $badgeClass }}">{{ $estado !== '' ? $estado : '—' }}</span></td>
                                            <td class="text-center">{{ (int) ($dia['cantidad_rendiciones'] ?? 0) }}</td>
                                            <td class="text-right">
                                                {{ number_format((float) ($dia['total_flash'] ?? 0), 2, ',', '.') }}
                                                @include('caja.flash.partials.tilde_validado', ['validado' => ! empty($dia['flash_validado'])])
                                            </td>
                                            <td class="text-right">
                                                {{ number_format((float) ($dia['flash_slot'] ?? 0), 2, ',', '.') }}
                                                @include('caja.flash.partials.tilde_validado', ['validado' => ! empty($dia['flash_validado'])])
                                            </td>
                                            <td class="text-right">
                                                {{ number_format((float) ($dia['flash_ruleta'] ?? 0), 2, ',', '.') }}
                                                @include('caja.flash.partials.tilde_validado', ['validado' => ! empty($dia['flash_validado'])])
                                            </td>
                                            <td class="text-right">{{ number_format((float) ($dia['rendicion_online'] ?? 0), 2, ',', '.') }}</td>
                                            <td class="text-right">{{ number_format((float) ($dia['rendicion_real'] ?? 0), 2, ',', '.') }}</td>
                                            <td class="text-right {{ abs($difFlash) > $tol ? 'text-danger font-weight-bold' : 'text-muted' }}">
                                                {{ number_format($difFlash, 2, ',', '.') }}
                                            </td>
                                            <td class="text-right {{ abs($difReal) > $tol ? 'text-danger font-weight-bold' : 'text-muted' }}">
                                                {{ number_format($difReal, 2, ',', '.') }}
                                            </td>
                                            <td class="text-center">
                                                @php $ec = (string) ($dia['estado_cierre'] ?? ''); @endphp
                                                @if ($ec === 'cerrada')
                                                    <span class="badge badge-success">Cerrado</span>
                                                @elseif ($ec === 'parcial')
                                                    <span class="badge badge-warning">Parcial</span>
                                                @else
                                                    <span class="badge badge-warning">Pendiente</span>
                                                @endif
                                            </td>
                                            <td class="text-center p-1">
                                                @if (can('ejecutar-cierre-rendicion-maquina-contable', false)
                                                    && (int) ($dia['cantidad_grupos_pendientes'] ?? 0) > 0)
                                                    <button type="button"
                                                            class="btn btn-success btn-sm js-cerrar-jornada-conc"
                                                            data-empresa-id="{{ (int) ($resultado['empresa_id'] ?? 0) }}"
                                                            data-fecha-dia="{{ $dia['fecha'] ?? '' }}"
                                                            data-fecha-fmt="{{ $dia['fecha_fmt'] ?? '' }}"
                                                            data-grupos="{{ (int) ($dia['cantidad_grupos_pendientes'] ?? 0) }}"
                                                            data-pendientes="{{ (int) ($dia['cantidad_pendiente'] ?? 0) }}">
                                                        <i class="fa fa-lock"></i> Cerrar
                                                    </button>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
