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
        @if ($tipoFila === 'header_cuenta')
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
                <td>{{ $fila['comprobante'] ?? '' }}</td>
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
                    @if ((int) ($fila['nro_oc'] ?? 0) > 0)
                        @if ($puedeVerOc && (int) ($fila['ordencompra_id'] ?? 0) > 0)
                            <a href="{{ route('editar_ordencompra', ['id' => $fila['ordencompra_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                               target="_blank" rel="noopener" class="text-primary">
                                {{ $fila['nro_oc'] }}
                            </a>
                        @else
                            {{ $fila['nro_oc'] }}
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
