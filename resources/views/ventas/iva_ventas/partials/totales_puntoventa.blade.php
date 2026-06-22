@php
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $columnas = $resultado['columnas'] ?? \App\Support\Ventas\IvaVentas\IvaVentasColumnasSupport::COLUMNAS;
    $totalesPv = $resultado['totales_por_puntoventa'] ?? [];
    $puedeVerPuntoventa = $puede_ver_puntoventa ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
@endphp
@if (count($totalesPv) > 0)
    <div class="px-3 py-2 border-bottom">
        <h6 class="mb-2">Totales por punto de venta</h6>
        <div class="accordion" id="accordion-totales-pv">
            @foreach ($totalesPv as $idx => $tot)
                <div class="card card-outline card-secondary mb-1">
                    <div class="card-header p-2" id="heading-pv-{{ $idx }}">
                        <button class="btn btn-link btn-sm text-left w-100 collapsed d-flex justify-content-between align-items-center"
                            type="button" data-toggle="collapse" data-target="#collapse-pv-{{ $idx }}"
                            aria-expanded="false" aria-controls="collapse-pv-{{ $idx }}">
                            <span>
                                <strong>{{ $tot['seccion_label'] ?? '' }}</strong>
                                · PV
                                @if ($puedeVerPuntoventa && (int) ($tot['puntoventa_id'] ?? 0) > 0)
                                    <a href="{{ route('editar_puntoventa', array_merge(['id' => $tot['puntoventa_id']], $queryConsulta)) }}"
                                       target="_blank" rel="noopener" class="text-primary">
                                        {{ $tot['puntoventa_codigo'] ?? '' }}
                                    </a>
                                @else
                                    {{ $tot['puntoventa_codigo'] ?? '' }}
                                @endif
                                {{ $tot['puntoventa_nombre'] ?? '' }}
                                <span class="text-muted">({{ (int) ($tot['cantidad'] ?? 0) }} comprobantes)</span>
                            </span>
                            <span class="text-muted small">Total {{ $formatear($tot['columnas']['total'] ?? 0) }}</span>
                        </button>
                    </div>
                    <div id="collapse-pv-{{ $idx }}" class="collapse" data-parent="#accordion-totales-pv">
                        <div class="card-body p-2">
                            <table class="table table-sm table-bordered mb-0" style="font-size: 0.78rem;">
                                <thead>
                                    <tr style="background-color: #85C1E9; color: #17202A;">
                                        @foreach ($columnas as $col)
                                            <th class="text-right">{{ $col['label'] }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="font-weight-bold">
                                        @foreach ($columnas as $col)
                                            <td class="text-right">{{ $formatear($tot['columnas'][$col['key']] ?? 0) }}</td>
                                        @endforeach
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
