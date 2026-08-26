@php
    $importUrl = $importUrl ?? route('importar_recuento_preview');
    $previewUrl = $previewUrl ?? route('importar_recuento_preview');
    $modoPreview = $modoPreview ?? ! isset($recuento);
    $depositoFijoId = isset($recuento) ? (int) $recuento->deposito_id : 0;
@endphp

<div class="modal fade" id="modal-importar-recuento-excel" tabindex="-1" role="dialog" aria-labelledby="modal-importar-recuento-excel-titulo" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <form id="form-importar-recuento-excel"
                action="{{ $importUrl }}"
                method="POST"
                enctype="multipart/form-data"
                data-preview="{{ $modoPreview ? '1' : '0' }}"
                data-preview-url="{{ $previewUrl }}">
                @csrf
                @if ($depositoFijoId > 0)
                    <input type="hidden" name="deposito_id" id="importar-recuento-deposito-id" value="{{ $depositoFijoId }}">
                @endif
                <input type="hidden" name="hoja_indice" id="importar-recuento-hoja-indice" value="{{ old('hoja_indice', 1) }}">
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
                    <p class="text-muted small mb-2">
                        Suba un Excel/CSV. El sistema busca la fila de encabezados (aunque haya un título arriba)
                        y reconoce alias habituales: <code>sku</code>/<code>codigo</code>,
                        <code>cantidad_contada</code>/<code>contado</code>, <code>color</code>, <code>talle</code>.
                    </p>
                    @if ($modoPreview)
                        <p class="text-info small mb-3">
                            <i class="fa fa-info-circle"></i> Seleccione el depósito para ver el saldo y cargar las líneas en la grilla. Se guardan al confirmar el recuento.
                        </p>
                    @endif
                    <div class="form-group row">
                        <label class="col-lg-2 control-label requerido">Archivo</label>
                        <div class="col-lg-7">
                            <input type="file" name="archivo" id="importar-recuento-archivo" class="form-control" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="col-lg-3">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-preview-recuento-excel" disabled>
                                <i class="fa fa-search"></i> Vista previa
                            </button>
                        </div>
                    </div>
                    <div class="form-group row d-none" id="panel-hoja-recuento-excel">
                        <label class="col-lg-2 control-label">Hoja</label>
                        <div class="col-lg-4">
                            <select id="importar-recuento-hoja-select" class="form-control form-control-sm" aria-label="Elegir hoja del Excel"></select>
                        </div>
                        <div class="col-lg-6 col-form-label text-muted small" id="importar-recuento-hoja-ayuda">
                            Elija la pestaña del Excel con el recuento.
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
                        <label class="col-lg-2 control-label">Fila encabezado</label>
                        <div class="col-lg-2">
                            <input type="number" name="fila_encabezado" id="importar-recuento-fila-encabezado" class="form-control" min="1" max="50" value="{{ old('fila_encabezado') }}" placeholder="Auto">
                        </div>
                        <div class="col-lg-3">
                            <span class="form-text text-muted mb-0">Vacío = detectar (título arriba, encabezado más abajo).</span>
                        </div>
                    </div>
                    <div class="form-group row mb-2">
                        <label class="col-lg-2 control-label">Columna color</label>
                        <div class="col-lg-3">
                            <input type="text" name="col_color" class="form-control" value="{{ old('col_color', 'color') }}">
                        </div>
                        <label class="col-lg-2 control-label">Columna talle</label>
                        <div class="col-lg-3">
                            <input type="text" name="col_talle" class="form-control" value="{{ old('col_talle', 'talle') }}">
                        </div>
                        <div class="col-lg-2">
                            <span class="form-text text-muted mb-0">Obligatorias si el artículo maneja color/talle. El guión del Excel exportado se ignora.</span>
                        </div>
                    </div>

                    <div id="panel-preview-import-recuento" class="card border-primary mb-0" style="display:none;">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <strong><i class="fa fa-table"></i> Vista previa del archivo</strong>
                            <span id="preview-import-recuento-estado" class="badge badge-secondary">—</span>
                        </div>
                        <div class="card-body p-2" id="preview-import-recuento-contenido">
                            <p class="text-muted small mb-0">Seleccione un archivo para analizar columnas y filas.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-importar-recuento-excel-submit" disabled>
                        <i class="fa fa-upload"></i> Cargar líneas en la grilla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
