@extends("theme.$theme.layout")

@section('titulo')
    Factura gastronomía — venta {{ $venta->id }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-outline card-primary mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>{{ $venta->codigo ?? '' }}</span>
                <div>
                    <a href="{{ route('gastronomia_facturas_dia') }}" class="btn btn-sm btn-outline-secondary">Volver al listado</a>
                    <a href="{{ url('ventas/listaunafactura/'.$venta->id) }}" target="_blank" class="btn btn-sm btn-outline-primary">PDF / QR ARCA</a>
                </div>
            </div>
            <div class="card-body py-2">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Cliente:</strong> {{ $venta->clientes->nombre ?? '—' }}<br>
                        <strong>Fecha:</strong> {{ $venta->fecha }}<br>
                        <strong>Total:</strong> {{ number_format((float) $venta->total, 2, ',', '.') }}
                        {{ $venta->monedas->abreviatura ?? '' }}
                    </div>
                    <div class="col-md-6">
                        <strong>PV:</strong> {{ $venta->puntoventas->codigo ?? '—' }} — modo {{ $venta->puntoventas->modofacturacion ?? '—' }}<br>
                        <strong>Cuenta gastronomía:</strong> {{ $meta->cuenta_gastronomia_id ?? '—' }}<br>
                        <strong>PC emisión:</strong> {{ $meta->identificador_pc }}
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header p-0 border-bottom-0">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#tab-detalle">Detalle ítems</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-cobranzas">Cobranzas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-contable">Asiento</a>
                    </li>
                </ul>
            </div>
            <div class="card-body tab-content">
                <div class="tab-pane fade show active" id="tab-detalle">
                    <table class="table table-sm">
                        <thead><tr><th>SKU</th><th>Detalle</th><th>Cant.</th><th>Precio</th></tr></thead>
                        <tbody>
                            @foreach ($venta->venta_emisiones as $em)
                                <tr>
                                    <td>{{ $em->articulos->sku ?? '' }}</td>
                                    <td>{{ $em->articulos->descripcion ?? $em->detalle }}</td>
                                    <td>{{ $em->cantidad }}</td>
                                    <td>{{ number_format((float) $em->precio, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="tab-cobranzas">
                    @if ($cobranzas->isEmpty())
                        <p class="text-muted mb-0">Sin cobranzas registradas para esta venta.</p>
                    @else
                        <table class="table table-sm">
                            <thead><tr><th>ID</th><th>Estado</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($cobranzas as $cob)
                                    <tr>
                                        <td>{{ $cob->id }}</td>
                                        <td>{{ $cob->estado ?? '—' }}</td>
                                        <td>
                                            @if (can('editar-cobranza', false))
                                                <a href="{{ route('editar_cobranza', ['id' => $cob->id, 'origen' => 'gastronomia']) }}" class="btn btn-sm btn-outline-warning">Editar cobranza</a>
                                            @else
                                                <span class="text-muted small">Sin permiso editar cobranza</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="small text-muted mb-0">La edición está pensada solo para reacomodar medios de cobro manteniendo el total aplicado a la factura (control en cobranzas).</p>
                    @endif
                </div>

                <div class="tab-pane fade" id="tab-contable">
                    @php $asientos = $venta->asientos ?? collect(); @endphp
                    @if ($asientos->isEmpty())
                        <p class="text-muted mb-0">Sin asientos asociados (por ejemplo AGG puede generar contabilidad después en rendición).</p>
                    @else
                        @foreach ($asientos as $as)
                            <div class="mb-3">
                                <strong>Asiento {{ $as->id }}</strong>
                                <table class="table table-sm table-bordered mt-1">
                                    <thead><tr><th>Cuenta</th><th>Monto</th><th>Obs.</th></tr></thead>
                                    <tbody>
                                        @foreach ($as->asiento_movimientos as $mov)
                                            <tr>
                                                <td>{{ $mov->cuentacontables->codigo ?? '' }} {{ $mov->cuentacontables->nombre ?? '' }}</td>
                                                <td>{{ number_format((float) ($mov->monto ?? 0), 2, ',', '.') }}</td>
                                                <td>{{ $mov->observacion ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
