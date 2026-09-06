@php
    $formatearMonto = static function ($valor) {
        if ($valor === null || $valor === '' || (float) $valor === 0.0) {
            return '';
        }

        return number_format((float) $valor, 2, ',', '.');
    };
    $panel = $auditoria_panel ?? null;
    $conc = $panel['conciliacion'] ?? null;
    $filasDescuadradas = $conc['filas_descuadradas'] ?? [];
    $filasCuadradas = $conc['filas_cuadradas'] ?? [];
    $tieneConciliacion = ! empty($conc) && (int) ($conc['asientos_analizados'] ?? 0) > 0;
    $asientosCuadrados = (int) ($conc['asientos_cuadrados'] ?? 0);
    $asientosDescuadrados = (int) ($conc['asientos_descuadrados'] ?? 0);
    $asientosAnalizados = (int) ($conc['asientos_analizados'] ?? 0);
    $todoCuadra = ! empty($panel['cuadra']);
@endphp
@if ($tieneConciliacion)
    <div class="px-3 py-2 border-bottom">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#panel-conciliacion-mayor-concepto" aria-expanded="false">
                <i class="fa fa-chevron-right"></i> Conciliación analítico vs concepto
            </button>
            <div class="d-flex flex-wrap align-items-center mt-1 mt-md-0">
                @if ($todoCuadra)
                    <span class="badge badge-success mr-1">
                        {{ number_format($asientosCuadrados, 0, ',', '.') }} asiento{{ $asientosCuadrados === 1 ? '' : 's' }} balanceado{{ $asientosCuadrados === 1 ? '' : 's' }}
                    </span>
                @else
                    <span class="badge badge-success mr-1">
                        {{ number_format($asientosCuadrados, 0, ',', '.') }} balanceado{{ $asientosCuadrados === 1 ? '' : 's' }}
                    </span>
                    <span class="badge badge-danger mr-1">
                        {{ number_format($asientosDescuadrados, 0, ',', '.') }} descuadrado{{ $asientosDescuadrados === 1 ? '' : 's' }}
                    </span>
                @endif
                <span class="badge badge-secondary">
                    {{ number_format($asientosAnalizados, 0, ',', '.') }} analizados
                    ({{ number_format((float) ($conc['porcentaje_cuadrado'] ?? 0), 1, ',', '.') }}%)
                </span>
            </div>
        </div>

        <div class="collapse" id="panel-conciliacion-mayor-concepto">
            <p class="small text-muted mb-2">
                <strong>Regla:</strong> {{ $conc['regla'] ?? 'Neto analítico + Neto concepto = 0' }} por asiento.
                Tolerancia ±{{ number_format((float) ($conc['tolerancia'] ?? 1), 2, ',', '.') }}.
                Excluye asiento 0 (remanente mayor plano).
            </p>

            @if ($todoCuadra)
                <p class="small text-success mb-2">
                    <i class="fa fa-check-circle"></i>
                    Los {{ number_format($asientosCuadrados, 0, ',', '.') }} asientos del período están balanceados entre mayor analítico y mayor por concepto.
                </p>
            @else
                <h6 class="font-weight-bold mb-2 text-danger">
                    Descuadrados ({{ count($filasDescuadradas) }} asiento{{ count($filasDescuadradas) === 1 ? '' : 's' }})
                </h6>
                <div class="table-responsive mb-2">
                    <table class="table table-sm table-bordered mb-0" id="tabla-conciliacion-descuadres" style="font-size: 0.75rem;">
                        <thead>
                            <tr style="background-color: #85C1E9; color: #17202A;">
                                @if (! empty($conc['multiempresa']))
                                    <th>Empresa</th>
                                @endif
                                <th>N. asiento</th>
                                <th>Fecha</th>
                                <th class="text-right">Neto analítico</th>
                                <th class="text-right">Neto concepto</th>
                                <th class="text-right">Diferencia</th>
                                <th>Origen</th>
                                <th>Cuentas analítico</th>
                                <th>Cuentas concepto</th>
                                <th class="text-center">Ver</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filasDescuadradas as $fila)
                                <tr class="table-warning">
                                    @if (! empty($conc['multiempresa']))
                                        <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                                    @endif
                                    <td class="font-weight-bold">{{ $fila['nro_asiento'] ?? '' }}</td>
                                    <td>{{ $fila['fecha_fmt'] ?? '' }}</td>
                                    <td class="text-right">{{ $formatearMonto($fila['neto_analitico'] ?? null) }}</td>
                                    <td class="text-right">{{ $formatearMonto($fila['neto_concepto'] ?? null) }}</td>
                                    <td class="text-right font-weight-bold">{{ $formatearMonto($fila['diferencia'] ?? null) }}</td>
                                    <td>
                                        {{ $fila['origen'] ?? '' }}
                                        @if (! empty($fila['motivo']))
                                            <div class="text-danger mt-1">
                                                <i class="fa fa-info-circle"></i> {{ $fila['motivo'] }}
                                            </div>
                                        @endif
                                    </td>
                                    <td><small>{{ $fila['cuentas_analitico'] ?? '' }}</small></td>
                                    <td><small>{{ $fila['cuentas_concepto'] ?? '' }}</small></td>
                                    <td class="text-center text-nowrap">
                                        @if (($puede_ver_asiento ?? false) && (int) ($fila['asiento_id'] ?? 0) > 0)
                                            <a href="{{ route('editar_asiento', ['id' => $fila['asiento_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                               class="btn btn-info btn-xs" target="_blank" rel="noopener" title="Consultar asiento en ERP">
                                                <i class="fa fa-external-link"></i> Asiento
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($asientosCuadrados > 0)
                <button type="button" class="btn btn-outline-success btn-sm mb-2" data-toggle="collapse" data-target="#panel-conciliacion-cuadrados" aria-expanded="false">
                    <i class="fa fa-check"></i>
                    Ver asientos balanceados ({{ number_format($asientosCuadrados, 0, ',', '.') }})
                    @if (! empty($conc['filas_cuadradas_recortadas']))
                        <span class="text-muted">— muestra parcial</span>
                    @endif
                </button>
                <div class="collapse" id="panel-conciliacion-cuadrados">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" style="font-size: 0.72rem;">
                            <thead>
                                <tr style="background-color: #d5f5e3; color: #17202A;">
                                    @if (! empty($conc['multiempresa']))
                                        <th>Empresa</th>
                                    @endif
                                    <th>N. asiento</th>
                                    <th>Fecha</th>
                                    <th class="text-right">Debe anal.</th>
                                    <th class="text-right">Haber anal.</th>
                                    <th class="text-right">Debe conc.</th>
                                    <th class="text-right">Haber conc.</th>
                                    <th class="text-right">Neto anal.</th>
                                    <th class="text-right">Neto conc.</th>
                                    <th class="text-center">Ver</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filasCuadradas as $fila)
                                    <tr>
                                        @if (! empty($conc['multiempresa']))
                                            <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                                        @endif
                                        <td>{{ $fila['nro_asiento'] ?? '' }}</td>
                                        <td>{{ $fila['fecha_fmt'] ?? '' }}</td>
                                        <td class="text-right">{{ $formatearMonto($fila['debe_analitico'] ?? null) }}</td>
                                        <td class="text-right">{{ $formatearMonto($fila['haber_analitico'] ?? null) }}</td>
                                        <td class="text-right">{{ $formatearMonto($fila['debe_concepto'] ?? null) }}</td>
                                        <td class="text-right">{{ $formatearMonto($fila['haber_concepto'] ?? null) }}</td>
                                        <td class="text-right">{{ $formatearMonto($fila['neto_analitico'] ?? null) }}</td>
                                        <td class="text-right">{{ $formatearMonto($fila['neto_concepto'] ?? null) }}</td>
                                        <td class="text-center">
                                            @if (($puede_ver_asiento ?? false) && (int) ($fila['asiento_id'] ?? 0) > 0)
                                                <a href="{{ route('editar_asiento', ['id' => $fila['asiento_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                                   class="text-primary" target="_blank" rel="noopener" title="Consultar asiento">
                                                    <i class="fa fa-external-link"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
    <script>
        (function () {
            if (typeof jQuery === 'undefined') {
                return;
            }
            var $panel = jQuery('#panel-conciliacion-mayor-concepto');
            var $btn = jQuery('[data-target="#panel-conciliacion-mayor-concepto"]');
            if ($panel.length === 0 || $btn.length === 0) {
                return;
            }
            $panel.on('show.bs.collapse', function () {
                $btn.find('i').removeClass('fa-chevron-right').addClass('fa-chevron-down');
            }).on('hide.bs.collapse', function () {
                $btn.find('i').removeClass('fa-chevron-down').addClass('fa-chevron-right');
            });
        })();
    </script>
@endif
