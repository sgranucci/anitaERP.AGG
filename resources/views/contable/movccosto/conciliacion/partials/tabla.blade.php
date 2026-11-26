@php
    /**
     * @var iterable<array<string, mixed>> $filas
     * @var bool $solo_resumen
     * @var bool $multiempresa  columna Empresa en el detalle
     * @var bool $enlaces       links azules (solo pantalla)
     * @var bool $puede_ver_mayor
     * @var bool $puede_ver_asiento
     * @var array<string, mixed> $mayor_base
     * @var array<string, mixed>|null $pdf_cabecera  parámetros de pdf_thead_cabecera (solo PDF)
     */
    $resumen = ! empty($solo_resumen);
    $multi = ! empty($multiempresa);
    $enlaces = ! empty($enlaces);
    $verMayor = $enlaces && ! empty($puede_ver_mayor);
    $verAsiento = $enlaces && ! empty($puede_ver_asiento);
    $mayorBase = $mayor_base ?? [];
    $fmtNum = static fn (float $v): string => number_format($v, 2, ',', '.');
    $fmtFecha = static fn (string $f): string => strlen($f) >= 10 ? substr($f, 8, 2).'/'.substr($f, 5, 2).'/'.substr($f, 0, 4) : $f;
    // Detalle: [Empresa] Fecha, Asiento, Tipo, Origen, Comprobante, Descripción, Mon. | Debe, Haber, Saldo
    $colsTexto = $resumen ? 3 + ($multi ? 1 : 0) : 7 + ($multi ? 1 : 0);
    $colspanTotal = $colsTexto + 3;
    $urlMayor = function (array $fila) use ($verMayor, $mayorBase): ?string {
        if (! $verMayor || (int) ($fila['codigo'] ?? 0) <= 0) {
            return null;
        }
        $params = $mayorBase;
        if ((int) ($fila['empresa_id'] ?? 0) > 0) {
            $params['empresa_ids'] = [(int) $fila['empresa_id']];
            $params['consolidar_empresas'] = 1;
        }
        $params['cuenta_desde'] = (int) $fila['codigo'];
        $params['cuenta_hasta'] = (int) $fila['codigo'];

        return route('mayor_plano_cuenta', $params);
    };
@endphp
<thead>
    @if (! empty($pdf_cabecera))
        @include('includes.reportes.pdf_thead_cabecera', array_merge($pdf_cabecera, ['colspan' => $colspanTotal]))
    @endif
    <tr class="columnas">
        @if ($resumen)
            @if ($multi)
                <th>Empresa</th>
            @endif
            <th class="mcc-col-cta">Cuenta</th>
            <th>Descripci&oacute;n</th>
            <th class="text-right">Movs.</th>
        @else
            @if ($multi)
                <th>Empresa</th>
            @endif
            <th>Fecha</th>
            <th>Asiento</th>
            <th>Tipo</th>
            <th>Origen</th>
            <th>Comprobante</th>
            <th>Descripci&oacute;n</th>
            <th>Mon.</th>
        @endif
        <th class="text-right">Debe</th>
        <th class="text-right">Haber</th>
        <th class="text-right">Saldo</th>
    </tr>
</thead>
<tbody>
@forelse ($filas as $fila)
    @php
        $tipoFila = (string) ($fila['tipo_fila'] ?? '');
        $d = (float) ($fila['debe'] ?? 0);
        $h = (float) ($fila['haber'] ?? 0);
    @endphp
    @if ($tipoFila === 'cuenta')
        @php $url = $urlMayor($fila); @endphp
        <tr class="mcc-fila-header-empresa">
            <td colspan="{{ $colspanTotal }}" style="font-weight:bold;">
                @if ($url)
                    <a class="text-primary" target="_blank" rel="noopener" href="{{ $url }}" title="Mayor de la cuenta en el per&iacute;odo">{{ $fila['codigo_fmt'] }}</a>
                @else
                    {{ $fila['codigo_fmt'] }}
                @endif
                {{ $fila['nombre'] }}
                @if (($fila['empresa_nombre'] ?? '') !== '')
                    &middot; {{ $fila['empresa_nombre'] }}
                @endif
                <span style="font-weight:normal;">({{ (int) $fila['cantidad'] }} {{ (int) $fila['cantidad'] === 1 ? 'movimiento' : 'movimientos' }} sin centro de costo)</span>
            </td>
        </tr>
    @elseif ($tipoFila === 'movimiento')
        <tr>
            @if ($multi)
                <td>{{ $fila['nombreempresa'] ?? '' }}</td>
            @endif
            <td style="white-space:nowrap;">{{ $fmtFecha((string) ($fila['fecha'] ?? '')) }}</td>
            <td style="white-space:nowrap;">
                @if ($verAsiento)
                    <a class="text-primary" target="_blank" rel="noopener"
                        href="{{ route('editar_asiento', ['id' => $fila['asiento_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">{{ $fila['numeroasiento'] }}</a>
                @else
                    {{ $fila['numeroasiento'] }}
                @endif
            </td>
            <td>{{ $fila['tipo_asiento'] ?? '' }}</td>
            <td>{{ $fila['origen'] ?? '' }}</td>
            <td style="white-space:nowrap;">{{ $fila['comprobante'] ?? '' }}</td>
            <td>
                {{ $fila['descripcion'] ?? '' }}
                @if (! empty($fila['centrocosto_id_huerfano']))
                    <span class="badge badge-warning" title="El movimiento apunta a un centro de costo que no existe">CC inexistente #{{ $fila['centrocosto_id_huerfano'] }}</span>
                @endif
            </td>
            <td>{{ $fila['moneda'] ?? '' }}</td>
            <td class="text-right">{{ abs($d) > 0.004 ? $fmtNum($d) : '' }}</td>
            <td class="text-right">{{ abs($h) > 0.004 ? $fmtNum($h) : '' }}</td>
            <td></td>
        </tr>
    @elseif ($tipoFila === 'resumen')
        @php $url = $urlMayor($fila); @endphp
        <tr>
            @if ($multi)
                <td>{{ $fila['empresa_nombre'] !== '' ? $fila['empresa_nombre'] : 'Consolidado' }}</td>
            @endif
            <td style="white-space:nowrap;">
                @if ($url)
                    <a class="text-primary" target="_blank" rel="noopener" href="{{ $url }}" title="Mayor de la cuenta en el per&iacute;odo">{{ $fila['codigo_fmt'] }}</a>
                @else
                    {{ $fila['codigo_fmt'] }}
                @endif
            </td>
            <td>{{ $fila['nombre'] }}</td>
            <td class="text-right">{{ (int) $fila['cantidad'] }}</td>
            <td class="text-right">{{ abs($d) > 0.004 ? $fmtNum($d) : '' }}</td>
            <td class="text-right">{{ abs($h) > 0.004 ? $fmtNum($h) : '' }}</td>
            <td class="text-right">{{ $fmtNum($d - $h) }}</td>
        </tr>
    @else
        {{-- total_cuenta / total_general --}}
        <tr class="{{ $tipoFila === 'total_general' ? 'mcc-fila-total-general' : 'mcc-fila-total-nivel' }}">
            <td colspan="{{ $colsTexto }}" style="font-weight:bold;">
                @if ($tipoFila === 'total_general')
                    Total general &middot; {{ (int) ($fila['cantidad'] ?? 0) }} movimientos
                @else
                    Total {{ $fila['codigo_fmt'] }} {{ $fila['nombre'] }}
                @endif
            </td>
            <td class="text-right" style="font-weight:bold;">{{ abs($d) > 0.004 ? $fmtNum($d) : '' }}</td>
            <td class="text-right" style="font-weight:bold;">{{ abs($h) > 0.004 ? $fmtNum($h) : '' }}</td>
            <td class="text-right" style="font-weight:bold;">{{ $fmtNum($d - $h) }}</td>
        </tr>
    @endif
@empty
    <tr>
        <td colspan="{{ $colspanTotal }}" class="text-center text-muted">
            No hay movimientos sin centro de costo en cuentas que manejan centro de costo para el filtro.
        </td>
    </tr>
@endforelse
</tbody>
