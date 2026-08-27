@extends("theme.$theme.layout")
@section('titulo')
Transferencia {{ $transferencia->codigo }}
@endsection

@section('contenido')
@php
    use App\Support\Stock\TransferenciaBienUsoSupport;
    use App\Support\Stock\TransferenciaMercaderiaEstados;

    $origen = TransferenciaBienUsoSupport::etiquetaOrigenTransferencia($transferencia);
    $destino = TransferenciaBienUsoSupport::etiquetaDestinoTransferencia($transferencia);
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Transferencia {{ $transferencia->codigo }}</h3>
                <div class="card-tools">
                    @include('stock.movimientostock.partials.boton_imprimir_transferencia_com_pdf', [
                        'transferenciaId' => $transferencia->id,
                    ])
                    @if (can('revertir-movimientos-de-stock', false)
                        && $transferencia->estado === \App\Support\Stock\TransferenciaMercaderiaEstados::CONFIRMADA
                        && ! ($transferencia->transferencia_revertido_por_id ?? null))
                        <form action="{{ route('revertir_transferencia_movimientostock', ['id' => $transferencia->id]) }}" class="d-inline form-revertir-movstock" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-info">
                                <i class="fa fa-undo"></i> Revertir transferencia
                            </button>
                        </form>
                    @endif
                    @can('editar-movimientos-de-stock')
                        @if ($transferencia->movimientostock_salida_id)
                            <a href="{{ route('editar_movimientostock', ['id' => $transferencia->movimientostock_salida_id]) }}" class="btn btn-sm btn-warning">
                                <i class="fa fa-sign-out-alt"></i> Egreso #{{ $transferencia->movimientostock_salida_id }}
                            </a>
                        @endif
                        @if ($transferencia->movimientostock_entrada_id)
                            <a href="{{ route('editar_movimientostock', ['id' => $transferencia->movimientostock_entrada_id]) }}" class="btn btn-sm btn-success">
                                <i class="fa fa-sign-in-alt"></i> Ingreso #{{ $transferencia->movimientostock_entrada_id }}
                            </a>
                        @endif
                    @endcan
                    @if (can('listar-movimientos-de-stock', false))
                        <a href="{{ route('movimientostock') }}" class="btn btn-sm btn-default">Volver al listado de movimientos</a>
                    @elseif (can('listar-transferencias-pendientes', false))
                        <a href="{{ route('transferencia_mercaderia_pendientes') }}" class="btn btn-sm btn-default">Volver a pendientes</a>
                    @else
                        <a href="{{ route('transferencia_mercaderia') }}" class="btn btn-sm btn-default">Volver a transferencias</a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Fecha:</strong> {{ $transferencia->fecha?->format('d/m/Y') }}</div>
                    <div class="col-md-3"><strong>Tipo:</strong> {{ $transferencia->tipotransaccion_stock->nombre ?? '' }}</div>
                    <div class="col-md-3"><strong>Estado:</strong> {{ TransferenciaMercaderiaEstados::etiqueta($transferencia->estado) }}</div>
                    <div class="col-md-3"><strong>Empresa:</strong> {{ $transferencia->empresas->nombre ?? '' }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Origen:</strong> {{ $origen }}</div>
                    <div class="col-md-4"><strong>Destino:</strong> {{ $destino }}</div>
                    <div class="col-md-4"><strong>Lote:</strong> {{ $transferencia->lote }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Usuario origen:</strong> {{ $transferencia->usuarioOrigen->nombre ?? '—' }}</div>
                    <div class="col-md-4"><strong>Usuario destino:</strong> {{ $transferencia->usuarioDestino->nombre ?? '—' }}</div>
                    <div class="col-md-4"><strong>Aprobador:</strong> {{ $transferencia->usuarioAprobador->nombre ?? '—' }}</div>
                </div>
                @if ($transferencia->observacion)
                    <p><strong>Observaci&oacute;n:</strong> {{ $transferencia->observacion }}</p>
                @endif
                @if ($transferencia->motivo_rechazo)
                    <p class="text-danger"><strong>Motivo rechazo:</strong> {{ $transferencia->motivo_rechazo }}</p>
                @endif

                <h5 class="mt-4">&Iacute;tems transferidos</h5>
                <table class="table table-bordered table-sm">
                    <thead style="background-color: #85C1E9; color: #17202A;">
                        <tr>
                            <th>#</th>
                            <th>SKU origen</th>
                            <th>Art&iacute;culo origen</th>
                            <th class="text-right">Cant. origen</th>
                            <th class="text-right">Costo orig.</th>
                            <th>SKU destino</th>
                            <th>Art&iacute;culo destino</th>
                            <th class="text-right">Cant. destino</th>
                            <th class="text-right">Costo dest.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transferencia->articulos as $item)
                            <tr>
                                <td>{{ $item->item }}</td>
                                <td>
                                    @include('stock.partials.link_articulo_consulta', [
                                        'articuloId' => (int) ($item->articulo_origen_id ?? optional($item->articuloOrigen)->id ?? 0),
                                        'texto' => $item->articuloOrigen->sku ?? '',
                                        'titulo' => 'Consultar artículo origen',
                                    ])
                                </td>
                                <td>
                                    @include('stock.partials.link_articulo_consulta', [
                                        'articuloId' => (int) ($item->articulo_origen_id ?? optional($item->articuloOrigen)->id ?? 0),
                                        'texto' => $item->articuloOrigen->descripcion ?? '',
                                        'titulo' => 'Consultar artículo origen',
                                    ])
                                </td>
                                <td class="text-right">{{ number_format((float) $item->cantidad_origen, 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) $item->precio_costo_origen, 4, ',', '.') }}</td>
                                <td>
                                    @include('stock.partials.link_articulo_consulta', [
                                        'articuloId' => (int) ($item->articulo_destino_id ?? optional($item->articuloDestino)->id ?? 0),
                                        'texto' => $item->articuloDestino->sku ?? '',
                                        'titulo' => 'Consultar artículo destino / insumo',
                                    ])
                                </td>
                                <td>
                                    @include('stock.partials.link_articulo_consulta', [
                                        'articuloId' => (int) ($item->articulo_destino_id ?? optional($item->articuloDestino)->id ?? 0),
                                        'texto' => $item->articuloDestino->descripcion ?? '',
                                        'titulo' => 'Consultar artículo destino / insumo',
                                    ])
                                </td>
                                <td class="text-right">{{ number_format((float) $item->cantidad_destino, 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) $item->precio_costo_destino, 4, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/revertir.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/movimientostock/revertir.js')) ?: time() }}" type="text/javascript"></script>
@endsection
