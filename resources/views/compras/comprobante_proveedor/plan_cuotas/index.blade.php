@extends("theme.$theme.layout")
@section('titulo')
    Plan de cuotas de facturas impagas
@endsection
@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Plan de cuotas de facturas impagas</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Unifica cuotas, cambia la condición de pago o rearma los vencimientos de un comprobante sin pagos.
                    El total y la moneda de la factura no se modifican. Todas las cuotas quedan con la cotización de la cabecera.
                    Para cambiar importes, conceptos o la cotización de la factura usá la edición del comprobante.
                </p>
                <form method="get" action="{{ route('plan_cuotas_comprobante_proveedor') }}" class="form-inline mb-3">
                    <input type="text" name="q" value="{{ $texto }}" class="form-control mr-2" placeholder="Número o proveedor" autofocus>
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Comprobante</th>
                                <th>Proveedor</th>
                                <th>Estado</th>
                                <th class="text-right">Total</th>
                                <th>Moneda</th>
                                <th class="text-right">Cuotas</th>
                                <th class="text-right">Cotizaciones</th>
                                <th>Pagos</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $fila)
                                @php $cp = $fila['comprobante']; @endphp
                                <tr>
                                    <td>{{ $fila['etiqueta'] }}</td>
                                    <td>{{ $cp->proveedores->codigo ?? '' }} {{ $cp->proveedores->nombre ?? '' }}</td>
                                    <td>{{ $cp->estado }}</td>
                                    <td class="text-right">{{ number_format((float) $cp->total, 2, ',', '.') }}</td>
                                    <td>{{ $cp->monedas->abreviatura ?? '' }}</td>
                                    <td class="text-right">{{ $fila['cuotas'] }}</td>
                                    <td class="text-right">{{ $fila['cotizaciones'] }}</td>
                                    <td>{{ $fila['impago'] ? 'Impago' : 'Con pagos' }}</td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('editar_plan_cuotas_comprobante_proveedor', $cp->id) }}">Abrir</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-muted text-center">
                                        {{ $texto === '' ? 'Buscá por número de comprobante o por proveedor.' : 'Sin comprobantes para esa búsqueda.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
