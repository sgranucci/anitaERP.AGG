@php
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $columnas = $resultado['columnas'] ?? \App\Support\Ventas\IvaVentas\IvaVentasColumnasSupport::COLUMNAS;
    $totales = $resultado['totales_por_sucursal_tipo'] ?? [];
    $puedeVerPuntoventa = $puede_ver_puntoventa ?? false;
    $puedeVerTipo = $puede_ver_tipotransaccion ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
@endphp
@if (count($totales) > 0)
    <div class="px-3 py-2 border-bottom">
        <h6 class="mb-2">Por sucursal y tipo de comprobante</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 0.78rem;">
                <thead>
                    <tr style="background-color: #85C1E9; color: #17202A;">
                        <th>Sucursal</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th class="text-right">Comp.</th>
                        @foreach ($columnas as $col)
                            <th class="text-right">{{ $col['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($totales as $tot)
                        <tr>
                            <td>
                                @if ($puedeVerPuntoventa && (int) ($tot['puntoventa_id'] ?? 0) > 0)
                                    <a href="{{ route('editar_puntoventa', array_merge(['id' => $tot['puntoventa_id']], $queryConsulta)) }}"
                                       target="_blank" rel="noopener" class="text-primary">
                                        {{ $tot['puntoventa_codigo'] ?? '' }}
                                    </a>
                                @else
                                    {{ $tot['puntoventa_codigo'] ?? '' }}
                                @endif
                            </td>
                            <td>{{ $tot['puntoventa_nombre'] ?? '' }}</td>
                            <td>
                                @if ($puedeVerTipo && (int) ($tot['tipotransaccion_id'] ?? 0) > 0)
                                    <a href="{{ route('editar_tipotransaccion', array_merge(['id' => $tot['tipotransaccion_id']], $queryConsulta)) }}"
                                       target="_blank" rel="noopener" class="text-primary">
                                        {{ $tot['tipo'] ?? '' }}
                                    </a>
                                @else
                                    {{ $tot['tipo'] ?? '' }}
                                @endif
                            </td>
                            <td class="text-right">{{ (int) ($tot['cantidad'] ?? 0) }}</td>
                            @foreach ($columnas as $col)
                                <td class="text-right">{{ $formatear($tot['columnas'][$col['key']] ?? 0) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
