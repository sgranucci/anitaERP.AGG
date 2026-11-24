@php
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $columnas = $resultado['columnas'] ?? \App\Support\Compras\IvaCompras\IvaComprasColumnasSupport::columnas();
    $totales = $resultado['totales_por_tipo'] ?? [];
    $puedeVerTipo = $puede_ver_tipotransaccion ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $cantidadTipos = count($totales);
    $collapseId = 'collapse-iva-compras-totales-tipo';
@endphp
@if ($cantidadTipos > 0)
    <div class="px-3 py-2 border-top border-bottom bg-white">
        <div class="card card-outline card-info mb-0">
            <div class="card-header p-2">
                <button class="btn btn-link btn-sm text-left w-100 d-flex justify-content-between align-items-center"
                    type="button" data-toggle="collapse" data-target="#{{ $collapseId }}"
                    aria-expanded="true" aria-controls="{{ $collapseId }}">
                    <span>
                        <i class="fa fa-list-alt text-info"></i>
                        <strong>Totales por tipo de comprobante</strong>
                        <span class="text-muted">({{ $cantidadTipos }} tipo{{ $cantidadTipos === 1 ? '' : 's' }})</span>
                    </span>
                    <span class="text-muted small">
                        <i class="fa fa-chevron-down"></i>
                    </span>
                </button>
            </div>
            <div id="{{ $collapseId }}" class="collapse show">
                <div class="card-body p-2">
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
                                            @if ($puedeVerTipo && (int) ($tot['tipotransaccion_compra_id'] ?? 0) > 0)
                                                <a href="{{ route('editar_tipotransaccion_compra', array_merge(['id' => $tot['tipotransaccion_compra_id']], $queryConsulta)) }}"
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
            </div>
        </div>
    </div>
@endif
