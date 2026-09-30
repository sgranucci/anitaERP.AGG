@extends("theme.$theme.layout")
@section('titulo')
    Historial de devoluciones
@endsection
@section('scripts')
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/historial_devolucion/export.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/facturacion_local/historial_devolucion/export.js')) ?: time() }}" type="text/javascript"></script>
@endsection
@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'historial-devolucion-overlay',
    'tituloId' => 'historial-devolucion-titulo',
    'subtituloId' => 'historial-devolucion-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo. Pulse Esc para cerrar este aviso.',
])
@php
    $f = $filtros ?? [];
    $consultado = (int) ($f['consultar'] ?? 0) === 1;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Historial de devoluciones</h3>
                <div class="card-tools ml-auto">
                    @if (can('listar-motivo-devolucion-facturacion-local', false))
                        <a href="{{ route('facturacion_local_motivos_devolucion') }}" class="btn btn-outline-light btn-sm mr-1">Motivos</a>
                    @endif
                    <a href="{{ route('facturacion_local_historial_devoluciones') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('facturacion_local_historial_devoluciones') }}" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Cada devolución del POS, de la nota de crédito y de Tienda Nube queda acá con el motivo y si el par volvió al stock.
                    </p>
                    <div class="form-group row">
                        <label for="fecha_desde" class="col-lg-2 control-label text-right pr-2">Desde</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" value="{{ $f['fecha_desde'] ?? '' }}">
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 control-label text-right pr-2">Hasta</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" value="{{ $f['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="origen" class="col-lg-2 control-label text-right pr-2">Origen</label>
                        <div class="col-lg-3">
                            <select name="origen" id="origen" class="form-control">
                                <option value="">Todos</option>
                                @foreach ($origenes as $clave => $etiqueta)
                                    <option value="{{ $clave }}" @selected(($f['origen'] ?? '') === $clave)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label for="vuelve_stock" class="col-lg-2 control-label text-right pr-2">Stock</label>
                        <div class="col-lg-3">
                            <select name="vuelve_stock" id="vuelve_stock" class="form-control">
                                <option value="" @selected(($f['vuelve_stock'] ?? '') === '')>Todos</option>
                                <option value="1" @selected(($f['vuelve_stock'] ?? '') === '1')>Vuelve al stock</option>
                                <option value="0" @selected(($f['vuelve_stock'] ?? '') === '0')>No entra al stock</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="motivo" class="col-lg-2 control-label text-right pr-2">Motivo</label>
                        <div class="col-lg-3">
                            <input type="text" name="motivo" id="motivo" class="form-control" value="{{ $f['motivo'] ?? '' }}" placeholder="Nombre o código">
                        </div>
                        <label for="local" class="col-lg-2 control-label text-right pr-2">Local</label>
                        <div class="col-lg-3">
                            <input type="text" name="local" id="local" class="form-control" value="{{ $f['local'] ?? '' }}" placeholder="Código o nombre">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="texto" class="col-lg-2 control-label text-right pr-2">Texto</label>
                        <div class="col-lg-6">
                            <input type="text" name="texto" id="texto" class="form-control" value="{{ $f['texto'] ?? '' }}" placeholder="SKU, descripción o comprobante">
                        </div>
                        <div class="col-lg-2">
                            <button type="submit" class="btn btn-primary">Consultar</button>
                        </div>
                    </div>
                </div>
            </form>
            @if ($consultado)
                <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                    <div class="mb-1" id="historial-devolucion-export">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_historial_devolucion',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                    </div>
                    <div class="small mb-1 text-md-right">
                        <span class="text-muted">Totales del filtro:</span>
                        {{ (int) ($totales['cantidad'] ?? 0) }} devoluciones
                        · Importe <strong>${{ number_format((float) ($totales['importe'] ?? 0), 2, ',', '.') }}</strong>
                        · Sin stock <strong>{{ (int) ($totales['sin_stock'] ?? 0) }}</strong>
                    </div>
                </div>
                @php
                    $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas ?? collect());
                @endphp
                @if (count($logosVista) > 0)
                    <div class="border-bottom px-3 py-2 d-flex flex-wrap align-items-center">
                        @foreach ($logosVista as $logo)
                            <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                        @endforeach
                    </div>
                @endif
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                        @include('ventas.facturacion_local.historial_devolucion.partials.tabla_datos', [
                            'presentacion' => 'pantalla',
                            'datas' => $datas,
                        ])
                    </table>
                </div>
                <div class="card-footer">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <small class="text-muted">
                            @if ($datas && $datas->total() > 0)
                                Mostrando {{ $datas->firstItem() }}–{{ $datas->lastItem() }} de {{ $datas->total() }}
                            @else
                                Sin devoluciones para este filtro.
                            @endif
                        </small>
                        @if ($datas)
                            {{ $datas->appends($filtrosQuery ?? [])->links() }}
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
