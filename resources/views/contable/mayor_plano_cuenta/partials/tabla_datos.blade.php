@php
    $formatearMonto = static function ($valor, bool $mostrarCero = false) {
        if ($valor === null || $valor === '') {
            return '';
        }
        if (! $mostrarCero && (float) $valor === 0.0) {
            return '';
        }

        return number_format((float) $valor, 2, ',', '.');
    };
    $puedeVerAsiento = $puede_ver_asiento ?? false;
    $puedeVerCuenta = $puede_ver_cuenta ?? false;
    $puedeVerOc = $puede_ver_ordencompra ?? false;
    $puedeVerProveedor = $puede_ver_proveedor ?? false;
    $mostrarEmpresa = $multiempresa ?? false;
    $colSpanBase = $mostrarEmpresa ? 12 : 11;
@endphp
<thead>
    <tr>
        <th>Fecha</th>
        <th>N.Asi.</th>
        <th>Tip</th>
        <th>Comprobante</th>
        <th>Emisor</th>
        <th>CUIT</th>
        <th>Descripción mov.</th>
        <th>O.Compra</th>
        <th>Mon</th>
        <th class="text-right">Cotiz.</th>
        <th class="text-right">Mon.Referencia</th>
        <th class="text-right">Debe</th>
        <th class="text-right">Haber</th>
        <th class="text-right">Saldo del mes</th>
        <th class="text-right">Saldo ejerc.</th>
        @if ($mostrarEmpresa)
            <th>Empr.</th>
        @endif
    </tr>
</thead>
<tbody>
    @forelse ($filas as $f)
        @php
            $fila = is_array($f) ? $f : (array) $f;
            $tipoFila = $fila['tipo_fila'] ?? 'detalle';
        @endphp
        @if ($tipoFila === 'header_empresa')
            <tr class="font-weight-bold" style="background-color: #fff3cd;">
                <td colspan="{{ $mostrarEmpresa ? 16 : 15 }}">
                    <i class="far fa-building mr-1"></i>
                    Empresa: {{ $fila['nombreempresa'] ?? '' }}
                </td>
            </tr>
        @elseif ($tipoFila === 'header_cuenta')
            <tr class="font-weight-bold" style="background-color: #d6eaf8;">
                <td colspan="{{ $mostrarEmpresa ? 16 : 15 }}">
                    Cuenta:
                    @if ($puedeVerCuenta && (int) ($fila['cuentacontable_id'] ?? 0) > 0)
                        <a href="{{ route('editar_cuentacontable', ['id' => $fila['cuentacontable_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['cuenta_codigo'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['cuenta_codigo'] ?? '' }}
                    @endif
                    {{ $fila['cuenta_nombre'] ?? '' }}
                </td>
            </tr>
        @elseif ($tipoFila === 'saldo_inicial')
            <tr style="background-color: #f8f9fa;">
                <td>Saldo Inicial</td>
                <td colspan="{{ $colSpanBase }}"></td>
                <td class="text-right">{{ $formatearMonto($fila['saldo_ejercicio'] ?? null, true) }}</td>
                @if ($mostrarEmpresa)
                    <td></td>
                @endif
            </tr>
        @elseif ($tipoFila === 'total_cuenta')
            <tr class="font-weight-bold" style="background-color: #e9ecef; border-top: 1px solid #adb5bd;">
                <td colspan="{{ $colSpanBase }}" class="text-right">
                    Total cuenta
                    @if ($puedeVerCuenta && (int) ($fila['cuentacontable_id'] ?? 0) > 0)
                        <a href="{{ route('editar_cuentacontable', ['id' => $fila['cuentacontable_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['cuenta_codigo'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['cuenta_codigo'] ?? '' }}
                    @endif
                    @if (! empty($fila['cuenta_nombre']))
                        — {{ $fila['cuenta_nombre'] }}
                    @endif
                </td>
                <td class="text-right">{{ $formatearMonto($fila['debe'] ?? null) }}</td>
                <td class="text-right">{{ $formatearMonto($fila['haber'] ?? null) }}</td>
                <td></td>
                <td></td>
                @if ($mostrarEmpresa)
                    <td></td>
                @endif
            </tr>
        @else
            <tr>
                <td>{{ $fila['fecha_fmt'] ?? '' }}</td>
                <td>
                    @if ($puedeVerAsiento && (int) ($fila['asiento_id'] ?? 0) > 0)
                        <a href="{{ route('editar_asiento', ['id' => $fila['asiento_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['nro_asiento_fmt'] ?? $fila['nro_asiento'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['nro_asiento_fmt'] ?? $fila['nro_asiento'] ?? '' }}
                    @endif
                </td>
                <td>{{ $fila['tipo_comp'] ?? '' }}</td>
                <td>
                    @php
                        $textoComprobante = $fila['comprobante'] ?? '';
                        $cpIdFila = (int) ($fila['comprobante_proveedor_id'] ?? 0);
                        $ventaIdFila = (int) ($fila['venta_id'] ?? 0);
                        $remesaIdFila = (int) ($fila['remesa_id'] ?? 0);
                        $jornadaGastroIdFila = (int) ($fila['jornada_gastronomia_id'] ?? 0);
                        $rendicionEstacIdFila = (int) ($fila['rendicion_estacionamiento_caja_id'] ?? 0);
                        $tmIdFila = (int) ($fila['transferencia_mercaderia_id'] ?? 0);
                        $cobranzaIdFila = (int) ($fila['cobranza_id'] ?? 0);
                        $pagoIdFila = (int) ($fila['pagoproveedor_id'] ?? 0);
                        $recepcionIdFila = (int) ($fila['recepcionproveedor_id'] ?? 0);
                        $movStockIdFila = (int) ($fila['movimientostock_id'] ?? 0);
                        $cajaMovIdFila = (int) ($fila['caja_movimiento_id'] ?? 0);
                        $solicitudpagoIdFila = (int) ($fila['solicitudpago_id'] ?? 0);
                        $solicitudpagoCodigoFila = trim((string) ($fila['solicitudpago_codigo'] ?? ''));
                        $puedeVerCp = $puede_ver_comprobante_proveedor ?? false;
                        $puedeVerFactura = $puede_ver_factura ?? false;
                        $puedeVerRemesa = $puede_ver_remesa ?? false;
                        $puedeVerJornadaGastro = $puede_ver_jornada_gastronomia ?? false;
                        $puedeVerRendicionEstac = $puede_ver_rendicion_estacionamiento ?? false;
                        $puedeVerTm = $puede_ver_transferencia_mercaderia ?? false;
                        $puedeVerCobranza = $puede_ver_cobranza ?? false;
                        $puedeVerPago = $puede_ver_pagoproveedor ?? false;
                        $puedeVerRecepcion = $puede_ver_recepcion_proveedor ?? false;
                        $puedeVerMovStock = $puede_ver_movimientostock ?? false;
                        $puedeVerCajaMov = $puede_ver_caja_movimiento ?? false;
                        $puedeVerSp = $puede_ver_solicitudpago ?? false;
                        $hrefComprobante = null;
                        $hrefSolicitudpago = null;
                        if ($puedeVerCp && $cpIdFila > 0) {
                            $hrefComprobante = route('editar_comprobante_proveedor', ['id' => $cpIdFila, 'origen' => 'modal_consulta', 'vista' => 'consulta']);
                        } elseif ($puedeVerFactura && $ventaIdFila > 0) {
                            $hrefComprobante = route('editar_factura', ['id' => $ventaIdFila, 'origen' => 'modal_consulta', 'vista' => 'consulta']);
                        } elseif ($puedeVerRemesa && $remesaIdFila > 0) {
                            $hrefComprobante = route('editar_remesa', ['id' => $remesaIdFila, 'origen' => 'modal_consulta', 'vista' => 'consulta']);
                        } elseif ($puedeVerJornadaGastro && $jornadaGastroIdFila > 0) {
                            $hrefComprobante = route('waitry_cierre_jornada', ['jornada_id' => $jornadaGastroIdFila, 'origen' => 'modal_consulta']);
                        } elseif ($puedeVerRendicionEstac && $rendicionEstacIdFila > 0) {
                            $hrefComprobante = route('editar_rendicionestacionamiento', ['id' => $rendicionEstacIdFila, 'origen' => 'modal_consulta', 'vista' => 'consulta']);
                        } elseif ($puedeVerTm && $tmIdFila > 0) {
                            $hrefComprobante = route('transferencia_mercaderia', ['id' => $tmIdFila, 'origen' => 'modal_consulta']);
                        } elseif ($puedeVerCobranza && $cobranzaIdFila > 0) {
                            $hrefComprobante = route('editar_cobranza', ['id' => $cobranzaIdFila, 'origen' => 'modal_consulta']);
                        } elseif ($puedeVerPago && $pagoIdFila > 0) {
                            $hrefComprobante = route('editar_pagoproveedor', ['id' => $pagoIdFila, 'origen' => 'modal_consulta', 'vista' => 'consulta']);
                        } elseif ($puedeVerRecepcion && $recepcionIdFila > 0) {
                            $hrefComprobante = route('editar_recepcion_proveedor', ['id' => $recepcionIdFila, 'origen' => 'modal_consulta', 'vista' => 'consulta']);
                        } elseif ($puedeVerMovStock && $movStockIdFila > 0) {
                            $hrefComprobante = route('editar_movimientostock', ['id' => $movStockIdFila, 'origen' => 'modal_consulta', 'vista' => 'consulta']);
                        } elseif ($puedeVerCajaMov && $cajaMovIdFila > 0) {
                            $hrefComprobante = route('editar_ingresoegreso', ['id' => $cajaMovIdFila, 'origen' => 'modal_consulta']);
                        } elseif ($puedeVerSp && $solicitudpagoIdFila > 0) {
                            $hrefComprobante = route('editar_solicitudpago', ['id' => $solicitudpagoIdFila, 'origen' => 'modal_consulta', 'vista' => 'consulta']);
                        } elseif ($puedeVerOc) {
                            $ocIdComp = (int) ($fila['ordencompra_id'] ?? 0);
                            if ($ocIdComp <= 0) {
                                $ocIdComp = (int) ($fila['ordencompra_id_asiento'] ?? 0);
                            }
                            if ($ocIdComp > 0) {
                                $hrefComprobante = route('editar_ordencompra', ['id' => $ocIdComp, 'origen' => 'modal_consulta', 'vista' => 'consulta']);
                            }
                        }
                        if ($puedeVerSp && $solicitudpagoIdFila > 0 && $cajaMovIdFila > 0) {
                            $hrefSolicitudpago = route('editar_solicitudpago', ['id' => $solicitudpagoIdFila, 'origen' => 'modal_consulta', 'vista' => 'consulta']);
                        }
                        if ($textoComprobante === '' && $hrefComprobante) {
                            $textoComprobante = 'Ver origen';
                        }
                    @endphp
                    @if ($textoComprobante !== '')
                        @if ($hrefComprobante)
                            <a href="{{ $hrefComprobante }}" target="_blank" rel="noopener" class="text-primary">
                                {{ $textoComprobante }}
                            </a>
                        @else
                            {{ $textoComprobante }}
                        @endif
                        @if ($hrefSolicitudpago)
                            <span class="text-muted"> · </span>
                            <a href="{{ $hrefSolicitudpago }}" target="_blank" rel="noopener" class="text-primary">
                                SP {{ $solicitudpagoCodigoFila !== '' ? $solicitudpagoCodigoFila : '#'.$solicitudpagoIdFila }}
                            </a>
                        @endif
                    @elseif ($hrefSolicitudpago)
                        <a href="{{ $hrefSolicitudpago }}" target="_blank" rel="noopener" class="text-primary">
                            SP {{ $solicitudpagoCodigoFila !== '' ? $solicitudpagoCodigoFila : '#'.$solicitudpagoIdFila }}
                        </a>
                    @endif
                </td>
                <td>
                    @if ($puedeVerProveedor && (int) ($fila['proveedor_id'] ?? 0) > 0)
                        <a href="{{ route('editar_proveedor', ['id' => $fila['proveedor_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['emisor'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['emisor'] ?? '' }}
                    @endif
                </td>
                <td>{{ $fila['cuit'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td>
                    @php
                        $nroOcFila = (int) ($fila['nro_oc'] ?? 0);
                        $ocIdFila = (int) ($fila['ordencompra_id'] ?? 0);
                        if ($ocIdFila <= 0) {
                            $ocIdFila = (int) ($fila['ordencompra_id_asiento'] ?? 0);
                        }
                    @endphp
                    @if ($nroOcFila > 0 || $ocIdFila > 0)
                        @if ($puedeVerOc && $ocIdFila > 0)
                            <a href="{{ route('editar_ordencompra', ['id' => $ocIdFila, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                               target="_blank" rel="noopener" class="text-primary">
                                {{ $nroOcFila > 0 ? $nroOcFila : ('#'.$ocIdFila) }}
                            </a>
                        @elseif ($nroOcFila > 0)
                            {{ $nroOcFila }}
                        @endif
                    @endif
                </td>
                <td>{{ $fila['moneda_abrev'] ?? '' }}</td>
                <td class="text-right">
                    @if (! empty($fila['cotizacion']))
                        {{ number_format((float) $fila['cotizacion'], 4, ',', '.') }}
                    @endif
                </td>
                <td class="text-right">{{ $formatearMonto($fila['mon_referencia'] ?? null) }}</td>
                <td class="text-right">{{ $formatearMonto($fila['debe'] ?? null) }}</td>
                <td class="text-right">{{ $formatearMonto($fila['haber'] ?? null) }}</td>
                <td class="text-right">{{ $formatearMonto($fila['saldo_mes'] ?? null, true) }}</td>
                <td class="text-right">{{ $formatearMonto($fila['saldo_ejercicio'] ?? null, true) }}</td>
                @if ($mostrarEmpresa)
                    <td>{{ $fila['empresa_id'] ?? '' }}</td>
                @endif
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="{{ $mostrarEmpresa ? 16 : 15 }}" class="text-center text-muted py-4">
                No se generaron movimientos para el período seleccionado.
            </td>
        </tr>
    @endforelse
</tbody>
