@php
    use App\Support\Stock\RecepcionProveedorReporteFiltros;

    $paraPdf = $para_pdf ?? false;
    $paraExcel = ! empty($para_excel);
    $columnasCompletas = $columnas_completas ?? (! $paraPdf);
    $modo = $modo ?? RecepcionProveedorReporteFiltros::MODO_DETALLE;
    $esResumen = $modo === RecepcionProveedorReporteFiltros::MODO_RESUMEN;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $puedeVerRecepcion = ! $paraPdf && ! $paraExcel && ($puede_ver_recepcion ?? false);
    $puedeVerArticulo = ! $paraPdf && ! $paraExcel && ($puede_ver_articulo ?? false);
    $puedeVerOc = ! $paraPdf && ! $paraExcel && ($puede_ver_ordencompra ?? false);
    $puedeVerReq = ! $paraPdf && ! $paraExcel && ($puede_ver_requisicion ?? false);
    $puedeVerProveedor = ! $paraPdf && ! $paraExcel && ($puede_ver_proveedor ?? false);
    $puedeVerCuenta = ! $paraPdf && ! $paraExcel && ($puede_ver_cuentacontable ?? false);
    $puedeVerCp = ! $paraPdf && ! $paraExcel && ($puede_ver_comprobante ?? false);
    $colspanCompleto = $esResumen ? 18 : ($columnasCompletas ? 43 : 17);
    $tableClass = $table_class ?? 'table table-hover table-striped table-sm mb-0';
    $soloTheadTbody = ! empty($solo_thead_tbody);
    $filasIterable = $filas ?? [];
    if ($filasIterable instanceof \Illuminate\Pagination\LengthAwarePaginator) {
        $filasIterable = $filasIterable->items();
    }
@endphp
@if (! $soloTheadTbody)
<table id="tabla-paginada" class="{{ $tableClass }} rpr-tabla">
@endif
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            @if ($esResumen)
                <th>Fecha</th>
                <th>C.C.</th>
                <th>N&ordm; factura remito</th>
                <th>O.Compra</th>
                <th>N&ordm; COM</th>
                <th>Proveedor</th>
                <th>Rubro</th>
                <th class="text-right">Importe</th>
                <th class="text-right">Importe MN</th>
                <th>Usuario</th>
                <th>Pide requi</th>
                <th>Autoriza requi</th>
                <th>Estado fact.</th>
                <th class="text-right">D&iacute;as s/fact.</th>
                <th>Dep&oacute;sito</th>
                <th>Empresa</th>
                <th>Estado</th>
                <th>Dif.</th>
            @else
                <th>Art&iacute;culo</th>
                <th>Descripci&oacute;n</th>
                @if ($columnasCompletas)
                    <th>Desde NPU</th>
                    <th>Hasta NPU</th>
                    <th>Grupo</th>
                    <th>Subg</th>
                    <th>Tip</th>
                @endif
                <th>N&ordm; COM</th>
                @if ($columnasCompletas)
                    <th>COM Anita</th>
                    <th>N.Pro.</th>
                @endif
                <th>Proveedor</th>
                <th>Fecha</th>
                <th class="text-right">Cantidad</th>
                @if ($columnasCompletas)
                    <th>UM</th>
                @endif
                <th class="text-right">Importe</th>
                <th class="text-right">Total</th>
                @if ($columnasCompletas)
                    <th class="text-right">Total MN</th>
                @endif
                <th>O.Compra</th>
                @if ($columnasCompletas)
                    <th>Fecha OC</th>
                @endif
                <th class="text-right">C.Pedida</th>
                <th class="text-right">P.Pactado</th>
                @if ($columnasCompletas)
                    <th class="text-right">% var.</th>
                @endif
                <th>C.Cos.</th>
                @if ($columnasCompletas)
                    <th>Cuenta</th>
                @endif
                <th class="text-right">Dif. unid.</th>
                <th class="text-right">Pendiente</th>
                <th>Nr. factura</th>
                @if ($columnasCompletas)
                    <th>Factura ERP</th>
                    <th class="text-right">D&iacute;as s/fact.</th>
                    <th>Nr.requi</th>
                    <th>Fec.req.</th>
                    <th>Pide requi</th>
                    <th>Autoriza requi</th>
                    <th>CC dest.</th>
                    <th>Comentario</th>
                    <th>Usu. orig.</th>
                    <th>Empr.</th>
                    <th>Dif.</th>
                    <th>DEP</th>
                    <th>Asiento</th>
                    <th>Estado fact.</th>
                @endif
                <th>Estado</th>
                <th>Usuario</th>
            @endif
        </tr>
    </thead>
    <tbody>
    @forelse ($filasIterable as $fila)
        @php
            $fila = is_array($fila) ? $fila : (array) $fila;
            $tipoFila = (string) ($fila['tipo_fila'] ?? 'dato');
        @endphp
        @if ($tipoFila === 'header_empresa')
            <tr class="rpr-header-empresa">
                <td colspan="{{ $colspanCompleto }}">
                    <strong>{{ $fila['nombreempresa'] ?? $fila['etiqueta_grupo'] ?? '' }}</strong>
                </td>
            </tr>
        @elseif ($tipoFila === 'header_grupo')
            <tr class="rpr-header-grupo">
                <td colspan="{{ $colspanCompleto }}">
                    <strong>{{ $fila['etiqueta_grupo'] ?? '' }}</strong>
                </td>
            </tr>
        @elseif ($tipoFila === 'subtotal_grupo')
            <tr class="rpr-subtotal">
                @if ($esResumen)
                    <td colspan="7"><strong>Total {{ $fila['etiqueta_grupo'] ?? '' }}</strong></td>
                    <td class="text-right"><strong>{{ number_format((float) ($fila['total'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td class="text-right"><strong>{{ number_format((float) ($fila['importe_mn'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td colspan="9"></td>
                @else
                    <td colspan="{{ $columnasCompletas ? 12 : 5 }}"><strong>Total {{ $fila['etiqueta_grupo'] ?? '' }}</strong></td>
                    <td class="text-right"><strong>{{ number_format((float) ($fila['cantidad'] ?? 0), 2, ',', '.') }}</strong></td>
                    @if ($columnasCompletas)
                        <td></td>
                    @endif
                    <td></td>
                    <td class="text-right"><strong>{{ number_format((float) ($fila['total'] ?? 0), 2, ',', '.') }}</strong></td>
                    @if ($columnasCompletas)
                        <td class="text-right"><strong>{{ number_format((float) ($fila['importe_mn'] ?? 0), 2, ',', '.') }}</strong></td>
                    @endif
                    <td colspan="{{ $columnasCompletas ? 26 : 9 }}"></td>
                @endif
            </tr>
        @else
            @php
                $filaClass = '';
                if (! $paraPdf && ! $paraExcel) {
                    if (! empty($fila['fl_precio_pendiente'])) {
                        $filaClass = 'table-info';
                    } elseif (($fila['estado'] ?? '') === 'BORRADOR') {
                        $filaClass = 'table-secondary';
                    } elseif (($fila['estado'] ?? '') === 'ANULADA') {
                        $filaClass = 'table-danger';
                    } elseif (! empty($fila['tiene_diff'])) {
                        $filaClass = 'table-warning';
                    }
                }
                $recepcionId = (int) ($fila['recepcion_id'] ?? 0);
                $articuloId = (int) ($fila['articulo_id'] ?? 0);
                $ocId = (int) ($fila['ordencompra_id'] ?? 0);
                $reqId = (int) ($fila['requisicion_id'] ?? 0);
                $ctaId = (int) ($fila['cuentacontable_id'] ?? 0);
                $cpId = (int) ($fila['comprobante_proveedor_id'] ?? 0);
                $proveedorId = (int) ($fila['proveedor_id'] ?? 0);
                $varPct = (float) ($fila['var_precio_pct'] ?? 0);
                $dias = $fila['dias_sin_facturar'] ?? null;
            @endphp
            <tr class="{{ $filaClass }}">
                @if ($esResumen)
                    <td>{{ $fila['fecha_fmt'] ?? '' }}</td>
                    <td>{{ $fila['codigo_cc'] ?? '' }}</td>
                    <td>{{ $fila['numerofactura'] ?? '' }}</td>
                    <td>
                        @if ($puedeVerOc && $ocId > 0)
                            <a class="text-primary" href="{{ route('editar_ordencompra', array_merge(['id' => $ocId], $queryConsulta)) }}" target="_blank" rel="noopener">{{ $fila['numeroordencompra'] ?? '' }}</a>
                        @else
                            {{ $fila['numeroordencompra'] ?? '' }}
                        @endif
                    </td>
                    <td>
                        @if ($puedeVerRecepcion && $recepcionId > 0)
                            <a class="text-primary" href="{{ route('editar_recepcion_proveedor', array_merge(['id' => $recepcionId], $queryConsulta)) }}" target="_blank" rel="noopener">{{ $fila['numerorecepcion'] ?? '' }}</a>
                        @else
                            {{ $fila['numerorecepcion'] ?? '' }}
                        @endif
                    </td>
                    <td>
                        @if ($puedeVerProveedor && $proveedorId > 0)
                            <a class="text-primary" href="{{ route('editar_proveedor', array_merge(['id' => $proveedorId], $queryConsulta)) }}" target="_blank" rel="noopener">{{ $fila['nombreproveedor'] ?? '' }}</a>
                        @else
                            {{ $fila['nombreproveedor'] ?? '' }}
                        @endif
                    </td>
                    <td>{{ $fila['nombre_categoria'] ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['total'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['importe_mn'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ $fila['usuario'] ?? '' }}</td>
                    <td>{{ $fila['usuario_requisicion'] ?? '' }}</td>
                    <td>{{ $fila['autorizante_requisicion'] ?? '' }}</td>
                    <td>{{ $fila['estado_facturacion'] ?? '' }}</td>
                    <td class="text-right">
                        @if ($dias !== null)
                            {{ (int) $dias }}
                        @endif
                    </td>
                    <td>{{ trim(($fila['codigo_deposito'] ?? '').' '.($fila['nombre_deposito'] ?? '')) }}</td>
                    <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                    <td>{{ $fila['estado'] ?? '' }}</td>
                    <td>
                        @if (! empty($fila['tiene_diff']))
                            S&iacute;
                        @endif
                    </td>
                @else
                    <td>
                        @if ($puedeVerArticulo && $articuloId > 0)
                            <a class="text-primary" href="{{ route('editar_articulo', array_merge(['id' => $articuloId], $queryConsulta)) }}" target="_blank" rel="noopener">{{ $fila['sku'] ?? '' }}</a>
                        @else
                            {{ $fila['sku'] ?? '' }}
                        @endif
                    </td>
                    <td>{{ $fila['descripcion_articulo'] ?? '' }}</td>
                    @if ($columnasCompletas)
                        <td>{{ $fila['npu_desde'] ?? '' }}</td>
                        <td>{{ $fila['npu_hasta'] ?? '' }}</td>
                        <td>{{ $fila['nombre_categoria'] ?? '' }}</td>
                        <td>{{ $fila['nombre_subcategoria'] ?? '' }}</td>
                        <td>{{ $fila['nombre_tipoarticulo'] ?? '' }}</td>
                    @endif
                    <td>
                        @if ($puedeVerRecepcion && $recepcionId > 0)
                            <a class="text-primary" href="{{ route('editar_recepcion_proveedor', array_merge(['id' => $recepcionId], $queryConsulta)) }}" target="_blank" rel="noopener">{{ $fila['numerorecepcion'] ?? '' }}</a>
                        @else
                            {{ $fila['numerorecepcion'] ?? '' }}
                        @endif
                    </td>
                    @if ($columnasCompletas)
                        <td>{{ $fila['com_anita'] ?? '' }}</td>
                        <td>{{ $fila['codigo_proveedor'] ?? '' }}</td>
                    @endif
                    <td>
                        @if ($puedeVerProveedor && $proveedorId > 0)
                            <a class="text-primary" href="{{ route('editar_proveedor', array_merge(['id' => $proveedorId], $queryConsulta)) }}" target="_blank" rel="noopener">{{ $fila['nombreproveedor'] ?? '' }}</a>
                        @else
                            {{ $fila['nombreproveedor'] ?? '' }}
                        @endif
                    </td>
                    <td>{{ $fila['fecha_fmt'] ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['cantidad'] ?? 0), 2, ',', '.') }}</td>
                    @if ($columnasCompletas)
                        <td>{{ $fila['um_abreviatura'] ?? '' }}</td>
                    @endif
                    <td class="text-right">{{ number_format((float) ($fila['precio'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['total'] ?? 0), 2, ',', '.') }}</td>
                    @if ($columnasCompletas)
                        <td class="text-right">{{ number_format((float) ($fila['importe_mn'] ?? 0), 2, ',', '.') }}</td>
                    @endif
                    <td>
                        @if ($puedeVerOc && $ocId > 0)
                            <a class="text-primary" href="{{ route('editar_ordencompra', array_merge(['id' => $ocId], $queryConsulta)) }}" target="_blank" rel="noopener">{{ $fila['numeroordencompra'] ?? '' }}</a>
                        @else
                            {{ $fila['numeroordencompra'] ?? '' }}
                        @endif
                    </td>
                    @if ($columnasCompletas)
                        <td>{{ $fila['fecha_oc_fmt'] ?? '' }}</td>
                    @endif
                    <td class="text-right">{{ number_format((float) ($fila['cantidad_oc'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['precio_oc'] ?? 0), 2, ',', '.') }}</td>
                    @if ($columnasCompletas)
                        @php
                            $claseVar = abs($varPct) >= 0.5 ? 'text-right text-danger font-weight-bold' : 'text-right';
                        @endphp
                        <td class="{{ $claseVar }}">
                            @if (abs($varPct) >= 0.01)
                                {{ number_format($varPct, 1, ',', '.') }}%
                            @endif
                        </td>
                    @endif
                    <td>{{ $fila['codigo_cc'] ?? '' }}</td>
                    @if ($columnasCompletas)
                        <td>
                            @if ($puedeVerCuenta && $ctaId > 0)
                                <a class="text-primary" href="{{ route('editar_cuentacontable', array_merge(['id' => $ctaId], $queryConsulta)) }}" target="_blank" rel="noopener">{{ $fila['codigo_cuenta'] ?? '' }}</a>
                            @else
                                {{ $fila['codigo_cuenta'] ?? '' }}
                            @endif
                        </td>
                    @endif
                    <td class="text-right">{{ number_format((float) ($fila['dif_unidades'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['pendiente'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ $fila['numerofactura'] ?? '' }}</td>
                    @if ($columnasCompletas)
                        <td>
                            @if ($puedeVerCp && $cpId > 0)
                                <a class="text-primary" href="{{ route('editar_comprobante_proveedor', array_merge(['id' => $cpId], $queryConsulta)) }}" target="_blank" rel="noopener">{{ $fila['factura_erp'] ?? '' }}</a>
                            @else
                                {{ $fila['factura_erp'] ?? '' }}
                            @endif
                        </td>
                        <td class="text-right">
                            @if ($dias !== null)
                                {{ (int) $dias }}
                            @endif
                        </td>
                        <td>
                            @if ($puedeVerReq && $reqId > 0)
                                <a class="text-primary" href="{{ route('editar_requisicion', array_merge(['id' => $reqId], $queryConsulta)) }}" target="_blank" rel="noopener">{{ $fila['numerorequisicion'] ?? '' }}</a>
                            @else
                                {{ $fila['numerorequisicion'] ?? '' }}
                            @endif
                        </td>
                        <td>{{ $fila['fecha_requisicion_fmt'] ?? '' }}</td>
                        <td>{{ $fila['usuario_requisicion'] ?? '' }}</td>
                        <td>{{ $fila['autorizante_requisicion'] ?? '' }}</td>
                        <td>{{ $fila['codigo_cc_req'] ?? '' }}</td>
                        <td>{{ $fila['comentario'] ?? '' }}</td>
                        <td>{{ $fila['usuario_orig'] ?? '' }}</td>
                        <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                        <td>
                            @if (! empty($fila['tiene_diff']))
                                S&iacute;
                            @endif
                        </td>
                        <td>{{ trim(($fila['codigo_deposito'] ?? '').' '.($fila['nombre_deposito'] ?? '')) }}</td>
                        <td>{{ $fila['numeroasiento'] ?? '' }}</td>
                        <td>{{ $fila['estado_facturacion'] ?? '' }}</td>
                    @endif
                    <td>{{ $fila['estado'] ?? '' }}</td>
                    <td>{{ $fila['usuario'] ?? '' }}</td>
                @endif
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="{{ $colspanCompleto }}" class="text-center text-muted">
                No hay movimientos para los filtros indicados.
            </td>
        </tr>
    @endforelse
    </tbody>
@if (! $soloTheadTbody)
</table>
@endif
