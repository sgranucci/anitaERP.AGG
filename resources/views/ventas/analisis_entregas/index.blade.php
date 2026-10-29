@extends("theme.$theme.layout")
@section('titulo')
    Análisis de entregas
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/ventas/cliente/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/vendedor/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/ventas/vendedor/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/analisis_entregas/filtro.js') }}?v={{ filemtime(public_path('assets/pages/scripts/ventas/analisis_entregas/filtro.js')) }}"></script>
@endsection

@section('contenido')
@php
    $f = $filtros;
@endphp
<div class="row">
    <div class="col-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Análisis de entregas y pendientes</h3>
                <div class="card-tools">
                    <a href="{{ route('analisis_entregas') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('analisis_entregas') }}" id="form-analisis-entregas" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <input type="hidden" name="empresa_id" id="empresa_id" value="{{ session('empresa_id') }}">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Mismo criterio que el listado Anita de entregas: cantidades, fason, atraso contra la fecha parámetro y estado del ítem.
                        «Todos» deja afuera anulados y suspendidos.
                        Vacío en cliente, artículo o vendedor no filtra.
                        Si hace falta ordenar por agrupación, zona, marca o línea, se puede sumar.
                    </p>

                    <div class="form-group row">
                        <label for="orden" class="col-lg-2 control-label text-right pr-2">Orden</label>
                        <div class="col-lg-4">
                            <select name="orden" id="orden" class="form-control">
                                @foreach ($ordenes as $valor => $texto)
                                    <option value="{{ $valor }}" @selected(($f['orden'] ?? '') === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label for="modo" class="col-lg-2 control-label text-right pr-2">Presentación</label>
                        <div class="col-lg-4">
                            <select name="modo" id="modo" class="form-control">
                                @foreach ($modos as $valor => $texto)
                                    <option value="{{ $valor }}" @selected(($f['modo'] ?? '') === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="estado" class="col-lg-2 control-label text-right pr-2">Estado</label>
                        <div class="col-lg-4">
                            <select name="estado" id="estado" class="form-control">
                                @foreach ($estados as $valor => $texto)
                                    <option value="{{ $valor }}" @selected(($f['estado'] ?? '') === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label for="situacion" class="col-lg-2 control-label text-right pr-2">Ítems</label>
                        <div class="col-lg-4">
                            <select name="situacion" id="situacion" class="form-control">
                                @foreach ($situaciones as $valor => $texto)
                                    <option value="{{ $valor }}" @selected(($f['situacion'] ?? '') === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fecha_pedido_desde" class="col-lg-2 control-label text-right pr-2">Pedido desde</label>
                        <div class="col-lg-4">
                            <input type="date" name="fecha_pedido_desde" id="fecha_pedido_desde" class="form-control" value="{{ $f['fecha_pedido_desde'] ?? '' }}">
                        </div>
                        <label for="fecha_pedido_hasta" class="col-lg-2 control-label text-right pr-2">Pedido hasta</label>
                        <div class="col-lg-4">
                            <input type="date" name="fecha_pedido_hasta" id="fecha_pedido_hasta" class="form-control" value="{{ $f['fecha_pedido_hasta'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fecha_entrega_desde" class="col-lg-2 control-label text-right pr-2">Entrega desde</label>
                        <div class="col-lg-4">
                            <input type="date" name="fecha_entrega_desde" id="fecha_entrega_desde" class="form-control" value="{{ $f['fecha_entrega_desde'] ?? '' }}">
                        </div>
                        <label for="fecha_entrega_hasta" class="col-lg-2 control-label text-right pr-2">Entrega hasta</label>
                        <div class="col-lg-4">
                            <input type="date" name="fecha_entrega_hasta" id="fecha_entrega_hasta" class="form-control" value="{{ $f['fecha_entrega_hasta'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fecha_parametro" class="col-lg-2 control-label text-right pr-2">Fecha parámetro</label>
                        <div class="col-lg-4">
                            <input type="date" name="fecha_parametro" id="fecha_parametro" class="form-control" value="{{ $f['fecha_parametro'] ?? '' }}">
                            <small class="text-muted">El atraso es la fecha de entrega menos este día.</small>
                        </div>
                        <label class="col-lg-2 control-label text-right pr-2">Sucursal</label>
                        <div class="col-lg-4">
                            <div class="form-row">
                                <div class="col">
                                    <input type="number" name="sucursal_desde" class="form-control" placeholder="Desde" value="{{ $f['sucursal_desde'] ?? '' }}">
                                </div>
                                <div class="col">
                                    <input type="number" name="sucursal_hasta" class="form-control" placeholder="Hasta" value="{{ $f['sucursal_hasta'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2">Cliente</label>
                        <div class="col-lg-4">
                            <input type="hidden" id="cliente_id" name="cliente_id" value="{{ $cliente['id'] ?: '' }}">
                            <div class="input-group">
                                <input type="text" class="form-control codigocliente" id="codigocliente" value="{{ $cliente['codigo'] }}" placeholder="Código" autocomplete="off" title="Enter valida; F1 consulta">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary consultacliente" title="Consulta clientes (F1)"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                            <input type="text" class="form-control mt-1" id="nombrecliente" value="{{ $cliente['nombre'] }}" readonly>
                        </div>
                        <label class="col-lg-2 control-label text-right pr-2">Vendedor</label>
                        <div class="col-lg-4 tm-vendedor-campo">
                            <input type="hidden" class="vendedor_id" id="vendedor_id" name="vendedor_id" value="{{ $vendedor['id'] ?: '' }}">
                            <div class="input-group">
                                <input type="text" class="form-control codigovendedor" id="codigovendedor" value="{{ $vendedor['codigo'] }}" placeholder="Código" autocomplete="off" title="Enter valida; F1 consulta">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary consultavendedor" title="Consulta vendedores (F1)"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                            <input type="text" class="form-control mt-1 nombrevendedor" id="nombrevendedor" value="{{ $vendedor['nombre'] }}" readonly>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2">Artículo</label>
                        <div class="col-lg-4 tm-articulo-campo">
                            <input type="hidden" class="articulo_id" name="articulo_id" value="{{ $articulo['id'] ?: '' }}">
                            <div class="input-group">
                                <input type="text" class="form-control codigoarticulo" value="{{ $articulo['codigo'] }}" placeholder="SKU" autocomplete="off" title="Enter valida; F1 consulta">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary consultaarticulo" title="Consulta artículos (F1)"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                            <input type="text" class="form-control mt-1 descripcionarticulo" value="{{ $articulo['nombre'] }}" readonly>
                        </div>
                        <div class="col-lg-6 text-right">
                            <button type="submit" class="btn btn-primary" id="btn-consultar">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado)
                <div class="card-body pt-0">
                    <div class="d-flex flex-wrap align-items-center mb-2">
                        <div class="small text-muted mr-auto">{{ $subtitulo }}<br>{{ \App\Support\Ventas\AnalisisEntregasReporte::resumenTexto($resumen) }}</div>
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_analisis_entregas',
                            'queryparams' => $filtrosQuery,
                        ])
                    </div>
                    @if (! empty($logosCabecera))
                        <div class="mb-2">
                            @foreach ($logosCabecera as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] ?? '' }}" style="max-height:42px;margin-right:8px;">
                            @endforeach
                        </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
                            <thead>
                                <tr style="background:#85C1E9;color:#17202A;">
                                    @include('ventas.analisis_entregas.partials.columnas')
                                </tr>
                            </thead>
                            <tbody>
                                @include('ventas.analisis_entregas.partials.filas', [
                                    'filas' => $filasVista,
                                    'puede_ver_pedido' => $puede_ver_pedido,
                                    'puede_ver_cliente' => $puede_ver_cliente,
                                    'puede_ver_articulo' => $puede_ver_articulo,
                                ])
                            </tbody>
                        </table>
                    </div>
                    @if ($filas)
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="small text-muted">
                                @if ($filas->total() > 0)
                                    {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
                                @endif
                            </span>
                            {{ $filas->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'analisis-entregas-overlay',
    'tituloId' => 'analisis-entregas-titulo',
    'subtituloId' => 'analisis-entregas-subtitulo',
    'titulo' => 'Consultando…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
@include('includes.ventas.modalconsultacliente')
@include('includes.ventas.modalconsultavendedor')
@include('includes.stock.modalconsultaarticulo')
@endsection
