@php
    $pf = $resultado['conciliacion_contable']['por_factura_vinculada'] ?? ['habilitada' => false];
    $facturas = $pf['facturas'] ?? [];
    $statsPf = $pf['stats'] ?? [];
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $puedeVerVenta = $puede_ver_venta ?? false;
    $puedeVerAsiento = $puede_ver_asiento ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $soloDiferencias = $solo_diferencias ?? true;
    $facturasVista = $soloDiferencias
        ? array_values(array_filter($facturas, static fn (array $f) => empty($f['cuadra'])))
        : $facturas;
@endphp
@if (! empty($pf['habilitada']) && (int) ($statsPf['vinculadas'] ?? 0) > 0)
    <div class="px-3 py-3 border-bottom">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
            <h6 class="mb-1">Cuadre por factura (imputaci&oacute;n unitaria)</h6>
            <span class="small text-muted">
                {{ (int) ($statsPf['vinculadas'] ?? 0) }} con asiento
                · {{ (int) ($statsPf['cuadran'] ?? 0) }} cuadran
                · {{ (int) ($statsPf['con_diferencia'] ?? 0) }} con diferencia
            </span>
        </div>
        <p class="small text-muted mb-2">
            Solo comprobantes con asiento vinculado (<code>asiento.venta_id</code>).
            Gastronom&iacute;a / estacionamiento agrupados en cierre de jornada no aparecen aqu&iacute;; valide esos contra el cuadre general.
            @if ((int) ($statsPf['cierre_agrupado'] ?? 0) > 0)
                <span class="badge badge-secondary ml-1">{{ (int) $statsPf['cierre_agrupado'] }} cierre agrupado</span>
            @endif
            @if ((int) ($statsPf['sin_asiento'] ?? 0) > 0)
                <span class="badge badge-light border ml-1">{{ (int) $statsPf['sin_asiento'] }} sin asiento</span>
            @endif
        </p>

        @if (count($facturasVista) === 0)
            <p class="small text-success mb-0">
                <i class="fa fa-check"></i> Todas las facturas con asiento vinculado cuadran con el mayor contable.
            </p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" style="font-size: 0.75rem;">
                    <thead>
                        <tr style="background-color: #85C1E9; color: #17202A;">
                            <th>Fecha</th>
                            <th>Comprobante</th>
                            <th>Cliente</th>
                            <th class="text-right">Neto grav. ERP</th>
                            <th class="text-right">Neto grav. ctb.</th>
                            <th class="text-right">IVA ERP</th>
                            <th class="text-right">IVA ctb.</th>
                            <th class="text-right">Dif. IVA</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($facturasVista as $fac)
                            @php $cuadra = ! empty($fac['cuadra']); @endphp
                            <tr @if (! $cuadra) class="table-warning" @endif>
                                <td>{{ $fac['fecha_mov'] ?? '' }}</td>
                                <td>{{ $fac['comprobante'] ?? '' }}</td>
                                <td>{{ $fac['cliente_nombre'] ?? '' }}</td>
                                <td class="text-right">{{ $formatear($fac['erp']['neto_gravado'] ?? 0) }}</td>
                                <td class="text-right">{{ $formatear($fac['contable']['ventas_gravadas'] ?? 0) }}</td>
                                <td class="text-right">{{ $formatear($fac['erp']['iva'] ?? 0) }}</td>
                                <td class="text-right">{{ $formatear($fac['contable']['iva'] ?? 0) }}</td>
                                <td class="text-right font-weight-bold">{{ $formatear($fac['diferencias']['iva'] ?? 0) }}</td>
                                <td class="text-nowrap text-center">
                                    @if ($puedeVerVenta && (int) ($fac['venta_id'] ?? 0) > 0)
                                        <a href="{{ route('editar_factura', array_merge(['id' => $fac['venta_id']], $queryConsulta)) }}"
                                           target="_blank" rel="noopener" class="btn btn-info btn-xs btn-sm py-0" title="Ver factura">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                    @endif
                                    @if ($puedeVerAsiento && (int) ($fac['asiento_id'] ?? 0) > 0)
                                        <a href="{{ route('editar_asiento', array_merge(['id' => $fac['asiento_id']], $queryConsulta)) }}"
                                           target="_blank" rel="noopener" class="btn btn-secondary btn-xs btn-sm py-0" title="Ver asiento">
                                            <i class="fa fa-book"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif
