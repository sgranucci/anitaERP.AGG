@extends("theme.$theme.layout")
@section('titulo')
    Reporte stock por OT
@endsection

@section('styles')
<style>
    .repstockot-fichas { display: flex; flex-wrap: wrap; gap: 8px; }
    .repstockot-ficha {
        min-width: 7.5rem;
        padding: 0.65rem 1rem;
        border: 1px solid #aeb6bf;
        border-radius: 0.35rem;
        background: #fff;
        color: #17202a;
        font-weight: 600;
        line-height: 1.2;
        cursor: pointer;
    }
    .repstockot-ficha.is-activa {
        background: #d6eaf8;
        border-color: #2471a3;
        color: #1b4f72;
        box-shadow: inset 0 0 0 1px #2471a3;
    }
    .repstockot-ficha:focus {
        outline: 2px solid #2471a3;
        outline-offset: 2px;
    }
</style>
@endsection

@section("scripts")
<script>
    window.REPORTE_FILTROS_RESULTADO = {
        formId: 'form-general',
        panelId: 'panel-filtros-repstockot',
    };
</script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/includes/listado-filtros.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/includes/reporte-filtros-resultado.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/includes/reporte-filtros-resultado.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/stock/depmae/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/linea/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/stock/linea/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/categoria/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/stock/categoria/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/repstockot/reporte.js') }}?v={{ filemtime(public_path('assets/pages/scripts/stock/repstockot/reporte.js')) }}"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'repstockot-overlay',
    'tituloId' => 'repstockot-overlay-titulo',
    'subtituloId' => 'repstockot-overlay-subtitulo',
    'titulo' => 'Consultando stock por OT…',
    'subtitulo' => 'Puede demorar según los filtros. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Stock por OT</h3>
                <div class="card-tools">
                    <a href="{{ route('rep_stockot') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form action="{{ route('rep_stockot') }}" id="form-general" class="form-horizontal form--label-right mb-0" method="GET" autocomplete="off">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Consultá el stock y, en la vista previa, abrí cada orden de trabajo.
                        <strong>Filtrar resultado</strong> acota el listado sin volver a calcular.
                        Pdf y Excel salen con ese recorte, en el formato de siempre.
                    </p>
                    @include('stock.repstockot.form')
                    <div class="form-group row mb-0">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar-repstockot">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
                @if ($consultado ?? false)
                    @include('includes.reportes.filtros_resultado_texto', [
                        'filtros' => $filtros,
                        'camposTexto' => \App\Support\Stock\ReporteStockOtListadoFiltros::CAMPOS_TEXTO,
                        'tiene_filtros_texto' => $tiene_filtros_texto ?? false,
                        'limpiar_filtros_texto_url' => $limpiar_filtros_texto_url ?? null,
                        'formId' => 'form-general',
                        'panelId' => 'panel-filtros-repstockot',
                        'campoDefault' => 'sku',
                        'placeholder' => 'Buscar artículo, OT, depósito, situación…',
                    ])
                @endif
            </form>
            @if ($consultado ?? false)
                <div class="card-body border-top pt-3">
                    <p class="small mb-2">
                        {{ $subtitulo ?? '' }}
                        · <strong>Filas:</strong> {{ $total_filas ?? 0 }}
                        · <strong>Pares:</strong> {{ number_format((float) ($total_pares ?? 0), 0, ',', '.') }}
                    </p>
                    <div class="mb-2" id="bloque-export-repstockot">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_repstockot',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                    </div>
                    @include('stock.repstockot.partials.tabla_datos', [
                        'filas' => $filas,
                        'medidas_columnas' => $medidas_columnas ?? [],
                        'imprimefoto' => 'SIN_FOTO',
                        'es_movimientos' => ($filtros['apertura'] ?? '') === 'MOVIMIENTOS',
                        'con_herramientas' => true,
                    ])
                    <div class="mt-2">
                        {{ $filas->appends($filtrosQuery ?? [])->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.stock.modalconsultadeposito')
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultalinea')
@include('includes.stock.modalconsultacategoria')
@endsection
