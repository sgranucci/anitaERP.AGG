@php
    $aud = $resultado['conciliacion_contable']['auditoria_diaria'] ?? ['habilitada' => false];
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $dias = $aud['dias'] ?? [];
    $stats = $aud['stats'] ?? [];
    $tolerancia = (float) ($aud['tolerancia'] ?? 1);
    $diasConDif = (int) ($stats['dias_con_diferencia'] ?? 0);
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
            Comparación diaria de neto gravado, imp. interno e IVA entre el listado IVA ventas y el mayor contable.
            Tolerancia diaria: <strong>{{ $formatear($tolerancia) }}</strong> (más amplia que el cuadre global de {{ number_format(\App\Support\Ventas\IvaVentas\IvaVentasConciliacionCuentaSupport::TOLERANCIA_DEFAULT, 2, ',', '.') }}).
            Útil para ubicar el día donde se desvía la contabilización respecto a la facturación.
        </p>

        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size: 0.75rem;" id="tabla-auditoria-diaria">
                <thead>
                    <tr style="background-color: #85C1E9; color: #17202A;">
                        <th>Día</th>
                        <th class="text-center">Comp.</th>
                        <th class="text-right">ERP neto</th>
                        <th class="text-right">Ctb. neto</th>
                        <th class="text-right">Dif. neto</th>
                        <th class="text-right">ERP IVA</th>
                        <th class="text-right">Ctb. IVA</th>
                        <th class="text-right">Dif. IVA</th>
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
                                <td class="text-right">{{ $formatear($dia['erp']['neto_gravado'] ?? 0) }}</td>
                                <td class="text-right">{{ $formatear($dia['contable']['ventas_gravadas'] ?? 0) }}</td>
                                <td class="text-right font-weight-bold">{{ $formatear($dia['diferencias']['neto_gravado'] ?? 0) }}</td>
                                <td class="text-right">{{ $formatear($dia['erp']['iva'] ?? 0) }}</td>
                                <td class="text-right">{{ $formatear($dia['contable']['iva'] ?? 0) }}</td>
                                <td class="text-right font-weight-bold">{{ $formatear($dia['diferencias']['iva'] ?? 0) }}</td>
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
                        <td colspan="9">
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
