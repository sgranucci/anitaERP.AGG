@php
    /**
     * @var iterable<array<string, mixed>> $filas
     * @var array<int, array<string, mixed>> $columnas
     * @var list<int> $indices
     * @var string $formato
     * @var bool $mostrarTotal
     * @var bool $enlaces      links azules (solo pantalla)
     * @var bool $fijas
     * @var bool $puede_ver_cuenta
     * @var bool $puede_ver_mayor
     * @var array<string, mixed> $mayor_base
     */
    $dh = ($formato ?? 'saldo') === 'debe_haber';
    $indices = $indices ?? array_keys($columnas ?? []);
    $mostrarTotal = ! empty($mostrarTotal);
    $enlaces = ! empty($enlaces);
    $fijas = ! empty($fijas);
    $puedeVerCuenta = $enlaces && ! empty($puede_ver_cuenta);
    $puedeVerMayor = $enlaces && ! empty($puede_ver_mayor);
    $mayorBase = $mayor_base ?? [];
    $cantCeldas = count($indices) + ($mostrarTotal ? 1 : 0);
    $colspanTotal = 2 + $cantCeldas * ($dh ? 2 : 1);
    $fmtNum = static fn (float $v): string => number_format($v, 2, ',', '.');
    $claseFila = [
        'titulo' => 'mcc-fila-titulo',
        'total_nivel' => 'mcc-fila-total-nivel',
        'total_empresa' => 'mcc-fila-total-empresa',
        'total_general' => 'mcc-fila-total-general',
        'header_empresa' => 'mcc-fila-header-empresa',
        'cuenta' => '',
    ];
@endphp
@forelse ($filas as $fila)
    @php
        $tipoFila = (string) ($fila['tipo_fila'] ?? 'cuenta');
        $esDato = in_array($tipoFila, ['cuenta', 'total_nivel', 'total_empresa', 'total_general'], true);
        $nivel = (int) ($fila['nivel'] ?? 0);
        $sangria = in_array($tipoFila, ['titulo', 'total_nivel', 'cuenta'], true)
            ? min(max($nivel - 1, 0), 6) * 10
            : 0;
    @endphp
    @if ($tipoFila === 'header_empresa')
        <tr class="{{ $claseFila['header_empresa'] }}">
            <td colspan="{{ $colspanTotal }}" style="font-weight:bold;">{{ $fila['nombre'] ?? '' }}</td>
        </tr>
    @else
        <tr class="{{ $claseFila[$tipoFila] ?? '' }}">
            <td class="{{ $fijas ? 'col-fija-1' : '' }}">
                @if ($puedeVerCuenta && $tipoFila === 'cuenta' && ! empty($fila['cuentacontable_id']))
                    <a class="text-primary" target="_blank" rel="noopener"
                        href="{{ route('editar_cuentacontable', [
                            'id' => $fila['cuentacontable_id'],
                            'origen' => 'modal_consulta',
                            'vista' => 'consulta',
                        ]) }}">{{ $fila['codigo_fmt'] ?? '' }}</a>
                @else
                    {{ $fila['codigo_fmt'] ?? '' }}
                @endif
            </td>
            <td class="{{ $fijas ? 'col-fija-2' : '' }}" style="padding-left: {{ 6 + $sangria }}px;">{{ $fila['nombre'] ?? '' }}</td>
            @foreach ($indices as $pos => $i)
                @php
                    $d = (float) ($fila['debe'][$i] ?? 0);
                    $h = (float) ($fila['haber'][$i] ?? 0);
                    $hay = $esDato && (abs($d) > 0.004 || abs($h) > 0.004);
                    $col = $columnas[$i];
                    $urlMayor = null;
                    if ($puedeVerMayor && $tipoFila === 'cuenta' && $hay && $col['tipo'] !== 'sin' && ! empty($col['codigos_cc'])) {
                        $paramsMayor = $mayorBase;
                        if ((int) ($fila['empresa_id'] ?? 0) > 0) {
                            $paramsMayor['empresa_ids'] = [(int) $fila['empresa_id']];
                            $paramsMayor['consolidar_empresas'] = 1;
                        }
                        $paramsMayor['cuenta_desde'] = (int) $fila['codigo'];
                        $paramsMayor['cuenta_hasta'] = (int) $fila['codigo'];
                        $paramsMayor['centrocostos_codigo'] = implode(',', $col['codigos_cc']);
                        $urlMayor = route('mayor_plano_cuenta', $paramsMayor);
                    }
                @endphp
                @if ($dh)
                    <td class="text-right">
                        @if ($hay && abs($d) > 0.004)
                            @if ($urlMayor)
                                <a class="text-primary" target="_blank" rel="noopener" href="{{ $urlMayor }}">{{ $fmtNum($d) }}</a>
                            @else
                                {{ $fmtNum($d) }}
                            @endif
                        @endif
                    </td>
                    <td class="text-right">
                        @if ($hay && abs($h) > 0.004)
                            @if ($urlMayor)
                                <a class="text-primary" target="_blank" rel="noopener" href="{{ $urlMayor }}">{{ $fmtNum($h) }}</a>
                            @else
                                {{ $fmtNum($h) }}
                            @endif
                        @endif
                    </td>
                @else
                    <td class="text-right">
                        @if ($hay)
                            @if ($urlMayor)
                                <a class="text-primary" target="_blank" rel="noopener" href="{{ $urlMayor }}">{{ $fmtNum($d - $h) }}</a>
                            @else
                                {{ $fmtNum($d - $h) }}
                            @endif
                        @endif
                    </td>
                @endif
            @endforeach
            @if ($mostrarTotal)
                @php
                    $td = (float) ($fila['tot_debe'] ?? 0);
                    $th = (float) ($fila['tot_haber'] ?? 0);
                    $hayTotal = $esDato && (abs($td) > 0.004 || abs($th) > 0.004);
                @endphp
                @if ($dh)
                    <td class="text-right font-weight-bold">{{ $hayTotal && abs($td) > 0.004 ? $fmtNum($td) : '' }}</td>
                    <td class="text-right font-weight-bold">{{ $hayTotal && abs($th) > 0.004 ? $fmtNum($th) : '' }}</td>
                @else
                    <td class="text-right font-weight-bold">{{ $hayTotal ? $fmtNum($td - $th) : '' }}</td>
                @endif
            @endif
        </tr>
    @endif
@empty
    <tr>
        <td colspan="{{ $colspanTotal }}" class="text-center text-muted">Sin movimientos para el filtro.</td>
    </tr>
@endforelse
