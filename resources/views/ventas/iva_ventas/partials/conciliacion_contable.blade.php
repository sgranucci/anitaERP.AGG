@php
    $conc = $resultado['conciliacion_contable'] ?? ['habilitada' => false];
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $resumen = $conc['resumen_empresa'] ?? [];
    $lineas = $resumen['lineas'] ?? [];
    $porPv = $conc['por_puntoventa'] ?? [];
    $cuentasDet = $conc['cuentas']['detalle'] ?? [];
    $puedeVerPuntoventa = $puede_ver_puntoventa ?? false;
    $puedeVerCuenta = $puede_ver_cuenta ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
@endphp
@if (! empty($conc['habilitada']))
    <div class="px-3 py-3 border-bottom bg-white">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
            <h6 class="mb-1">Conciliación contable (AnitaERP)</h6>
            @if (! empty($resumen['cuadra_global']))
                <span class="badge badge-success">Cuadre general OK</span>
            @else
                <span class="badge badge-warning">Diferencias en cuadre general</span>
            @endif
        </div>

        @if (count($cuentasDet) > 0)
            <p class="small text-muted mb-2">
                Cuentas controladas
                <span class="text-muted">(facturaci&oacute;n + cierre jornada)</span>:
                @foreach ($cuentasDet as $c)
                    @php
                        $fuente = ($c['fuente'] ?? '') === 'cierre_jornada' ? 'cierre' : 'fact.';
                    @endphp
                    @if ($puedeVerCuenta && (int) ($c['id'] ?? 0) > 0)
                        <a href="{{ route('editar_cuentacontable', array_merge(['id' => $c['id']], $queryConsulta)) }}"
                           target="_blank" rel="noopener" class="badge badge-light border mr-1 text-primary"
                           title="Fuente: {{ $fuente }}">
                            {{ $c['codigo'] ?? '' }} {{ $c['nombre'] ?? '' }}
                        </a>
                    @else
                        <span class="badge badge-light border mr-1" title="Fuente: {{ $fuente }}">{{ $c['codigo'] ?? '' }} {{ $c['nombre'] ?? '' }}</span>
                    @endif
                @endforeach
            </p>
        @endif

        <p class="small font-weight-bold text-muted mb-1">Cuadre general (incluye cierres agrupados)</p>

        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered mb-0" style="font-size: 0.78rem;">
                <thead>
                    <tr style="background-color: #85C1E9; color: #17202A;">
                        <th>Concepto</th>
                        <th class="text-right">IVA ventas (ERP)</th>
                        <th class="text-right">Mayor contable</th>
                        <th class="text-right">Diferencia</th>
                        <th class="text-center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lineas as $linea)
                        @php $cuadra = ! empty($linea['cuadra']); @endphp
                        <tr @if (! $cuadra) class="table-warning" @endif>
                            <td>{{ $linea['concepto'] ?? '' }}</td>
                            <td class="text-right">{{ $formatear($linea['erp'] ?? 0) }}</td>
                            <td class="text-right">{{ $formatear($linea['contable'] ?? 0) }}</td>
                            <td class="text-right font-weight-bold">{{ $formatear($linea['diferencia'] ?? 0) }}</td>
                            <td class="text-center">
                                @if ($cuadra)
                                    <i class="fa fa-check text-success" title="Cuadra"></i>
                                @else
                                    <i class="fa fa-exclamation-triangle text-warning" title="Diferencia"></i>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="small text-muted">
                        <td colspan="5">
                            Comprobantes: {{ (int) ($resumen['comprobantes'] ?? 0) }}
                            · Con asiento vinculado: {{ (int) ($resumen['con_asiento'] ?? 0) }}
                            · Sin asiento: {{ (int) ($resumen['sin_asiento'] ?? 0) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @foreach ($conc['notas'] ?? [] as $nota)
            <p class="small text-muted mb-1"><i class="fa fa-info-circle"></i> {{ $nota }}</p>
        @endforeach

        @if (count($porPv) > 0)
            <h6 class="mt-3 mb-2">Detalle por punto de venta</h6>
            <div class="accordion" id="accordion-conciliacion-pv">
                @foreach ($porPv as $idx => $fila)
                    @php
                        $st = $fila['stats'] ?? [];
                        $modo = $fila['modo_contable'] ?? 'cierre_agrupado';
                        $cuadraPv = ! empty($fila['cuadra_vinculado']) && ($st['con_asiento'] ?? 0) > 0;
                    @endphp
                    <div class="card card-outline card-info mb-1">
                        <div class="card-header p-2" id="heading-conc-pv-{{ $idx }}">
                            <button class="btn btn-link btn-sm text-left w-100 collapsed d-flex justify-content-between align-items-center"
                                type="button" data-toggle="collapse" data-target="#collapse-conc-pv-{{ $idx }}"
                                aria-expanded="false" aria-controls="collapse-conc-pv-{{ $idx }}">
                                <span>
                                    <strong>{{ $fila['seccion_label'] ?? '' }}</strong>
                                    · PV
                                    @if ($puedeVerPuntoventa && (int) ($fila['puntoventa_id'] ?? 0) > 0)
                                        <a href="{{ route('editar_puntoventa', array_merge(['id' => $fila['puntoventa_id']], $queryConsulta)) }}"
                                           target="_blank" rel="noopener" class="text-primary">
                                            {{ $fila['puntoventa_codigo'] ?? '' }}
                                        </a>
                                    @else
                                        {{ $fila['puntoventa_codigo'] ?? '' }}
                                    @endif
                                    {{ $fila['puntoventa_nombre'] ?? '' }}
                                    @if ($modo === 'cierre_agrupado')
                                        <span class="badge badge-secondary ml-1">cierre agrupado</span>
                                    @else
                                        <span class="badge badge-primary ml-1">asiento vinculado</span>
                                    @endif
                                </span>
                                <span class="text-muted small">
                                    ERP IVA {{ $formatear($fila['erp']['iva'] ?? 0) }}
                                    · {{ (int) ($st['cantidad'] ?? 0) }} comp.
                                </span>
                            </button>
                        </div>
                        <div id="collapse-conc-pv-{{ $idx }}" class="collapse" data-parent="#accordion-conciliacion-pv">
                            <div class="card-body p-2">
                                <table class="table table-sm table-bordered mb-0" style="font-size: 0.75rem;">
                                    <thead>
                                        <tr style="background-color: #e9ecef;">
                                            <th></th>
                                            <th class="text-right">Neto grav.</th>
                                            <th class="text-right">Imp. int.</th>
                                            <th class="text-right">IVA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>IVA ventas (ERP)</td>
                                            <td class="text-right">{{ $formatear($fila['erp']['neto_gravado'] ?? 0) }}</td>
                                            <td class="text-right">{{ $formatear($fila['erp']['imp_interno'] ?? 0) }}</td>
                                            <td class="text-right">{{ $formatear($fila['erp']['iva'] ?? 0) }}</td>
                                        </tr>
                                        <tr>
                                            <td>Contable vinculado</td>
                                            <td class="text-right">{{ $formatear($fila['contable_vinculado']['ventas_gravadas'] ?? 0) }}</td>
                                            <td class="text-right">{{ $formatear($fila['contable_vinculado']['ventas_kiosco'] ?? 0) }}</td>
                                            <td class="text-right">{{ $formatear($fila['contable_vinculado']['iva'] ?? 0) }}</td>
                                        </tr>
                                        <tr class="font-weight-bold @if (! $cuadraPv && ($st['con_asiento'] ?? 0) > 0) table-warning @endif">
                                            <td>Diferencia</td>
                                            <td class="text-right">{{ $formatear($fila['diferencias']['neto_gravado'] ?? 0) }}</td>
                                            <td class="text-right">{{ $formatear($fila['diferencias']['imp_interno'] ?? 0) }}</td>
                                            <td class="text-right">{{ $formatear($fila['diferencias']['iva'] ?? 0) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p class="small text-muted mb-0 mt-2">
                                    Con asiento: {{ (int) ($st['con_asiento'] ?? 0) }}
                                    · Sin asiento: {{ (int) ($st['sin_asiento'] ?? 0) }}
                                    @if ($modo === 'cierre_agrupado')
                                        — use el cuadre general de arriba para este PV.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @include('ventas.iva_ventas.partials.conciliacion_facturas_vinculadas', [
        'resultado' => $resultado,
        'puede_ver_venta' => $puede_ver_venta ?? false,
        'puede_ver_asiento' => $puede_ver_asiento ?? false,
    ])
@endif
