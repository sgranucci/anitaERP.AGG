@php
    use App\Support\Compras\ListaprecioProveedorImportColumnasSupport as LpCols;

    $visualizar = ! empty($visualizar);
    $esAlta = ! isset($data) || ! $data || empty($data->id);
    $puedeImportarAlta = $esAlta && ! $visualizar && can('crear-listaprecio-proveedor', false);
    $puedeImportarEdicion = ! $esAlta && ! $visualizar && (
        can('actualizar-listaprecio-proveedor', false) || can('editar-listaprecio-proveedor', false)
    );
@endphp
@if ($puedeImportarAlta || $puedeImportarEdicion)
<div class="card card-outline card-success mb-3" id="lp-card-importar-excel">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-file-excel-o"></i> Importar precios desde Excel</h3>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">
            El sistema busca la fila de t&iacute;tulos (SKU, MATERIAL, precio, U$S/KG, dto, c&oacute;d. proveedor, etc.)
            en las primeras 15 filas. Si no hay encabezado, asume A = SKU, B = precio, C = % desc., D = c&oacute;d. proveedor.
            El art&iacute;culo se busca por SKU y, si hay proveedor elegido, por c&oacute;digo de art&iacute;culo proveedor.
            Los precios sin SKU en el maestro se cargan en un bloque informativo y no se graban.
        </p>

        <div class="form-row">
            <div class="form-group col-md-4 mb-2">
                <label for="fechavigencia_excel" class="control-label">Fecha de vigencia</label>
                <input type="date" name="fechavigencia_excel" id="fechavigencia_excel" class="form-control"
                       value="{{ old('fechavigencia_excel', date('Y-m-d')) }}">
            </div>
            <div class="form-group col-md-6 mb-2">
                <label for="archivoexcel" class="control-label">Archivo (.xlsx, .xls, .csv)</label>
                <input type="file" name="archivoexcel" id="archivoexcel" class="form-control" accept=".xlsx,.xls,.csv">
            </div>
            <div class="form-group col-md-2 mb-2 d-flex align-items-end">
                <button type="button" id="lp-btn-preview" class="btn btn-outline-primary btn-block" disabled>
                    <i class="fa fa-search"></i> Vista previa
                </button>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-2 mb-2">
                <label for="lp-col-sku" class="control-label">Columna SKU</label>
                <input type="text" name="col_sku" id="lp-col-sku" class="form-control form-control-sm"
                       value="{{ old('col_sku', LpCols::COL_SKU_DEFAULT) }}" placeholder="sku">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label for="lp-col-descripcion" class="control-label">Columna descripci&oacute;n</label>
                <input type="text" name="col_descripcion" id="lp-col-descripcion" class="form-control form-control-sm"
                       value="{{ old('col_descripcion', LpCols::COL_DESCRIPCION_DEFAULT) }}" placeholder="descripcion">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label for="lp-col-precio" class="control-label">Columna precio</label>
                <input type="text" name="col_precio" id="lp-col-precio" class="form-control form-control-sm"
                       value="{{ old('col_precio', LpCols::COL_PRECIO_DEFAULT) }}" placeholder="precio">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label for="lp-col-descuento" class="control-label">Columna % desc.</label>
                <input type="text" name="col_descuento" id="lp-col-descuento" class="form-control form-control-sm"
                       value="{{ old('col_descuento', LpCols::COL_DESCUENTO_DEFAULT) }}" placeholder="descuento">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label for="lp-col-codigo-proveedor" class="control-label">C&oacute;d. art. proveedor</label>
                <input type="text" name="col_codigo_proveedor" id="lp-col-codigo-proveedor" class="form-control form-control-sm"
                       value="{{ old('col_codigo_proveedor', LpCols::COL_CODIGO_PROVEEDOR_DEFAULT) }}" placeholder="codigo_proveedor">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label for="lp-fila-encabezado" class="control-label">Fila encabezado</label>
                <input type="number" name="fila_encabezado" id="lp-fila-encabezado" class="form-control form-control-sm"
                       min="1" max="50" value="{{ old('fila_encabezado') }}" placeholder="Auto">
            </div>
        </div>

        <input type="hidden" name="hoja_indice" id="lp-hoja-indice" value="{{ old('hoja_indice', 1) }}">

        <div class="form-row d-none" id="lp-panel-hoja-excel">
            <div class="form-group col-md-6 mb-2">
                <label for="lp-hoja-indice-select" class="control-label">Hoja a importar</label>
                <select id="lp-hoja-indice-select" class="form-control form-control-sm" aria-label="Elegir hoja del Excel"></select>
                <small class="form-text text-muted" id="lp-hoja-indice-ayuda">Elija la pesta&ntilde;a del Excel con los precios.</small>
            </div>
        </div>

        <div id="lp-panel-preview" class="card border-primary mb-3" style="display:none;">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <strong><i class="fa fa-table"></i> Vista previa del archivo</strong>
                <span id="lp-preview-estado" class="badge badge-secondary">&mdash;</span>
            </div>
            <div class="card-body p-2" id="lp-preview-contenido">
                <p class="text-muted small mb-0">Seleccione un archivo para analizar columnas y filas.</p>
            </div>
        </div>

        <div class="text-right">
            <button type="button" id="lp-btn-cargar-grilla" class="btn btn-primary" disabled>
                <i class="fa fa-list"></i> Cargar renglones en la grilla
            </button>
            @if ($puedeImportarEdicion)
                <button type="button" id="lp-btn-importar-grabar" class="btn btn-success ml-2" disabled>
                    <i class="fa fa-upload"></i> Importar y grabar
                </button>
            @endif
        </div>
    </div>
</div>
@endif
