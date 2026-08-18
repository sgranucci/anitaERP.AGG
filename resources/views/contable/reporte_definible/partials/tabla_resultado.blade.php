@php
    $columnas = $resultado['columnas'] ?? [];
    $filas = $resultado['filas'] ?? [];
    $puedeMayor = can('listar-mayor-plano-cuenta', false);
    $puedeDrill = ($puede_drill ?? false) && ($drill_url ?? '') !== '';
    $tieneAcciones = $puedeDrill || $puedeMayor;
    $compacta = count($columnas) <= 3;
    $clsAcciones = $compacta ? '' : 'col-fija-der-1';
    $marcasNota = $resultado['notas_marcas'] ?? [];
    $colspan = 2 + count($columnas) + ($tieneAcciones ? 1 : 0);
@endphp
<div class="tabla-ancha-grilla{{ $compacta ? ' tabla-ancha--compacta' : '' }}" data-tabla-ancha style="--tabla-ancha-c1: 6rem; --tabla-ancha-c2: 18rem; --tabla-ancha-der: 4.5rem;">
    <div class="tabla-ancha-scroll-top" hidden>
        <div class="tabla-ancha-scroll-top-inner"></div>
    </div>
    <div class="tabla-ancha-wrap">
        <table id="tabla-paginada" class="table table-sm table-hover mb-0 tabla-ancha">
            <thead>
                <tr>
                    <th class="col-fija-1">Código</th>
                    <th class="col-fija-2">Concepto</th>
                    @foreach ($columnas as $col)
                        <th class="text-right rd-exec-col-importe">{{ $col['label'] }}</th>
                    @endforeach
                    @if ($tieneAcciones)
                        <th class="text-center {{ $clsAcciones }}">Acciones</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($filas as $fila)
                    @php
                        $esCuenta = ($fila['kind'] ?? '') === 'cuenta';
                        $cls = $esCuenta ? 'rd-exec-row-cuenta' : 'rd-exec-row-rubro';
                        if (!empty($fila['negrita'])) {
                            $cls .= ' negrita';
                        }
                        $pad = (int) ($fila['depth'] ?? 0) * 16;
                        $marca = null;
                        if (!$esCuenta) {
                            $codigoLinea = strtoupper(trim((string) ($fila['codigo'] ?? '')));
                            $marca = $marcasNota[$codigoLinea]
                                ?? ($marcasNota['#'.(int) ($fila['rubro_id'] ?? 0)] ?? null);
                        }
                    @endphp
                    <tr class="{{ $cls }}">
                        <td class="col-fija-1">{{ $fila['codigo'] ?? '' }}</td>
                        <td class="col-fija-2">
                            <span class="rd-exec-indent" style="width:{{ $pad }}px"></span>
                            {{ $fila['nombre'] ?? '' }}
                            @if ($marca)
                                <sup class="text-info font-weight-bold tooltipsC" title="Ver notas al pie del informe">{{ $marca }}</sup>
                            @endif
                            @if (!$esCuenta && !empty($fila['tipo_label']))
                                <span class="badge badge-light border ml-1" style="font-size:10px">{{ $fila['tipo_label'] }}</span>
                            @endif
                        </td>
                        @if ($fila['saldos'] === null)
                            @foreach ($columnas as $col)
                                <td></td>
                            @endforeach
                        @else
                            @foreach ($columnas as $col)
                                @php
                                    $key = $col['key'] ?? ($col['periodo'] ?? '');
                                    $v = $fila['saldos'][$key] ?? null;
                                    $esPct = ($col['tipo'] ?? '') === 'var_pct';
                                @endphp
                                <td class="text-right rd-exec-importe">
                                    @if ($v === null)
                                    @elseif ($esPct)
                                        {{ number_format((float) $v, 1, ',', '.') }}%
                                    @else
                                        {{ abs((float) $v) < 0.005 ? '' : number_format((float) $v, 2, ',', '.') }}
                                    @endif
                                </td>
                            @endforeach
                        @endif
                        @if ($tieneAcciones)
                            <td class="text-center text-nowrap {{ $clsAcciones }}">
                                @if ($puedeDrill && !$esCuenta && (int) ($fila['rubro_id'] ?? 0) > 0)
                                    <button type="button" class="btn-accion-tabla tooltipsC rd-drill-rubro"
                                            title="Ver cuentas, asientos y comprobantes de este rubro"
                                            data-rubro="{{ (int) $fila['rubro_id'] }}"
                                            data-nombre="{{ $fila['nombre'] ?? '' }}">
                                        <i class="fa fa-search-plus text-primary"></i>
                                    </button>
                                @elseif ($puedeDrill && $esCuenta && (int) ($fila['codigo_num'] ?? 0) > 0)
                                    <button type="button" class="btn-accion-tabla tooltipsC rd-drill-cuenta"
                                            title="Ver asientos y comprobantes de esta cuenta"
                                            data-codigo="{{ (int) $fila['codigo_num'] }}"
                                            data-nombre="{{ $fila['nombre'] ?? '' }}">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                @endif
                                @if ($puedeMayor && !empty($fila['drill_url']))
                                    <a href="{{ $fila['drill_url'] }}" class="btn-accion-tabla tooltipsC" title="Abrir mayor plano"
                                       target="_blank" rel="noopener">
                                        <i class="fa fa-book text-primary"></i>
                                    </a>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colspan }}" class="text-center text-muted">
                            Sin filas para mostrar.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
