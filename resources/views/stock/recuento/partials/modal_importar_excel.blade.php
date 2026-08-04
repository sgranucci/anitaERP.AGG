@php
    $importUrl = $importUrl ?? route('importar_recuento_preview');
    $modoPreview = $modoPreview ?? ! isset($recuento);
@endphp

<div class="modal fade" id="modal-importar-recuento-excel" tabindex="-1" role="dialog" aria-labelledby="modal-importar-recuento-excel-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="form-importar-recuento-excel" action="{{ $importUrl }}" method="POST" enctype="multipart/form-data" data-preview="{{ $modoPreview ? '1' : '0' }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-importar-recuento-excel-titulo">
                        <i class="fa fa-file-excel-o"></i> Importar líneas desde Excel
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="importar-recuento-excel-error" class="alert alert-danger d-none"></div>
                    <p class="text-muted">
                        Suba un archivo Excel/CSV con encabezados en la primera fila.
                        Indique el nombre de cada columna según su planilla (no distingue mayúsculas; espacios se convierten en guión bajo).
                    </p>
                    @if ($modoPreview)
                        <p class="text-info small mb-3">
                            <i class="fa fa-info-circle"></i> Seleccione el depósito antes de importar; las líneas se cargarán en la grilla y se guardarán al confirmar el recuento.
                        </p>
                    @endif
                    <div class="form-group row">
                        <label class="col-lg-2 control-label requerido">Archivo</label>
                        <div class="col-lg-10">
                            <input type="file" name="archivo" class="form-control" accept=".xlsx,.xls,.csv" required>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-2 control-label requerido">Columna SKU</label>
                        <div class="col-lg-3">
                            <input type="text" name="col_sku" class="form-control" value="{{ old('col_sku', 'sku') }}" required>
                        </div>
                        <label class="col-lg-3 control-label requerido">Columna cantidad contada</label>
                        <div class="col-lg-3">
                            <input type="text" name="col_cantidad" class="form-control" value="{{ old('col_cantidad', 'cantidad_contada') }}" required>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-2 control-label">Columna detalle</label>
                        <div class="col-lg-3">
                            <input type="text" name="col_detalle" class="form-control" value="{{ old('col_detalle', 'detalle') }}">
                        </div>
                        <div class="col-lg-7">
                            <span class="form-text text-muted mb-0">Opcional.</span>
                        </div>
                    </div>
                    <div class="form-group row mb-0">
                        <label class="col-lg-2 control-label">Columna color</label>
                        <div class="col-lg-3">
                            <input type="text" name="col_color" class="form-control" value="{{ old('col_color', 'color') }}">
                        </div>
                        <label class="col-lg-2 control-label">Columna talle</label>
                        <div class="col-lg-3">
                            <input type="text" name="col_talle" class="form-control" value="{{ old('col_talle', 'talle') }}">
                        </div>
                        <div class="col-lg-2">
                            <span class="form-text text-muted mb-0">Obligatorias si los art&iacute;culos manejan color/talle (nombre o ID).</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-importar-recuento-excel-submit">
                        <i class="fa fa-upload"></i> Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
