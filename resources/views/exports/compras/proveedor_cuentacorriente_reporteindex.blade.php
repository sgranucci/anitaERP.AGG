@php
    use App\Support\Compras\ProveedorCuentacorrienteReporteFiltros;
    use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;
    $filaLogo = $reservarFilaLogoExcel ?? false;
    $modoDeuda = ! empty($modoDeuda)
        || (($filtros['modo'] ?? ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA)
            !== ProveedorCuentacorrienteReporteFiltros::MODO_FICHA);
    $stats = $resultado['stats'] ?? [];
    $soloTotalesDeuda = $modoDeuda && ! empty($filtros['solo_totales']);
    $totalesReporte = $resultado['totales'] ?? [];
    $columnasSaldo = $resultado['columnas_saldo'] ?? [];
    if (! $modoDeuda && $columnasSaldo === []) {
        $columnasSaldo = [[
            'moneda_id' => CuentacorrienteSaldosPorMoneda::monedaLocalId(),
            'abreviatura' => CuentacorrienteSaldosPorMoneda::abreviaturaLocal(),
            'es_local' => true,
        ]];
    }
    $cantSaldoFicha = max(1, count($columnasSaldo));
    $colspan = $soloTotalesDeuda ? 2 : ($modoDeuda ? 12 : (9 + $cantSaldoFicha));
@endphp
<table>
    @if ($filaLogo)
        <tr>
            <td colspan="{{ $colspan }}" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}" style="font-weight: bold; font-size: 16px;">{{ $titulo ?? 'Cuenta corriente proveedores' }}</td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr>
            <td colspan="{{ $colspan }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    @if (! empty($stats))
        <tr>
            <td colspan="{{ $colspan }}">
                Proveedores: {{ $stats['proveedores'] ?? 0 }}
                · Movimientos: {{ $stats['movimientos'] ?? 0 }}
                @if (($stats['aplicaciones'] ?? 0) > 0)
                    · Aplicaciones: {{ $stats['aplicaciones'] }}
                @endif
            </td>
        </tr>
    @endif
    @if ($soloTotalesDeuda)
    <thead>
        <tr>
            <th>Proveedor</th>
            <th>Saldo</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            @php $tipo = $fila['tipo'] ?? ''; @endphp
            @if ($tipo === 'header_proveedor')
                @continue
            @endif
            @if ($tipo === 'header_empresa')
                <tr>
                    <td>Empresa: {{ $fila['nombreempresa'] ?? $fila['empresa_nombre'] ?? '' }}</td>
                    <td></td>
                </tr>
                @continue
            @endif
            @if ($tipo !== 'total_proveedor')
                @continue
            @endif
            <tr>
                <td>{{ $fila['proveedor_nombre'] ?? '' }}</td>
                <td>{{ isset($fila['saldo_pendiente']) ? number_format((float) $fila['saldo_pendiente'], 2, '.', '') : '' }}</td>
            </tr>
        @endforeach
        <tr>
            <td>Total deuda pendiente</td>
            <td>{{ number_format((float) ($totalesReporte['pendiente'] ?? 0), 2, '.', '') }}</td>
        </tr>
    </tbody>
    @else
    <thead>
        <tr>
            <th>Código</th>
            <th>Proveedor</th>
            <th>Empresa</th>
            <th>Fecha</th>
            <th>Vencimiento</th>
            <th>Comprobante</th>
            <th>Moneda</th>
            @if ($modoDeuda)
                <th>Importe</th>
                <th>Aplicado</th>
                <th>Saldo pend.</th>
                <th>Saldo</th>
                <th></th>
            @else
                <th>Debe</th>
                <th>Haber</th>
                @foreach ($columnasSaldo as $colSaldo)
                    <th>{{ CuentacorrienteSaldosPorMoneda::etiquetaColumnaSaldoMoneda($colSaldo) }}</th>
                @endforeach
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            @php $tipo = $fila['tipo'] ?? ''; @endphp
            @if ($tipo === 'header_empresa')
                <tr>
                    <td></td>
                    <td>Empresa: {{ $fila['nombreempresa'] ?? $fila['empresa_nombre'] ?? '' }}</td>
                    @for ($columnaVacia = 2; $columnaVacia < $colspan; $columnaVacia++)
                        <td></td>
                    @endfor
                </tr>
                @continue
            @endif
            <tr>
                <td>{{ in_array($tipo, ['header_proveedor', 'total_proveedor'], true) ? ($fila['proveedor_codigo'] ?? '') : '' }}</td>
                <td>
                    @if ($tipo === 'header_proveedor')
                        {{ $fila['proveedor_nombre'] ?? '' }}
                    @elseif ($tipo === 'total_proveedor')
                        Total {{ $fila['proveedor_nombre'] ?? '' }}
                    @elseif ($tipo === 'saldo_anterior')
                        {{ $fila['comprobante'] ?? 'Saldo anterior' }}
                    @endif
                </td>
                <td>{{ in_array($tipo, ['header_proveedor', 'aplicacion', 'saldo_anterior', 'movimiento'], true) ? ($fila['nombreempresa'] ?? '') : '' }}</td>
                <td>{{ $fila['fecha'] ?? '' }}</td>
                <td>{{ $fila['fechavencimiento'] ?? '' }}</td>
                <td>{{ $tipo === 'header_proveedor' ? '' : ($fila['comprobante'] ?? '') }}</td>
                <td>{{ $fila['etiqueta_moneda'] ?? ($fila['abreviatura'] ?? '') }}</td>
                @if ($modoDeuda)
                    <td>{{ isset($fila['importe']) ? number_format((float) $fila['importe'], 2, '.', '') : '' }}</td>
                    <td>{{ isset($fila['aplicado']) ? number_format((float) $fila['aplicado'], 2, '.', '') : '' }}</td>
                    <td>{{ isset($fila['saldo_pendiente']) ? number_format((float) $fila['saldo_pendiente'], 2, '.', '') : '' }}</td>
                    <td>{{ isset($fila['saldo_parcial']) ? number_format((float) $fila['saldo_parcial'], 2, '.', '') : '' }}</td>
                    <td></td>
                @else
                    <td>{{ isset($fila['debe']) ? number_format((float) $fila['debe'], 2, '.', '') : '' }}</td>
                    <td>{{ isset($fila['haber']) ? number_format((float) $fila['haber'], 2, '.', '') : '' }}</td>
                    @php $mapaSaldos = $fila['saldos_por_moneda'] ?? null; @endphp
                    @foreach ($columnasSaldo as $colSaldo)
                        <td>
                            @if (is_array($mapaSaldos))
                                {{ number_format((float) ($mapaSaldos[$colSaldo['moneda_id']] ?? $mapaSaldos[(string) $colSaldo['moneda_id']] ?? 0), 2, '.', '') }}
                            @endif
                        </td>
                    @endforeach
                @endif
            </tr>
        @endforeach
    </tbody>
    @endif
</table>
