@extends("theme.$theme.layout")
@section('titulo')
    Importar novedades
@endsection

@section('scripts')
<script>
window.novedadImportPreviewUrl = @json(route('preview_importar_novedad_sueldos'));
</script>
<script src="{{ asset('assets/pages/scripts/sueldos/novedad/importar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/sueldos/novedad/importar.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Sueldos\NovedadSueldosImportColumnasSupport;
    $camposMeta = NovedadSueldosImportColumnasSupport::metaCampos();
    $unaEmpresa = ($empresa_query ?? collect())->count() === 1;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Importar novedades (Excel / CSV)</h3>
                <div class="card-tools">
                    <a href="{{ route('plantilla_importar_novedad_sueldos') }}" class="btn btn-outline-info btn-sm mr-1">
                        <i class="fa fa-download"></i> Plantilla
                    </a>
                    <a href="{{ route('consultar_novedad_sueldos') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('procesar_importar_novedad_sueldos') }}" id="form-importar-novedad" method="POST" enctype="multipart/form-data" class="form-horizontal" autocomplete="off">
                @csrf
                <div class="card-body">
                    <p class="text-muted mb-3">
                        La fila de títulos se detecta sola. Si el Excel usa otros nombres, asignalos en el mapeo.
                        Con fecha desde, la novedad se repite en cada corrida mientras esté vigente.
                        Se graban solo las filas en verde.
                    </p>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => old('empresa_id'),
                        'col_label' => 'col-lg-3 text-right pr-2',
                        'col_input' => 'col-lg-4',
                        'required' => false,
                        'permite_vacio' => ! $unaEmpresa,
                        'opcion_vacia' => 'Según el archivo',
                    ])
                    <div class="form-group row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-8 text-muted small">
                            Si elegís empresa acá, aplica a las filas que no traigan empresa. Si el archivo trae empresa, esa manda.
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fila_encabezado" class="col-lg-3 control-label text-right pr-2">Fila encabezado</label>
                        <div class="col-lg-2">
                            <input type="number" name="fila_encabezado" id="fila_encabezado" class="form-control form-control-sm" min="1" max="50" value="{{ old('fila_encabezado', '') }}" placeholder="Auto">
                        </div>
                        <div class="col-lg-5 col-form-label text-muted small">Vacío = detectar automáticamente</div>
                    </div>

                    <div class="form-group row">
                        <label for="archivo" class="col-lg-3 control-label text-right pr-2 requerido">Archivo</label>
                        <div class="col-lg-5">
                            <input type="file" name="archivo" id="archivo" class="form-control form-control-sm" accept=".xlsx,.xls,.csv,.txt" required>
                        </div>
                        <div class="col-lg-3">
                            <button type="button" id="btn-preview-import-novedad" class="btn btn-outline-primary btn-sm" disabled>
                                <i class="fa fa-search"></i> Vista previa
                            </button>
                        </div>
                    </div>

                    <input type="hidden" name="hoja_indice" id="hoja_indice" value="{{ old('hoja_indice', 1) }}">
                    <div class="form-group row d-none" id="panel-hoja-excel">
                        <label for="hoja_indice_select" class="col-lg-3 control-label text-right pr-2">Hoja</label>
                        <div class="col-lg-4">
                            <select id="hoja_indice_select" class="form-control form-control-sm"></select>
                        </div>
                    </div>

                    <div class="border rounded p-2 mb-3 bg-light" id="panel-mapeo-columnas">
                        <div class="small font-weight-bold mb-2">Mapeo de columnas</div>
                        <div class="row">
                            @foreach ($camposMeta as $campo => $meta)
                                <div class="col-md-4 col-lg-3 mb-2">
                                    <label class="small mb-0" for="map_{{ $campo }}">
                                        {{ $meta['label'] }}
                                        @if ($meta['requerido'])
                                            <span class="text-danger">*</span>
                                        @endif
                                    </label>
                                    <select name="mapping[{{ $campo }}]" id="map_{{ $campo }}" class="form-control form-control-sm map-campo" data-campo="{{ $campo }}">
                                        <option value="">—</option>
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div id="panel-preview-import-novedad" class="card border-primary mb-0" style="display:none;">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <strong><i class="fa fa-table"></i> Vista previa</strong>
                            <span id="preview-import-novedad-estado" class="badge badge-secondary">—</span>
                        </div>
                        <div class="card-body p-2" id="preview-import-novedad-contenido">
                            <p class="text-muted small mb-0">Seleccioná un archivo.</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" id="btn-confirmar-import-novedad" class="btn btn-success" disabled>
                        <i class="fa fa-upload"></i> Confirmar importación
                    </button>
                    <a href="{{ route('consultar_novedad_sueldos') }}" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'novedad-import-overlay',
    'tituloId' => 'novedad-import-overlay-titulo',
    'subtituloId' => 'novedad-import-overlay-subtitulo',
    'titulo' => 'Importando novedades…',
    'subtitulo' => 'Puede demorar según la cantidad de filas. No cierre la página.',
])
@endsection
