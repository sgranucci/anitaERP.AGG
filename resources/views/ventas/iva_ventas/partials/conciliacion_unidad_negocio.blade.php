@php
    $un = $resultado['conciliacion_contable']['por_unidad_negocio'] ?? ['habilitada' => false];
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $unidades = $un['unidades'] ?? [];
    $totalErp = $un['total_erp'] ?? ['neto_gravado' => 0, 'imp_interno' => 0, 'exento' => 0, 'iva' => 0, 'total' => 0];
    $cuadre = $un['cuadre'] ?? [];
    $ctamovHabil = ! empty($un['ctamov_habilitado']);
    $colspanCuadre = $ctamovHabil ? 6 : 4;
@endphp
@if (! empty($un['habilitada']) && count($unidades) > 0)
    <div class="px-3 py-3 border-bottom bg-white">
        <h6 class="mb-1">Conciliación por unidad de negocio</h6>
        <p class="small text-muted mb-2">
            Ventas del ERP agrupadas por unidad de negocio (según la PC / punto de venta del comprobante) y cuadre
            del total contra la cuenta de ventas contable{{ $ctamovHabil ? ' y ctamov (Anita)' : '' }}.
        </p>

        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered mb-0" style="font-size: 0.78rem;">
                <thead>
                    <tr style="background-color: #85C1E9; color: #17202A;">
                        <th>Unidad de negocio</th>
                        <th class="text-center">Comp.</th>
                        <th class="text-right">Neto gravado</th>
                        <th class="text-right">Imp. interno / kiosco</th>
                        <th class="text-right">Exento</th>
                        <th class="text-right">IVA</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($unidades as $unidad)
                        <tr>
                            <td>{{ $unidad['label'] ?? '' }}</td>
                            <td class="text-center">{{ (int) ($unidad['cantidad'] ?? 0) }}</td>
                            <td class="text-right">{{ $formatear($unidad['neto_gravado'] ?? 0) }}</td>
                            <td class="text-right">{{ $formatear($unidad['imp_interno'] ?? 0) }}</td>
                            <td class="text-right">{{ $formatear($unidad['exento'] ?? 0) }}</td>
                            <td class="text-right">{{ $formatear($unidad['iva'] ?? 0) }}</td>
                            <td class="text-right">{{ $formatear($unidad['total'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background-color: #f2f4f4; font-weight: bold;">
                        <td>TOTAL</td>
                        <td></td>
                        <td class="text-right">{{ $formatear($totalErp['neto_gravado'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatear($totalErp['imp_interno'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatear($totalErp['exento'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatear($totalErp['iva'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatear($totalErp['total'] ?? 0) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="small font-weight-bold text-muted mb-1">Cuadre del total contra la cuenta de ventas</p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size: 0.78rem;">
                <thead>
                    <tr style="background-color: #85C1E9; color: #17202A;">
                        <th>Concepto</th>
                        <th class="text-right">ERP (todas las unidades)</th>
                        <th class="text-right">Contable</th>
                        <th class="text-right">Dif. contable</th>
                        @if ($ctamovHabil)
                            <th class="text-right">ctamov (Anita)</th>
                            <th class="text-right">Dif. ctamov</th>
                        @endif
                        <th class="text-center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (['ventas', 'iva'] as $clave)
                        @php $linea = $cuadre[$clave] ?? null; @endphp
                        @if ($linea)
                            <tr @if (empty($linea['cuadra'])) class="table-warning" @endif>
                                <td>{{ $linea['concepto'] ?? '' }}</td>
                                <td class="text-right">{{ $formatear($linea['erp'] ?? 0) }}</td>
                                <td class="text-right">{{ $formatear($linea['contable'] ?? 0) }}</td>
                                <td class="text-right font-weight-bold">{{ $formatear($linea['dif_contable'] ?? 0) }}</td>
                                @if ($ctamovHabil)
                                    <td class="text-right">{{ $formatear($linea['ctamov'] ?? 0) }}</td>
                                    <td class="text-right">{{ $formatear($linea['dif_ctamov'] ?? 0) }}</td>
                                @endif
                                <td class="text-center">
                                    @if (! empty($linea['cuadra']))
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
                        <td colspan="{{ $colspanCuadre + 1 }}">
                            El desglose por unidad es informativo (ventas del ERP). El cuadre valida que el total de todas
                            las unidades coincida con la cuenta de ventas contable.
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif
