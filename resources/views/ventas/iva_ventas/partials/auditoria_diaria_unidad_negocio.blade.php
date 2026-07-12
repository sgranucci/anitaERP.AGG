@php
    $audUn = $resultado['conciliacion_contable']['auditoria_diaria_unidad'] ?? ['habilitada' => false];
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $unidades = $audUn['unidades'] ?? [];
    $tolerancia = (float) ($audUn['tolerancia'] ?? 1);
    $puedeVerCuenta = $puede_ver_cuenta ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
@endphp
@if (! empty($audUn['habilitada']) && count($unidades) > 0)
    <div class="px-3 py-3 border-bottom bg-white">
        <h6 class="mb-1">Auditoría día por día por unidad de negocio</h6>
        <p class="small text-muted mb-3">
            Cuadre diario de ventas (neto gravado + imp. interno + exento), IVA y total entre el listado IVA ventas y el mayor contable,
            usando las cuentas de imputación de cada proceso (gastronomía, estacionamiento, vending, administración).
            Tolerancia: <strong>{{ $formatear($tolerancia) }}</strong>.
            En estacionamiento y gastronomía el contable aparece el día del cierre de rendición / jornada (p. ej. si solo está cerrado el 01/07, los demás días muestran facturación ERP sin asiento aún).
        </p>

        <div class="accordion" id="accordion-auditoria-unidad-dia">
            @foreach ($unidades as $idx => $unidad)
                @php
                    $stats = $unidad['stats'] ?? [];
                    $diasConDif = (int) ($stats['dias_con_diferencia'] ?? 0);
                    $cuentasDet = $unidad['cuentas_detalle'] ?? [];
                @endphp
                <div class="card card-outline card-secondary mb-1">
                    <div class="card-header p-2" id="heading-aud-un-{{ $idx }}">
                        <button class="btn btn-link btn-sm text-left w-100 collapsed d-flex justify-content-between align-items-center"
                            type="button" data-toggle="collapse" data-target="#collapse-aud-un-{{ $idx }}"
                            aria-expanded="false" aria-controls="collapse-aud-un-{{ $idx }}">
                            <span>
                                <strong>{{ $unidad['label'] ?? '' }}</strong>
                                @if ($diasConDif === 0)
                                    <span class="badge badge-success ml-1">Cuadra</span>
                                @else
                                    <span class="badge badge-warning ml-1">{{ $diasConDif }} día(s) con dif.</span>
                                @endif
                            </span>
                            <span class="text-muted small">
                                {{ (int) ($stats['dias_con_movimiento'] ?? 0) }} día(s) con movimiento
                            </span>
                        </button>
                    </div>
                    <div id="collapse-aud-un-{{ $idx }}" class="collapse" data-parent="#accordion-auditoria-unidad-dia">
                        <div class="card-body p-2">
                            @if (count($cuentasDet) > 0)
                                <p class="small text-muted mb-2">
                                    Cuentas ventas / IVA:
                                    @foreach ($cuentasDet as $c)
                                        @if ($puedeVerCuenta && (int) ($c['id'] ?? 0) > 0)
                                            <a href="{{ route('editar_cuentacontable', array_merge(['id' => $c['id']], $queryConsulta)) }}"
                                               target="_blank" rel="noopener" class="badge badge-light border mr-1 text-primary">
                                                {{ $c['codigo'] ?? '' }}
                                            </a>
                                        @else
                                            <span class="badge badge-light border mr-1">{{ $c['codigo'] ?? '' }}</span>
                                        @endif
                                    @endforeach
                                </p>
                            @endif

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0" style="font-size: 0.72rem;">
                                    <thead>
                                        <tr style="background-color: #85C1E9; color: #17202A;">
                                            <th rowspan="2">Día</th>
                                            <th rowspan="2" class="text-center">Comp.</th>
                                            <th colspan="3" class="text-center" title="Neto gravado + imp. interno (cuentas de ventas)">Ventas</th>
                                            <th colspan="3" class="text-center">IVA</th>
                                            <th colspan="3" class="text-center">Total</th>
                                            <th rowspan="2" class="text-center">OK</th>
                                        </tr>
                                        <tr style="background-color: #85C1E9; color: #17202A; font-size: 0.68rem;">
                                            <th class="text-right">ERP</th>
                                            <th class="text-right">Ctb.</th>
                                            <th class="text-right">Dif.</th>
                                            <th class="text-right">ERP</th>
                                            <th class="text-right">Ctb.</th>
                                            <th class="text-right">Dif.</th>
                                            <th class="text-right">ERP</th>
                                            <th class="text-right">Ctb.</th>
                                            <th class="text-right">Dif.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($unidad['dias'] ?? [] as $dia)
                                            @php
                                                $cuadra = ! empty($dia['cuadra']);
                                                $tieneMov = ! empty($dia['tiene_movimiento']);
                                                $ocultar = ! $tieneMov && $cuadra;
                                                $erpVentas = (float) ($dia['erp']['ventas'] ?? 0);
                                                if ($erpVentas == 0.0) {
                                                    $erpVentas = (float) ($dia['erp']['neto_gravado'] ?? 0)
                                                        + (float) ($dia['erp']['imp_interno'] ?? 0)
                                                        + (float) ($dia['erp']['exento'] ?? 0);
                                                }
                                                $ctbVentas = (float) ($dia['contable']['ventas'] ?? 0);
                                                if ($ctbVentas == 0.0) {
                                                    $ctbVentas = (float) ($dia['contable']['ventas_gravadas'] ?? 0) + (float) ($dia['contable']['ventas_kiosco'] ?? 0);
                                                }
                                                $difVentas = (float) ($dia['diferencias']['ventas'] ?? ($erpVentas - $ctbVentas));
                                            @endphp
                                            @if (! $ocultar)
                                                <tr @if (! $cuadra && $tieneMov) class="table-warning" @elseif (! $tieneMov) class="text-muted" @endif>
                                                    <td>{{ $dia['dia_texto'] ?? '' }}</td>
                                                    <td class="text-center">{{ (int) ($dia['comprobantes'] ?? 0) }}</td>
                                                    <td class="text-right">{{ $formatear($erpVentas) }}</td>
                                                    <td class="text-right">{{ $formatear($ctbVentas) }}</td>
                                                    <td class="text-right font-weight-bold">{{ $formatear($difVentas) }}</td>
                                                    <td class="text-right">{{ $formatear($dia['erp']['iva'] ?? 0) }}</td>
                                                    <td class="text-right">{{ $formatear($dia['contable']['iva'] ?? 0) }}</td>
                                                    <td class="text-right font-weight-bold">{{ $formatear($dia['diferencias']['iva'] ?? 0) }}</td>
                                                    <td class="text-right">{{ $formatear($dia['erp']['total'] ?? 0) }}</td>
                                                    <td class="text-right">{{ $formatear($dia['contable']['total'] ?? 0) }}</td>
                                                    <td class="text-right font-weight-bold">{{ $formatear($dia['diferencias']['total'] ?? 0) }}</td>
                                                    <td class="text-center">
                                                        @if (! $tieneMov)
                                                            <span class="text-muted">—</span>
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
                                            <td colspan="12">
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
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
