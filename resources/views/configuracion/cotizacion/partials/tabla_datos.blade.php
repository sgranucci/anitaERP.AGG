@php
    use App\Support\Configuracion\CotizacionListadoColumnas;
    $mostrarAcciones = $mostrarAcciones ?? false;
    $retornoListadoQuery = $retornoListadoQuery ?? [];
    $monedasColumnas = $monedasColumnas ?? collect();
@endphp
<table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th class="width20 text-center align-middle" rowspan="2">ID</th>
            <th class="text-center align-middle" rowspan="2" style="min-width: 90px;">Fecha</th>
            @foreach ($monedasColumnas as $moneda)
                <th class="text-center" colspan="2">{{ $moneda->nombre }}</th>
            @endforeach
            @if ($mostrarAcciones)
                <th class="width80 text-center align-middle" rowspan="2" data-orderable="false"></th>
            @endif
        </tr>
        <tr>
            @foreach ($monedasColumnas as $moneda)
                <th class="text-center" style="min-width: 80px;">Compra</th>
                <th class="text-center" style="min-width: 80px;">Venta</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($datas as $data)
            @php
                $mapa = CotizacionListadoColumnas::mapaPorMoneda($data);
            @endphp
            <tr>
                <td>{{ $data->id }}</td>
                <td class="text-nowrap">{{ $data->fecha ? \Illuminate\Support\Carbon::parse($data->fecha)->format('d/m/Y') : '' }}</td>
                @foreach ($monedasColumnas as $moneda)
                    @php
                        $vals = $mapa[(int) $moneda->id] ?? ['compra' => null, 'venta' => null];
                    @endphp
                    <td class="text-right text-nowrap">{{ CotizacionListadoColumnas::formatear($vals['compra']) }}</td>
                    <td class="text-right text-nowrap">{{ CotizacionListadoColumnas::formatear($vals['venta']) }}</td>
                @endforeach
                @if ($mostrarAcciones)
                    <td class="text-nowrap">
                        @if (can('editar-cotizacion', false))
                            <a href="{{ route('editar_cotizacion', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                <i class="fa fa-edit"></i>
                            </a>
                        @endif
                        @if (can('borrar-cotizacion', false))
                            <form action="{{ route('eliminar_cotizacion', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                @csrf @method("delete")
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
                <td colspan="{{ 2 + ($monedasColumnas->count() * 2) + ($mostrarAcciones ? 1 : 0) }}" class="text-center text-muted py-3">
                    Sin cotizaciones para los filtros indicados.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
