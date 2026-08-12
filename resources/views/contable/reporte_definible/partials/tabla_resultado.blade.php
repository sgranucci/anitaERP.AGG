@php
    $columnas = $resultado['columnas'] ?? [];
    $filas = $resultado['filas'] ?? [];
    $puedeMayor = can('listar-mayor-plano-cuenta', false);
    $puedeDrill = ($puede_drill ?? false) && ($drill_url ?? '') !== '';
    $marcasNota = $resultado['notas_marcas'] ?? [];
@endphp
<div class="table-responsive">
    <table id="tabla-paginada" class="table table-sm table-hover mb-0">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th style="min-width:90px">Código</th>
                <th style="min-width:280px">Concepto</th>
                @foreach ($columnas as $col)
                    <th class="text-right">{{ $col['label'] }}</th>
                @endforeach
                @if ($puedeDrill)
                    <th class="text-center" style="width:50px">Detalle</th>
                @endif
                @if ($puedeMayor)
                    <th class="text-center" style="width:50px">Mayor</th>
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
                    <td>{{ $fila['codigo'] ?? '' }}</td>
                    <td>
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
                    @if ($puedeDrill)
                        <td class="text-center">
                            @if (!$esCuenta && (int) ($fila['rubro_id'] ?? 0) > 0)
                                <button type="button" class="btn-accion-tabla tooltipsC rd-drill-rubro"
                                        title="Ver cuentas, asientos y comprobantes de este rubro"
                                        data-rubro="{{ (int) $fila['rubro_id'] }}"
                                        data-nombre="{{ $fila['nombre'] ?? '' }}">
                                    <i class="fa fa-search-plus text-primary"></i>
                                </button>
                            @elseif ($esCuenta && (int) ($fila['codigo_num'] ?? 0) > 0)
                                <button type="button" class="btn-accion-tabla tooltipsC rd-drill-cuenta"
                                        title="Ver asientos y comprobantes de esta cuenta"
                                        data-codigo="{{ (int) $fila['codigo_num'] }}"
                                        data-nombre="{{ $fila['nombre'] ?? '' }}">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                            @endif
                        </td>
                    @endif
                    @if ($puedeMayor)
                        <td class="text-center">
                            @if (!empty($fila['drill_url']))
                                <a href="{{ $fila['drill_url'] }}" class="btn-accion-tabla tooltipsC" title="Abrir mayor plano"
                                   target="_blank" rel="noopener">
                                    <i class="fa fa-external-link-alt text-primary"></i>
                                </a>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 2 + count($columnas) + ($puedeDrill ? 1 : 0) + ($puedeMayor ? 1 : 0) }}" class="text-center text-muted">
                        Sin filas para mostrar.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
