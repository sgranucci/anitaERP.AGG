@extends("theme.$theme.layout")
@section('titulo', 'Diferencia de cambio')

@section('contenido')
<div class="container-fluid">
    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Factura con diferencia de cambio</h3>
            <div class="card-tools">
                <a href="{{ $urlBandeja }}" class="btn btn-outline-info btn-sm">
                    <i class="fa fa-reply-all"></i> Mis aprobaciones
                </a>
            </div>
        </div>
        <div class="card-body">
            @include('includes.form-error')
            @include('includes.mensaje')

            @php
                $factura = $comprobante;
            @endphp
            <p>
                La recepción de un período cerrado no se modifica. Si aprobás, la factura se contabiliza
                en el mes abierto con la diferencia de cambio.
            </p>

            <table class="table table-sm table-bordered">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Comprobante</th>
                        <th>Dato</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Factura {{ $factura->letra }} {{ $factura->sucursal }}-{{ $factura->numerocomprobante }}</td>
                        <td>
                            {{ optional($factura->proveedores)->nombre }}
                            · TC {{ number_format((float) $factura->cotizacion, 4, ',', '.') }}
                            · {{ optional($factura->empresas)->nombre }}
                        </td>
                        <td>
                            <a class="text-primary" href="{{ $url_factura }}" target="_blank" rel="noopener">Abrir</a>
                        </td>
                    </tr>
                    @if ($url_oc)
                        <tr>
                            <td>Orden de compra {{ optional($factura->ordencompras)->numeroordencompra }}</td>
                            <td></td>
                            <td>
                                <a class="text-primary" href="{{ $url_oc }}" target="_blank" rel="noopener">Abrir</a>
                            </td>
                        </tr>
                    @endif
                    @foreach ($recepciones as $recepcion)
                        <tr>
                            <td>Recepción #{{ $recepcion['id'] }}</td>
                            <td>
                                @if ($recepcion['fecha'] !== '')
                                    {{ $recepcion['fecha'] }} ·
                                @endif
                                TC {{ number_format((float) $recepcion['tc'], 4, ',', '.') }}
                            </td>
                            <td>
                                <a class="text-primary" href="{{ $recepcion['url'] }}" target="_blank" rel="noopener">Abrir</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($filas->isNotEmpty())
                <h5>Ajuste</h5>
                <table class="table table-sm table-bordered">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Recepción</th>
                            <th class="text-right">TC recepción</th>
                            <th class="text-right">TC factura</th>
                            <th class="text-right">Nominal</th>
                            <th class="text-right">Pesos</th>
                            <th class="text-right">%</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filas as $fila)
                            <tr>
                                <td>#{{ $fila->recepcion_proveedor_id }}</td>
                                <td class="text-right">{{ number_format((float) $fila->tc_recepcion, 4, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) $fila->tc_factura, 4, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) $fila->nominal_me, 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) $fila->importe_pesos, 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) $fila->porcentaje, 2, ',', '.') }}</td>
                                <td>{{ $fila->estado }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
        @if ($pendiente)
            <div class="card-footer">
                <form action="{{ $urlAprobar }}" method="POST" class="d-inline" onsubmit="return confirm('Se contabiliza la factura.');">
                    @csrf
                    <button type="submit" class="btn btn-success">Aprobar y contabilizar</button>
                </form>
                <form action="{{ $urlRechazar }}" method="POST" class="d-inline" onsubmit="return confirm('La factura queda en borrador.');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">Rechazar</button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
