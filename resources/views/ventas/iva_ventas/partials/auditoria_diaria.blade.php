@php
    $aud = $resultado['conciliacion_contable']['auditoria_diaria'] ?? ['habilitada' => false];
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $dias = $aud['dias'] ?? [];
    $stats = $aud['stats'] ?? [];
    $tolerancia = (float) ($aud['tolerancia'] ?? 1);
    $diasConDif = (int) ($stats['dias_con_diferencia'] ?? 0);
    $ctamovHabilitado = ! empty($aud['ctamov_habilitado']);
    $colspanFoot = $ctamovHabilitado ? 11 : 9;
@endphp
@if (! empty($aud['habilitada']) && count($dias) > 0)
    <div class="px-3 py-3 border-bottom bg-white">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
            <h6 class="mb-1">Auditoría día por día (facturación vs contable)</h6>
            @if ($diasConDif === 0)
                <span class="badge badge-success">Todos los días cuadran (± {{ $formatear($tolerancia) }})</span>
            @else
                <span class="badge badge-warning">{{ $diasConDif }} día(s) con diferencias</span>
            @endif
        </div>
        <p class="small text-muted mb-2">
            Comparación diaria de ventas (neto gravado + imp. interno + exento) e IVA entre el listado IVA ventas y el mayor contable (cuentas 413/414).
            Tolerancia diaria: <strong>{{ $formatear($tolerancia) }}</strong> (más amplia que el cuadre global de {{ number_format(\App\Support\Ventas\IvaVentas\IvaVentasConciliacionCuentaSupport::TOLERANCIA_DEFAULT, 2, ',', '.') }}).
            Útil para ubicar el día donde se desvía la contabilización respecto a la facturación.
        </p>

        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size: 0.75rem;" id="tabla-auditoria-diaria">
                <thead>
                    <tr style="background-color: #85C1E9; color: #17202A;">
                        <th>Día</th>
                        <th class="text-center">Comp.</th>
                        <th class="text-right" title="Neto gravado + imp. interno + exento">ERP ventas</th>
                        <th class="text-right" title="Cuentas 413 + 414 (haber)">Ctb. ventas</th>
                        <th class="text-right">Dif. ventas</th>
                        <th class="text-right">ERP IVA</th>
                        <th class="text-right">Ctb. IVA</th>
                        <th class="text-right">Dif. IVA</th>
                        @if ($ctamovHabilitado)
                            <th class="text-right" title="ctamov Anita — ventas (haber)">Ctamov vtas</th>
                            <th class="text-right" title="ctamov Anita — IVA (débito − crédito)">Ctamov IVA</th>
                        @endif
                        <th class="text-center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dias as $dia)
                        @php
                            $cuadra = ! empty($dia['cuadra']);
                            $tieneMov = ! empty($dia['tiene_movimiento']);
                            $ocultar = ! $tieneMov && $cuadra;
                        @endphp
                        @if (! $ocultar)
                            <tr @if (! $cuadra && $tieneMov) class="table-warning" @elseif (! $tieneMov) class="text-muted" @endif>
                                <td>{{ $dia['dia_texto'] ?? '' }}</td>
                                <td class="text-center">{{ (int) ($dia['comprobantes'] ?? 0) }}</td>
                                <td class="text-right">{{ $formatear($dia['erp']['ventas'] ?? (($dia['erp']['neto_gravado'] ?? 0) + ($dia['erp']['imp_interno'] ?? 0) + ($dia['erp']['exento'] ?? 0))) }}</td>
                                <td class="text-right">{{ $formatear($dia['contable']['ventas'] ?? ($dia['contable']['ventas_total'] ?? 0)) }}</td>
                                <td class="text-right font-weight-bold">{{ $formatear($dia['diferencias']['ventas'] ?? ($dia['diferencias']['neto_gravado'] ?? 0)) }}</td>
                                <td class="text-right">{{ $formatear($dia['erp']['iva'] ?? 0) }}</td>
                                <td class="text-right">{{ $formatear($dia['contable']['iva'] ?? 0) }}</td>
                                <td class="text-right font-weight-bold">{{ $formatear($dia['diferencias']['iva'] ?? 0) }}</td>
                                @if ($ctamovHabilitado)
                                    <td class="text-right">{{ $formatear($dia['ctamov']['ventas'] ?? 0) }}</td>
                                    <td class="text-right">{{ $formatear($dia['ctamov']['iva'] ?? 0) }}</td>
                                @endif
                                <td class="text-center">
                                    @if (! $tieneMov)
                                        <span class="text-muted" title="Sin movimiento">—</span>
                                    @elseif ($cuadra)
                                        <i class="fa fa-check text-success" title="Cuadra"></i>
                                    @else
                                        <i class="fa fa-exclamation-triangle text-warning" title="Diferencia"></i>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="small text-muted">
                        <td colspan="{{ $colspanFoot }}">
                            {{ (int) ($stats['total_dias'] ?? 0) }} días en período
                            · {{ (int) ($stats['dias_con_movimiento'] ?? 0) }} con movimiento
                            · {{ (int) ($stats['dias_cuadran'] ?? 0) }} cuadran
                            · {{ $diasConDif }} con diferencia
                            <span class="text-muted">(días sin movimiento ocultos)</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif
