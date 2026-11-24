@php
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $columnas = $resultado['columnas'] ?? \App\Support\Ventas\IvaVentas\IvaVentasColumnasSupport::COLUMNAS;
    $totales = $resultado['totales_por_tipo'] ?? [];
    $puedeVerTipo = $puede_ver_tipotransaccion ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
@endphp
@if (count($totales) > 0)
    <div class="px-3 py-3 border-top border-bottom bg-white">
        <h5 class="mb-2 text-dark">
            <i class="fa fa-list-alt text-info"></i>
            Totales por tipo de comprobante
        </h5>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 0.78rem;">
                <thead>
                    <tr style="background-color: #85C1E9; color: #17202A;">
                        <th>Tipo</th>
                        <th>Nombre</th>
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
                                @if ($puedeVerTipo && (int) ($tot['tipotransaccion_id'] ?? 0) > 0)
                                    <a href="{{ route('editar_tipotransaccion', array_merge(['id' => $tot['tipotransaccion_id']], $queryConsulta)) }}"
                                       target="_blank" rel="noopener" class="text-primary">
                                        {{ $tot['tipo'] ?? '' }}
                                    </a>
                                @else
                                    {{ $tot['tipo'] ?? '' }}
                                @endif
                            </td>
                            <td>{{ $tot['tipo_nombre'] ?? ($tot['tipo'] ?? '') }}</td>
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
