@extends("theme.$theme.layout")
@section('titulo')
    Picking pedidos
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/stock/picking_pedido/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $colLabel = 'col-lg-2 control-label text-right pr-2';
    $colInput = 'col-lg-4';
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Picking pedidos pendientes de facturar</h3>
                <div class="card-tools">
                    <a href="{{ route('picking_pedido') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('picking_pedido') }}" class="mb-0" id="form-picking-pedido">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <div class="form-group row">
                        <label for="cliente_id" class="{{ $colLabel }}">Cliente</label>
                        <div class="{{ $colInput }}">
                            <select name="cliente_id" id="cliente_id" class="form-control">
                                <option value="0">-- Todos --</option>
                                @foreach ($cliente_query as $cli)
                                    <option value="{{ $cli->id }}" @if ((int) $cliente_id === (int) $cli->id) selected @endif>
                                        {{ $cli->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="deposito_id" class="{{ $colLabel }}">Dep&oacute;sito</label>
                        <div class="{{ $colInput }}">
                            <select name="deposito_id" id="deposito_id" class="form-control">
                                <option value="0">-- Todos --</option>
                                @foreach ($deposito_query as $dep)
                                    <option value="{{ $dep->id }}" @if ((int) $deposito_id === (int) $dep->id) selected @endif>
                                        {{ trim(($dep->codigo ?? '').'-'.($dep->nombre ?? ''), '-') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="lote_desde" class="{{ $colLabel }}">Lote / OT desde</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="lote_desde" id="lote_desde" class="form-control" value="{{ $lote_desde }}">
                        </div>
                        <label for="lote_hasta" class="{{ $colLabel }}">Lote / OT hasta</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="lote_hasta" id="lote_hasta" class="form-control" value="{{ $lote_hasta }}">
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex flex-wrap align-items-center">
                    <button type="submit" class="btn btn-primary btn-sm mr-2">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                    @if ($consultar)
                        <a href="{{ route('exportar_picking_pedido', request()->query()) }}" class="btn btn-success btn-sm mr-2" id="btn-excel-picking">
                            <i class="fa fa-file-excel"></i> Excel FRAGOLA
                        </a>
                        @if ($puede_facturar)
                            <button type="button" class="btn btn-warning btn-sm" id="btn-facturar-picking">
                                <i class="fa fa-file-invoice"></i> Facturar seleccionados
                            </button>
                        @endif
                    @endif
                </div>
            </form>
        </div>

        @if ($consultar)
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">L&iacute;neas ({{ $lineas->count() }})</h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <div id="datosfactura"
                        data-puntoventa="{{ $puntoventa_query }}"
                        data-tipotransaccion="{{ $tipotransaccion_query }}"
                        data-incoterm="{{ $incoterm_query }}"
                        data-formapago="{{ $formapago_query }}"
                        data-transporte="{{ $transporte_query }}">
                    </div>
                    <input type="hidden" id="csrf_token" value="{{ csrf_token() }}">
                    <input type="hidden" id="puntoventadefault_id" value="{{ optional($puntoventa_query->first())->id ?? '' }}">
                    <input type="hidden" id="puntoventaremitodefault_id" value="{{ optional($puntoventa_query->first())->id ?? '' }}">
                    <input type="hidden" id="tipotransacciondefault_id" value="{{ optional($tipotransaccion_query->first())->id ?? '' }}">

                    <table class="table table-sm table-bordered table-hover mb-0" id="tabla-picking-pedido">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:2%;">
                                    <input type="checkbox" id="check-all-picking" title="Seleccionar todos">
                                </th>
                                <th>Pedido</th>
                                <th>Cliente</th>
                                <th>Art&iacute;culo</th>
                                <th>Combinaci&oacute;n</th>
                                <th class="text-right">Cant.</th>
                                <th class="text-right">Precio</th>
                                <th>Lote / OT</th>
                                <th>Dep&oacute;sito</th>
                                <th>Marcado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lineas as $linea)
                                @php
                                    $depTxt = '';
                                    if ($linea->picking_deposito_id) {
                                        $dep = $deposito_query->firstWhere('id', $linea->picking_deposito_id);
                                        if ($dep) {
                                            $depTxt = trim(($dep->codigo ?? '').'-'.($dep->nombre ?? ''), '-');
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <input type="checkbox" class="check-picking-linea"
                                            value="{{ $linea->id }}"
                                            data-ot="{{ (int) ($linea->ot_id ?? 0) }}">
                                    </td>
                                    <td>{{ $linea->pedidos->codigo ?? $linea->pedido_id }}</td>
                                    <td>{{ $linea->pedidos->clientes->nombre ?? '' }}</td>
                                    <td>{{ $linea->articulos->sku ?? '' }}</td>
                                    <td>{{ $linea->combinaciones->nombre ?? '' }}</td>
                                    <td class="text-right">{{ number_format((float) $linea->cantidad, 0, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) $linea->precio, 2, ',', '.') }}</td>
                                    <td>{{ $linea->picking_lote_codigo }}</td>
                                    <td>{{ $depTxt }}</td>
                                    <td>{{ optional($linea->picking_at)->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">No hay l&iacute;neas pendientes con los filtros indicados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

@include('ventas.ordentrabajo_ferli.modalfacturaordentrabajo')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'picking-factura-overlay',
    'tituloId' => 'picking-factura-titulo',
    'subtituloId' => 'picking-factura-subtitulo',
    'titulo' => 'Emitiendo factura…',
    'subtitulo' => 'Puede demorar según ARCA. No cierre la página.',
])
@endsection
