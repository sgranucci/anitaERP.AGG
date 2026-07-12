@php
    use App\Support\Stock\PrecioImportColumnasSupport;

    $formatoImport = old('formato', PrecioImportColumnasSupport::FORMATO_SIMPLE);
    $listaprecioIdImport = old('listaprecio_id', '');
    $fechaVigenciaImport = old('fechavigencia', date('Y-m-d'));
    try {
        $fechaVigenciaImport = \Carbon\Carbon::parse($fechaVigenciaImport)->format('Y-m-d');
    } catch (\Throwable $e) {
        $fechaVigenciaImport = date('Y-m-d');
    }
@endphp

<div class="form-group row">
    <label for="fechavigencia" class="col-lg-3 col-form-label requerido">Fecha de vigencia</label>
    <div class="col-lg-3">
        <input type="date" name="fechavigencia" id="fechavigencia" class="form-control"
            value="{{ $fechaVigenciaImport }}" required />
    </div>
</div>

<div class="form-group row">
    <label for="moneda_id" class="col-lg-3 col-form-label requerido">Moneda</label>
    <div class="col-lg-3">
        <select name="moneda_id" id="moneda_id" class="form-control" required>
            <option value="">-- Elija moneda --</option>
            @foreach ($moneda_query as $moneda)
                <option value="{{ $moneda->id }}"
                    @selected((int) old('moneda_id', 1) === (int) $moneda->id)>
                    {{ $moneda->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="formato" class="col-lg-3 col-form-label requerido">Formato del Excel</label>
    <div class="col-lg-6">
        <select name="formato" id="formato" class="form-control" required>
            <option value="{{ PrecioImportColumnasSupport::FORMATO_SIMPLE }}"
                @selected($formatoImport === PrecioImportColumnasSupport::FORMATO_SIMPLE)>
                Simple — SKU, descripci&oacute;n (opcional) y un precio por fila
            </option>
            <option value="{{ PrecioImportColumnasSupport::FORMATO_LISTAS }}"
                @selected($formatoImport === PrecioImportColumnasSupport::FORMATO_LISTAS)>
                M&uacute;ltiples listas — columnas L_&lt;c&oacute;digo lista&gt;
            </option>
        </select>
    </div>
</div>

<div id="panel-import-simple" class="border rounded p-3 mb-3 bg-light" style="{{ $formatoImport === PrecioImportColumnasSupport::FORMATO_LISTAS ? 'display:none' : '' }}">
    <h6 class="mb-3">Configuraci&oacute;n simple (valores por defecto)</h6>

    <div class="form-group row">
        <label for="listaprecio_id" class="col-lg-3 col-form-label requerido">Lista de precios destino</label>
        <div class="col-lg-6">
            <select name="listaprecio_id" id="listaprecio_id" class="form-control">
                <option value="">-- Elija lista --</option>
                @foreach ($listaprecio_query as $lista)
                    <option value="{{ $lista->id }}"
                        @selected((string) $listaprecioIdImport === (string) $lista->id)>
                        {{ $lista->codigo }} — {{ $lista->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group row">
        <label for="col_sku" class="col-lg-3 col-form-label requerido">Columna SKU</label>
        <div class="col-lg-3">
            <input type="text" name="col_sku" id="col_sku" class="form-control"
                value="{{ old('col_sku', PrecioImportColumnasSupport::COL_SKU_DEFAULT) }}"
                placeholder="sku" />
        </div>
        <div class="col-lg-6 col-form-label text-muted small">
            Encabezado en fila 1. Reconoce t&iacute;tulos compuestos (ej. <code>PLU o SKU</code>, <code>Nombre del producto</code>).
        </div>
    </div>

    <div class="form-group row">
        <label for="col_descripcion" class="col-lg-3 col-form-label">Columna descripci&oacute;n</label>
        <div class="col-lg-3">
            <input type="text" name="col_descripcion" id="col_descripcion" class="form-control"
                value="{{ old('col_descripcion', PrecioImportColumnasSupport::COL_DESCRIPCION_DEFAULT) }}"
                placeholder="descripcion" />
        </div>
        <div class="col-lg-6 col-form-label text-muted small">
            Opcional: solo referencia en la planilla; no se usa para buscar el art&iacute;culo
        </div>
    </div>

    <div class="form-group row mb-0">
        <label for="col_precio" class="col-lg-3 col-form-label requerido">Columna precio</label>
        <div class="col-lg-3">
            <input type="text" name="col_precio" id="col_precio" class="form-control"
                value="{{ old('col_precio', PrecioImportColumnasSupport::COL_PRECIO_DEFAULT) }}"
                placeholder="precio" />
        </div>
        <div class="col-lg-6 col-form-label text-muted small">
            Filas con precio 0 o vac&iacute;o se omiten
        </div>
    </div>
</div>

<div id="panel-import-listas" class="alert alert-light border small mb-3" style="{{ $formatoImport === PrecioImportColumnasSupport::FORMATO_SIMPLE ? 'display:none' : '' }}">
    <strong>Formato m&uacute;ltiples listas (legacy):</strong>
    <ul class="mb-0 mt-2">
        <li>Columna obligatoria <code>articulo</code> con el SKU.</li>
        <li>Una columna por lista: <code>L_&lt;c&oacute;digo&gt;</code> (ej. <code>L_01</code>, <code>L_02</code>).</li>
        <li>Solo art&iacute;culos facturables; precio 0 se ignora.</li>
    </ul>
</div>

<div class="form-group row">
    <label for="fila_encabezado" class="col-lg-3 col-form-label">Fila del encabezado</label>
    <div class="col-lg-2">
        <input type="number" name="fila_encabezado" id="fila_encabezado" class="form-control" min="1" max="50"
            value="{{ old('fila_encabezado', '') }}" placeholder="Auto" />
    </div>
    <div class="col-lg-7 col-form-label text-muted small">
        Vac&iacute;o = detectar autom&aacute;ticamente (t&iacute;tulo arriba, encabezado en fila 2, 3, etc.)
    </div>
</div>

<div class="form-group row">
    <label for="file" class="col-lg-3 col-form-label requerido">Archivo Excel</label>
    <div class="col-lg-6">
        <input type="file" name="file" id="file" class="form-control" accept=".xlsx,.xls,.csv" required />
    </div>
    <div class="col-lg-3">
        <button type="button" id="btn-preview-import-precio" class="btn btn-outline-primary btn-sm" disabled>
            <i class="fa fa-search"></i> Vista previa
        </button>
    </div>
</div>

<input type="hidden" name="hoja_indice" id="hoja_indice" value="{{ old('hoja_indice', 1) }}">

<div class="form-group row mb-2 d-none" id="panel-hoja-excel">
    <label for="hoja_indice_select" class="col-lg-3 col-form-label pt-1">Hoja a importar</label>
    <div class="col-lg-4 col-md-5">
        <select id="hoja_indice_select" class="form-control form-control-sm" aria-label="Elegir hoja del Excel"></select>
    </div>
    <div class="col-lg-5 col-form-label text-muted small pt-1" id="hoja_indice_ayuda">
        Elija la pesta&ntilde;a del Excel con los precios a cargar.
    </div>
</div>

<div id="panel-preview-import-precio" class="card border-primary mb-3" style="display:none;">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <strong><i class="fa fa-table"></i> Vista previa del archivo</strong>
        <span id="preview-import-precio-estado" class="badge badge-secondary">—</span>
    </div>
    <div class="card-body p-2" id="preview-import-precio-contenido">
        <p class="text-muted small mb-0">Seleccione un archivo para analizar columnas y filas.</p>
    </div>
</div>

<div class="alert alert-info small mb-0">
    <strong>Encabezados:</strong> el sistema busca la fila con t&iacute;tulos de columna (<code>sku</code>, <code>precio</code>, etc.)
    en las primeras 15 filas y <strong>no la importa</strong>. Pod&eacute;s dejar filas de t&iacute;tulo o vac&iacute;as arriba (ej. encabezado en fila 3).
    Si la detecci&oacute;n falla, indic&aacute; el n&uacute;mero de fila manualmente.
    El sistema reconoce variantes habituales (<code>sku</code>/<code>codigo</code>, <code>descripcion</code>/<code>detalle</code>, <code>precio</code>/<code>importe</code>, etc.)
    sin distinguir may&uacute;sculas ni tildes. Si dej&aacute;s los defaults del formulario, busca esos nombres y sus alias m&aacute;s comunes.
    <br><br>
    <strong>Ejemplo con t&iacute;tulo (encabezado fila 3):</strong>
    <table class="table table-sm table-bordered bg-white mt-2 mb-0" style="max-width: 28rem;">
        <tbody>
            <tr class="table-secondary"><td colspan="3"><em>Lista de precios — julio 2026</em> (fila 1, se ignora)</td></tr>
            <tr><td colspan="3">&nbsp;</td></tr>
            <tr class="thead-light font-weight-bold"><td>sku</td><td>descripcion</td><td>precio</td></tr>
            <tr><td>1001</td><td>Producto A</td><td>1500.00</td></tr>
            <tr><td>1002</td><td>Producto B</td><td>2300,50</td></tr>
        </tbody>
    </table>
</div>
