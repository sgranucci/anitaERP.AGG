@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
        ! empty($empresa_nombre)
            ? collect([(object) ['nombreempresa' => $empresa_nombre]])
            : collect()
    );
@endphp
<div class="card border mb-0">
    <div class="card-header py-2 d-flex justify-content-between align-items-center" id="heading-totales-descuentos">
        <button class="btn btn-link btn-block text-left text-dark font-weight-bold p-0 collapsed"
                type="button"
                data-toggle="collapse"
                data-target="#collapse-totales-descuentos"
                aria-expanded="false"
                aria-controls="collapse-totales-descuentos">
            <i class="fa fa-chevron-down mr-1"></i>
            Totales por @php
                echo match ($filtros['agrupar_por'] ?? 'codigo_descuento') {
                    'cliente_descuento' => 'cliente',
                    'mozo_descuento' => 'mozo',
                    'cliente_vip' => 'cliente VIP',
                    default => 'descuento',
                };
            @endphp
            <span class="text-muted font-weight-normal ml-2">
                ({{ count($resultado['totales'] ?? []) }} sectores)
            </span>
        </button>
        <span class="badge badge-primary ml-2">
            Costo total: ${{ number_format((float) ($resultado['gran_total_costo'] ?? 0), 2, ',', '.') }}
        </span>
    </div>
    <div id="collapse-totales-descuentos" class="collapse" aria-labelledby="heading-totales-descuentos">
        <div class="card-body pb-2">
            @if (count($logosCabecera) > 0)
                <div class="mb-3 d-flex flex-wrap align-items-center">
                    @foreach ($logosCabecera as $logo)
                        <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 52px; max-width: 160px;">
                    @endforeach
                </div>
            @endif
            <h5 class="text-center mb-1">DESCUENTOS — TOTALES</h5>
            <p class="text-center text-muted mb-3">
                MES: {{ $resultado['mes_etiqueta'] ?? '' }}
                @if (! empty($empresa_nombre))
                    · {{ $empresa_nombre }}
                @endif
            </p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" id="tabla-totales-descuentos">
                    <thead style="background-color: #85C1E9; color: #17202A;">
                        <tr>
                            <th>Código</th>
                            <th>Sector</th>
                            <th class="text-right">Costo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resultado['totales'] ?? [] as $fila)
                            <tr>
                                <td>{{ $fila['codigo'] ?? '—' }}</td>
                                <td>
                                    @if (! empty($fila['clave']))
                                        <button type="button"
                                                class="btn btn-link btn-sm p-0 text-primary btn-ver-facturas-total-descuento"
                                                data-clave="{{ $fila['clave'] }}"
                                                data-titulo="{{ ($fila['codigo'] ?? '') }} — {{ ($fila['sector'] ?? '') }}"
                                                title="Ver facturas que componen este total">
                                            {{ $fila['sector'] ?? '—' }}
                                            <i class="fa fa-external-link-alt fa-xs ml-1"></i>
                                        </button>
                                    @else
                                        {{ $fila['sector'] ?? '—' }}
                                    @endif
                                </td>
                                <td class="text-right">${{ number_format((float) ($fila['costo_total'] ?? 0), 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="table-active font-weight-bold">
                            <td colspan="2" class="text-right">Total general</td>
                            <td class="text-right">${{ number_format((float) ($resultado['gran_total_costo'] ?? 0), 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
