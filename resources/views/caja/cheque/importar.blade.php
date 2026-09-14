@extends("theme.$theme.layout")
@section('titulo')
    Importar cheques
@endsection

@section('scripts')
<script>
window.chequeImportPreviewUrl = @json(route('preview_importar_cheque'));
</script>
<script src="{{ asset('assets/pages/scripts/caja/cheque/importar.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Caja\ChequeImportColumnasSupport;
    $camposMeta = ChequeImportColumnasSupport::metaCampos();
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Ingreso masivo CHT (CSV / Excel)</h3>
                <div class="card-tools">
                    <a href="{{ route('cheque') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a cheques
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_importar_cheque') }}" id="form-importar-cheque" class="form-horizontal" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => old('empresa_id'),
                        'col_label' => 'col-lg-3 text-right pr-2',
                        'col_input' => 'col-lg-4',
                    ])

                    <div class="form-group row">
                        <label for="fila_encabezado" class="col-lg-3 control-label text-right pr-2">Fila encabezado</label>
                        <div class="col-lg-2">
                            <input type="number" name="fila_encabezado" id="fila_encabezado" class="form-control form-control-sm" min="1" max="50" value="{{ old('fila_encabezado', '') }}" placeholder="Auto">
                        </div>
                        <div class="col-lg-5 col-form-label text-muted small">Vac&iacute;o = detectar autom&aacute;ticamente</div>
                    </div>

                    <div class="form-group row">
                        <label for="file" class="col-lg-3 control-label text-right pr-2 requerido">Archivo</label>
                        <div class="col-lg-5">
                            <input type="file" name="file" id="file" class="form-control form-control-sm" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="col-lg-3">
                            <button type="button" id="btn-preview-import-cheque" class="btn btn-outline-primary btn-sm" disabled>
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
                        <div class="row" id="mapeo-columnas-campos">
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

                    <div id="panel-preview-import-cheque" class="card border-primary mb-0" style="display:none;">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <strong><i class="fa fa-table"></i> Vista previa</strong>
                            <span id="preview-import-cheque-estado" class="badge badge-secondary">—</span>
                        </div>
                        <div class="card-body p-2" id="preview-import-cheque-contenido">
                            <p class="text-muted small mb-0">Seleccione un archivo.</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" id="btn-confirmar-import-cheque" class="btn btn-success" disabled>
                        <i class="fa fa-upload"></i> Confirmar importaci&oacute;n
                    </button>
                    <a href="{{ route('cheque') }}" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
