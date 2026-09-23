@php
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $columnas = $resultado['columnas'] ?? \App\Support\Ventas\IvaVentas\IvaVentasColumnasSupport::COLUMNAS;
    $totalesJur = $resultado['totales_por_jurisdiccion'] ?? [];
@endphp
@if (count($totalesJur) > 0)
    <div class="px-3 py-2 border-bottom">
        <h6 class="mb-2">Totales por jurisdicción (convenio multilateral)</h6>
        <div class="table-responsive mb-2">
            <table class="table table-sm table-bordered mb-0" style="font-size: 0.78rem;">
                <thead>
                    <tr style="background-color: #85C1E9; color: #17202A;">
                        <th>Jurisdicción</th>
                        <th class="text-center">Comp.</th>
                        @foreach ($columnas as $col)
                            <th class="text-right">{{ $col['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($totalesJur as $tot)
                        <tr>
                            <td>
                                <strong>{{ $tot['provincia_label'] ?? 'Sin jurisdicción' }}</strong>
                            </td>
                            <td class="text-center">{{ (int) ($tot['cantidad'] ?? 0) }}</td>
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
