@php
    $un = $resultado['conciliacion_contable']['por_unidad_negocio'] ?? ['habilitada' => false];
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $unidades = $un['unidades'] ?? [];
    $totalErp = $un['total_erp'] ?? ['neto_gravado' => 0, 'imp_interno' => 0, 'exento' => 0, 'iva' => 0, 'total' => 0, 'ventas' => 0];
    $totalCtb = $un['total_contable'] ?? ['ventas' => 0, 'iva' => 0];
    $cuadre = $un['cuadre'] ?? [];
    $ctamovHabil = ! empty($un['ctamov_habilitado']);
    $colspanCuadre = $ctamovHabil ? 6 : 4;
    $puedeVerCuenta = $puede_ver_cuenta ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
@endphp
@if (! empty($un['habilitada']) && count($unidades) > 0)
    <div class="px-3 py-3 border-bottom bg-white">
        <h6 class="mb-1">Conciliación por unidad de negocio</h6>
        <p class="small text-muted mb-2">
            Cuadre ERP vs mayor contable por unidad (Gastronomía, Estacionamiento, Máquinas vending y Administración),
            según la PC / punto de venta del comprobante y las cuentas de cada proceso de cierre.
            Bingo y máquinas de juego no emiten FAC de IVA ventas: se controlan en Flash / caja, no en este reporte.
        </p>

        <p class="small font-weight-bold text-muted mb-1">Cuadre por unidad (período)</p>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered mb-0" style="font-size: 0.78rem;">
                <thead>
                    <tr style="background-color: #85C1E9; color: #17202A;">
                        <th rowspan="2">Unidad de negocio</th>
                        <th rowspan="2" class="text-center">Comp.</th>
                        <th colspan="3" class="text-center">Ventas (neto + II + exento)</th>
                        <th colspan="3" class="text-center">IVA</th>
                        <th rowspan="2" class="text-center">Estado</th>
                    </tr>
                    <tr style="background-color: #85C1E9; color: #17202A; font-size: 0.68rem;">
                        <th class="text-right">ERP</th>
                        <th class="text-right">Contable</th>
                        <th class="text-right">Dif.</th>
                        <th class="text-right">ERP</th>
                        <th class="text-right">Contable</th>
                        <th class="text-right">Dif.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($unidades as $unidad)
                        @php
                            $cuadra = ! empty($unidad['cuadra']);
                            $tieneMov = ! empty($unidad['tiene_movimiento']);
                            $erpVentas = (float) ($unidad['erp_ventas'] ?? (
                                (float) ($unidad['neto_gravado'] ?? 0)
                                + (float) ($unidad['imp_interno'] ?? 0)
                                + (float) ($unidad['exento'] ?? 0)
                            ));
                            $ctbVentas = (float) ($unidad['contable']['ventas'] ?? 0);
                            $difVentas = (float) ($unidad['diferencias']['ventas'] ?? ($erpVentas - $ctbVentas));
                            $difIva = (float) ($unidad['diferencias']['iva'] ?? 0);
                        @endphp
                        <tr @if (! $cuadra && $tieneMov) class="table-warning" @elseif (! $tieneMov) class="text-muted" @endif>
                            <td>
                                <strong>{{ $unidad['label'] ?? '' }}</strong>
                                @if (! empty($unidad['cuentas_detalle']))
                                    <div class="small text-muted mt-1">
                                        @foreach ($unidad['cuentas_detalle'] as $c)
                                            @if ($puedeVerCuenta && (int) ($c['id'] ?? 0) > 0)
                                                <a href="{{ route('editar_cuentacontable', array_merge(['id' => $c['id']], $queryConsulta)) }}"
                                                   target="_blank" rel="noopener" class="badge badge-light border mr-1 text-primary">
                                                    {{ $c['codigo'] ?? '' }}
                                                </a>
                                            @else
                                                <span class="badge badge-light border mr-1">{{ $c['codigo'] ?? '' }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">{{ (int) ($unidad['cantidad'] ?? 0) }}</td>
                            <td class="text-right">{{ $formatear($erpVentas) }}</td>
                            <td class="text-right">{{ $formatear($ctbVentas) }}</td>
                            <td class="text-right font-weight-bold">{{ $formatear($difVentas) }}</td>
                            <td class="text-right">{{ $formatear($unidad['iva'] ?? 0) }}</td>
                            <td class="text-right">{{ $formatear($unidad['contable']['iva'] ?? 0) }}</td>
                            <td class="text-right font-weight-bold">{{ $formatear($difIva) }}</td>
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
                    @endforeach
                </tbody>
                <tfoot>
                    @php
                        $totErpVentas = (float) ($totalErp['ventas'] ?? (
                            (float) ($totalErp['neto_gravado'] ?? 0)
                            + (float) ($totalErp['imp_interno'] ?? 0)
                            + (float) ($totalErp['exento'] ?? 0)
                        ));
                        $totCtbVentas = (float) ($totalCtb['ventas'] ?? 0);
                        $totDifVentas = round($totErpVentas - $totCtbVentas, 2);
                        $totDifIva = round((float) ($totalErp['iva'] ?? 0) - (float) ($totalCtb['iva'] ?? 0), 2);
                    @endphp
                    <tr style="background-color: #f2f4f4; font-weight: bold;">
                        <td>TOTAL</td>
                        <td></td>
                        <td class="text-right">{{ $formatear($totErpVentas) }}</td>
                        <td class="text-right">{{ $formatear($totCtbVentas) }}</td>
                        <td class="text-right">{{ $formatear($totDifVentas) }}</td>
                        <td class="text-right">{{ $formatear($totalErp['iva'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatear($totalCtb['iva'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatear($totDifIva) }}</td>
                        <td class="text-center">
                            @if (abs($totDifVentas) <= 1 && abs($totDifIva) <= 1)
                                <i class="fa fa-check text-success" title="Cuadra"></i>
                            @else
                                <i class="fa fa-exclamation-triangle text-warning" title="Diferencia"></i>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="small font-weight-bold text-muted mb-1">Detalle ERP por unidad (desglose)</p>
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
                        <tr @if (empty($unidad['tiene_movimiento'])) class="text-muted" @endif>
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

        <p class="small font-weight-bold text-muted mb-1">Cuadre del total contra la cuenta de ventas (por unidad)</p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size: 0.78rem;">
                <thead>
                    <tr style="background-color: #85C1E9; color: #17202A;">
                        <th>Concepto</th>
                        <th class="text-right">ERP</th>
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
                    @foreach ($unidades as $unidad)
                        @php
                            $cuadraU = ! empty($unidad['cuadra']);
                            $tieneMovU = ! empty($unidad['tiene_movimiento']);
                            $erpVU = (float) ($unidad['erp_ventas'] ?? 0);
                            $ctbVU = (float) ($unidad['contable']['ventas'] ?? 0);
                            $difVU = (float) ($unidad['diferencias']['ventas'] ?? ($erpVU - $ctbVU));
                            $erpIU = (float) ($unidad['iva'] ?? 0);
                            $ctbIU = (float) ($unidad['contable']['iva'] ?? 0);
                            $difIU = (float) ($unidad['diferencias']['iva'] ?? ($erpIU - $ctbIU));
                        @endphp
                        <tr @if (! $cuadraU && $tieneMovU) class="table-warning" @elseif (! $tieneMovU) class="text-muted" @endif>
                            <td><strong>{{ $unidad['label'] ?? '' }}</strong> — ventas</td>
                            <td class="text-right">{{ $formatear($erpVU) }}</td>
                            <td class="text-right">{{ $formatear($ctbVU) }}</td>
                            <td class="text-right font-weight-bold">{{ $formatear($difVU) }}</td>
                            @if ($ctamovHabil)
                                <td class="text-right text-muted">—</td>
                                <td class="text-right text-muted">—</td>
                            @endif
                            <td class="text-center">
                                @if (! $tieneMovU)
                                    <span class="text-muted">—</span>
                                @elseif ($cuadraU)
                                    <i class="fa fa-check text-success" title="Cuadra"></i>
                                @else
                                    <i class="fa fa-exclamation-triangle text-warning" title="Diferencia"></i>
                                @endif
                            </td>
                        </tr>
                        <tr @if (! $cuadraU && $tieneMovU) class="table-warning" @elseif (! $tieneMovU) class="text-muted" @endif>
                            <td class="pl-3">{{ $unidad['label'] ?? '' }} — IVA</td>
                            <td class="text-right">{{ $formatear($erpIU) }}</td>
                            <td class="text-right">{{ $formatear($ctbIU) }}</td>
                            <td class="text-right font-weight-bold">{{ $formatear($difIU) }}</td>
                            @if ($ctamovHabil)
                                <td class="text-right text-muted">—</td>
                                <td class="text-right text-muted">—</td>
                            @endif
                            <td class="text-center">
                                @if (! $tieneMovU)
                                    <span class="text-muted">—</span>
                                @elseif (abs($difIU) <= 1)
                                    <i class="fa fa-check text-success" title="Cuadra"></i>
                                @else
                                    <i class="fa fa-exclamation-triangle text-warning" title="Diferencia"></i>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @foreach (['ventas', 'iva'] as $clave)
                        @php $linea = $cuadre[$clave] ?? null; @endphp
                        @if ($linea)
                            <tr class="font-weight-bold @if (empty($linea['cuadra'])) table-warning @endif" style="background-color: #f2f4f4;">
                                <td>TOTAL {{ $linea['concepto'] ?? '' }}</td>
                                <td class="text-right">{{ $formatear($linea['erp'] ?? 0) }}</td>
                                <td class="text-right">{{ $formatear($linea['contable'] ?? 0) }}</td>
                                <td class="text-right">{{ $formatear($linea['dif_contable'] ?? 0) }}</td>
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
                            Desglose por unidad + total del período. Bingo / máquinas de juego no emiten FAC de IVA ventas.
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif
