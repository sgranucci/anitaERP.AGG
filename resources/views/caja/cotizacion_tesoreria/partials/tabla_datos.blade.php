@php
    use App\Support\Caja\CotizacionTesoreriaMonedasSupport;
    $monedasColumnas = $monedasColumnas ?? CotizacionTesoreriaMonedasSupport::monedasParaColumnas();
    $mostrarAcciones = $mostrarAcciones ?? false;
    $retornoListadoQuery = $retornoListadoQuery ?? [];
@endphp
<table class="table table-striped table-bordered table-hover" id="tabla-paginada">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th class="width20" rowspan="2">ID</th>
            <th rowspan="2">Empresa</th>
            <th rowspan="2">Fecha</th>
            @foreach ($monedasColumnas as $moneda)
                <th colspan="2" class="text-center" title="{{ $moneda->nombre }}">{{ $moneda->label }}</th>
            @endforeach
            @if ($mostrarAcciones)
                <th class="width80" rowspan="2" data-orderable="false"></th>
            @endif
        </tr>
        <tr>
            @foreach ($monedasColumnas as $moneda)
                <th class="text-center">Compra</th>
                <th class="text-center">Venta</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($datas as $data)
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ $data->nombreempresa ?? $data->empresas?->nombre ?? $data->empresa_id }}</td>
                <td>{{ $data->fecha ? $data->fecha->format('d/m/Y') : '' }}</td>
                @foreach ($monedasColumnas as $moneda)
                    <td class="text-right">{{ CotizacionTesoreriaMonedasSupport::formatear($data->tasaCompra((int) $moneda->codigo)) }}</td>
                    <td class="text-right">{{ CotizacionTesoreriaMonedasSupport::formatear($data->tasaVenta((int) $moneda->codigo)) }}</td>
                @endforeach
                @if ($mostrarAcciones)
                    <td>
                        @if (can('editar-cotizacion-tesoreria', false))
                            <a href="{{ route('editar_cotizacion_tesoreria', ['id' => $data->id] + $retornoListadoQuery) }}"
                               class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                <i class="fa fa-edit"></i>
                            </a>
                        @endif
                        @if (can('borrar-cotizacion-tesoreria', false))
                            <form action="{{ route('eliminar_cotizacion_tesoreria', ['id' => $data->id]) }}"
                                  class="d-inline form-eliminar" method="POST">
                                @csrf @method('delete')
                                <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </form>
                        @endif
                    </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ 3 + ($monedasColumnas->count() * 2) + ($mostrarAcciones ? 1 : 0) }}" class="text-center text-muted py-4">
                    No hay cotizaciones de tesorería para los filtros aplicados.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
