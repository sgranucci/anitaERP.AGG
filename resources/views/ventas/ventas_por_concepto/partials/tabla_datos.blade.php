@php
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $puedeVerVenta = $puede_ver_venta ?? false;
    $puedeVerCliente = $puede_ver_cliente ?? false;
    $puedeVerConcepto = $puede_ver_concepto ?? false;
    $puedeVerCuenta = $puede_ver_cuenta ?? false;
    $paraPdf = $para_pdf ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $textoCuenta = static function (array $fila): string {
        $codigo = trim((string) ($fila['cuenta_codigo'] ?? ''));
        $nombre = trim((string) ($fila['cuenta_nombre'] ?? ''));
        $cc = trim((string) ($fila['centrocosto_codigo'] ?? ''));
        $txt = trim($codigo.($nombre !== '' ? ' '.$nombre : ''));
        if ($cc !== '') {
            $txt = trim($txt.' (CC '.$cc.')');
        }

        return $txt !== '' ? $txt : '—';
    };
@endphp
<thead>
    <tr>
        <th>Fecha</th>
        <th>Tipo</th>
        <th>Comprobante</th>
        <th>Cliente</th>
        <th>Concepto</th>
        <th>Cuenta</th>
        <th>Descripci&oacute;n</th>
        <th class="text-right">Cantidad</th>
        <th class="text-right">Precio</th>
        <th class="text-right">Neto</th>
        <th class="text-right">IVA</th>
        <th class="text-right">Total</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas as $fila)
        @php $tipo = $fila['tipo_fila'] ?? 'detalle'; @endphp
        @if ($tipo === 'subtotal_concepto' || $tipo === 'subtotal_cuenta')
            <tr class="font-weight-bold" style="background-color: #e9ecef;">
                <td colspan="4">
                    @if ($tipo === 'subtotal_concepto')
                        @if (! $paraPdf && $puedeVerConcepto && (int) ($fila['concepto_venta_id'] ?? 0) > 0)
                            <a href="{{ route('editar_concepto_venta', array_merge(['id' => $fila['concepto_venta_id']], $queryConsulta)) }}"
                               target="_blank" rel="noopener" class="text-primary">
                                {{ $fila['concepto_codigo'] ?? '' }}
                            </a>
                        @else
                            {{ $fila['concepto_codigo'] ?? '' }}
                        @endif
                        {{ $fila['concepto_nombre'] ?? '' }}
                    @else
                        @if (! $paraPdf && $puedeVerCuenta && (int) ($fila['cuentacontable_id'] ?? 0) > 0)
                            <a href="{{ route('editar_cuentacontable', array_merge(['id' => $fila['cuentacontable_id']], $queryConsulta)) }}"
                               target="_blank" rel="noopener" class="text-primary">
                                {{ $fila['cuenta_codigo'] ?? '' }}
                            </a>
                            {{ $fila['cuenta_nombre'] ?? '' }}
                            @if (! empty($fila['centrocosto_codigo']))
                                (CC {{ $fila['centrocosto_codigo'] }})
                            @endif
                        @else
                            {{ $textoCuenta($fila) }}
                        @endif
                    @endif
                </td>
                <td></td>
                <td></td>
                <td>{{ $fila['descripcion'] ?? 'Subtotal' }}</td>
                <td class="text-right">{{ $formatear($fila['cantidad'] ?? 0) }}</td>
                <td></td>
                <td class="text-right">{{ $formatear($fila['neto'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['iva'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total'] ?? 0) }}</td>
            </tr>
        @elseif ($tipo === 'total_final')
            <tr class="font-weight-bold" style="background-color: #d6eaf8;">
                <td colspan="7">{{ $fila['descripcion'] ?? 'TOTAL FINAL' }}</td>
                <td class="text-right">{{ $formatear($fila['cantidad'] ?? 0) }}</td>
                <td></td>
                <td class="text-right">{{ $formatear($fila['neto'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['iva'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total'] ?? 0) }}</td>
            </tr>
        @else
            <tr>
                <td>{{ $fila['fecha'] ?? '' }}</td>
                <td>{{ $fila['tipo'] ?? '' }}</td>
                <td>
                    @if (! $paraPdf && $puedeVerVenta && (int) ($fila['venta_id'] ?? 0) > 0)
                        <a href="{{ route('editar_factura', array_merge(['id' => $fila['venta_id']], $queryConsulta)) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['comprobante'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['comprobante'] ?? '' }}
                    @endif
                </td>
                <td>
                    @if (! $paraPdf && $puedeVerCliente && (int) ($fila['cliente_id'] ?? 0) > 0)
                        <a href="{{ route('editar_cliente', array_merge(['id' => $fila['cliente_id']], $queryConsulta)) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['cliente'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['cliente'] ?? '' }}
                    @endif
                </td>
                <td>
                    @if (! $paraPdf && $puedeVerConcepto && (int) ($fila['concepto_venta_id'] ?? 0) > 0)
                        <a href="{{ route('editar_concepto_venta', array_merge(['id' => $fila['concepto_venta_id']], $queryConsulta)) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['concepto_codigo'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['concepto_codigo'] ?? '' }}
                    @endif
                </td>
                <td>
                    @if (! $paraPdf && $puedeVerCuenta && (int) ($fila['cuentacontable_id'] ?? 0) > 0)
                        <a href="{{ route('editar_cuentacontable', array_merge(['id' => $fila['cuentacontable_id']], $queryConsulta)) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['cuenta_codigo'] ?? '' }}
                        </a>
                        {{ $fila['cuenta_nombre'] ?? '' }}
                        @if (! empty($fila['centrocosto_codigo']))
                            <span class="text-muted">(CC {{ $fila['centrocosto_codigo'] }})</span>
                        @endif
                    @else
                        {{ $textoCuenta($fila) }}
                    @endif
                </td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td class="text-right">{{ $formatear($fila['cantidad'] ?? 0) }}</td>
                <td class="text-right">{{ $fila['precio'] === null ? '' : $formatear($fila['precio']) }}</td>
                <td class="text-right">{{ $formatear($fila['neto'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['iva'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total'] ?? 0) }}</td>
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="12" class="text-center text-muted">Sin registros</td>
        </tr>
    @endforelse
</tbody>
