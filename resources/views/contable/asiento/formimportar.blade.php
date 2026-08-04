@php
    use App\Support\Contable\AsientoImportColumnasSupport;

    $fechaImport = old('fecha', date('Y-m-d'));
    try {
        $fechaImport = \Carbon\Carbon::parse($fechaImport)->format('Y-m-d');
    } catch (\Throwable $e) {
        $fechaImport = date('Y-m-d');
    }
    $tipoasientoIdImport = old('tipoasiento_id', session('tipoasiento_id', ''));
    $monedaIdImport = old('moneda_id', 1);
@endphp

@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => old('empresa_id', session('empresa_id')),
    'col_label' => 'col-lg-3 text-right pr-2',
    'col_input' => 'col-lg-5',
])

<div class="form-group row">
    <label for="tipoasiento_id" class="col-lg-3 control-label text-right pr-2 requerido">Tipo de asiento</label>
    <div class="col-lg-5">
        <select name="tipoasiento_id" id="tipoasiento_id" class="form-control" required>
            <option value="">-- Elija tipo --</option>
            @foreach ($tipoasiento_query as $tipo)
                <option value="{{ $tipo->id }}"
                    @selected((string) $tipoasientoIdImport === (string) $tipo->id)>
                    {{ $tipo->abreviatura }} — {{ $tipo->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="fecha" class="col-lg-3 control-label text-right pr-2 requerido">Fecha</label>
    <div class="col-lg-3">
        <input type="date" name="fecha" id="fecha" class="form-control" value="{{ $fechaImport }}" required />
    </div>
</div>

<div class="form-group row">
    <label for="observacion" class="col-lg-3 control-label text-right pr-2">Observación cabecera</label>
    <div class="col-lg-6">
        <input type="text" name="observacion" id="observacion" class="form-control" maxlength="500"
            value="{{ old('observacion', '') }}" placeholder="Opcional" />
    </div>
</div>

<div class="form-group row">
    <label for="moneda_id" class="col-lg-3 control-label text-right pr-2 requerido">Moneda por defecto</label>
    <div class="col-lg-3">
        <select name="moneda_id" id="moneda_id" class="form-control" required>
            <option value="">-- Elija moneda --</option>
            @foreach ($moneda_query as $moneda)
                <option value="{{ $moneda->id }}"
                    @selected((int) $monedaIdImport === (int) $moneda->id)>
                    {{ $moneda->abreviatura }} — {{ $moneda->nombre }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-lg-6 col-form-label text-muted small">
        Se usa cuando la fila no trae columna de moneda (o la columna no se detecta).
    </div>
</div>

<div class="border rounded p-3 mb-3 bg-light">
    <h6 class="mb-3">Configuración de columnas (valores por defecto)</h6>

    <div class="form-group row">
        <label for="col_cuenta" class="col-lg-3 control-label text-right pr-2 requerido">Columna cuenta</label>
        <div class="col-lg-3">
            <input type="text" name="col_cuenta" id="col_cuenta" class="form-control"
                value="{{ old('col_cuenta', AsientoImportColumnasSupport::COL_CUENTA_DEFAULT) }}"
                placeholder="cuenta" />
        </div>
        <div class="col-lg-6 col-form-label text-muted small">
            Código de cuenta contable de la empresa. Alias: <code>cuenta</code>, <code>codigo_cuenta</code>, <code>cta</code>…
        </div>
    </div>

    <div class="form-group row">
        <label for="col_debe" class="col-lg-3 control-label text-right pr-2 requerido">Columna Debe</label>
        <div class="col-lg-3">
            <input type="text" name="col_debe" id="col_debe" class="form-control"
                value="{{ old('col_debe', AsientoImportColumnasSupport::COL_DEBE_DEFAULT) }}"
                placeholder="debe" />
        </div>
        <div class="col-lg-6 col-form-label text-muted small">
            Importe al Debe. Filas con Debe y Haber juntos se omiten.
        </div>
    </div>

    <div class="form-group row">
        <label for="col_haber" class="col-lg-3 control-label text-right pr-2 requerido">Columna Haber</label>
        <div class="col-lg-3">
            <input type="text" name="col_haber" id="col_haber" class="form-control"
                value="{{ old('col_haber', AsientoImportColumnasSupport::COL_HABER_DEFAULT) }}"
                placeholder="haber" />
        </div>
    </div>

    <div class="form-group row">
        <label for="col_centrocosto" class="col-lg-3 control-label text-right pr-2">Columna centro de costo</label>
        <div class="col-lg-3">
            <input type="text" name="col_centrocosto" id="col_centrocosto" class="form-control"
                value="{{ old('col_centrocosto', AsientoImportColumnasSupport::COL_CENTROCOSTO_DEFAULT) }}"
                placeholder="centrocosto" />
        </div>
        <div class="col-lg-6 col-form-label text-muted small">Opcional. Código de CC.</div>
    </div>

    <div class="form-group row">
        <label for="col_moneda" class="col-lg-3 control-label text-right pr-2">Columna moneda</label>
        <div class="col-lg-3">
            <input type="text" name="col_moneda" id="col_moneda" class="form-control"
                value="{{ old('col_moneda', AsientoImportColumnasSupport::COL_MONEDA_DEFAULT) }}"
                placeholder="moneda" />
        </div>
        <div class="col-lg-6 col-form-label text-muted small">Opcional. Código / abreviatura (ej. ARS, USD).</div>
    </div>

    <div class="form-group row">
        <label for="col_cotizacion" class="col-lg-3 control-label text-right pr-2">Columna cotización</label>
        <div class="col-lg-3">
            <input type="text" name="col_cotizacion" id="col_cotizacion" class="form-control"
                value="{{ old('col_cotizacion', AsientoImportColumnasSupport::COL_COTIZACION_DEFAULT) }}"
                placeholder="cotizacion" />
        </div>
        <div class="col-lg-6 col-form-label text-muted small">Opcional.</div>
    </div>

    <div class="form-group row mb-0">
        <label for="col_detalle" class="col-lg-3 control-label text-right pr-2">Columna detalle</label>
        <div class="col-lg-3">
            <input type="text" name="col_detalle" id="col_detalle" class="form-control"
                value="{{ old('col_detalle', AsientoImportColumnasSupport::COL_DETALLE_DEFAULT) }}"
                placeholder="detalle" />
        </div>
        <div class="col-lg-6 col-form-label text-muted small">
            Opcional. Concepto / observación de la línea.
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="fila_encabezado" class="col-lg-3 control-label text-right pr-2">Fila del encabezado</label>
    <div class="col-lg-2">
        <input type="number" name="fila_encabezado" id="fila_encabezado" class="form-control" min="1" max="50"
            value="{{ old('fila_encabezado', '') }}" placeholder="Auto" />
    </div>
    <div class="col-lg-7 col-form-label text-muted small">
        Vacío = detectar automáticamente (título arriba, encabezado en fila 2, 3, etc.)
    </div>
</div>

<div class="form-group row">
    <label for="file" class="col-lg-3 control-label text-right pr-2 requerido">Archivo Excel</label>
    <div class="col-lg-6">
        <input type="file" name="file" id="file" class="form-control" accept=".xlsx,.xls,.csv" required />
    </div>
    <div class="col-lg-3">
        <button type="button" id="btn-preview-import-asiento" class="btn btn-outline-primary btn-sm" disabled>
            <i class="fa fa-search"></i> Vista previa
        </button>
    </div>
</div>

<input type="hidden" name="hoja_indice" id="hoja_indice" value="{{ old('hoja_indice', 1) }}">

<div class="form-group row mb-2 d-none" id="panel-hoja-excel">
    <label for="hoja_indice_select" class="col-lg-3 control-label text-right pr-2 pt-1">Hoja a importar</label>
    <div class="col-lg-4 col-md-5">
        <select id="hoja_indice_select" class="form-control form-control-sm" aria-label="Elegir hoja del Excel"></select>
    </div>
    <div class="col-lg-5 col-form-label text-muted small pt-1" id="hoja_indice_ayuda">
        Elija la pestaña del Excel con los movimientos.
    </div>
</div>

<div class="form-group row d-none" id="panel-confirm-aprobacion">
    <div class="col-lg-3"></div>
    <div class="col-lg-8">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="confirmar_pendiente_aprobacion"
                name="confirmar_pendiente_aprobacion" value="1"
                @checked(old('confirmar_pendiente_aprobacion'))>
            <label class="custom-control-label" for="confirmar_pendiente_aprobacion">
                Confirmo dejar el asiento pendiente de aprobación (cuentas fuera de mi lista autorizada)
            </label>
        </div>
    </div>
</div>

<div id="panel-preview-import-asiento" class="card border-primary mb-3" style="display:none;">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <strong><i class="fa fa-table"></i> Vista previa del archivo</strong>
        <span id="preview-import-asiento-estado" class="badge badge-secondary">—</span>
    </div>
    <div class="card-body p-2" id="preview-import-asiento-contenido">
        <p class="text-muted small mb-0">Seleccione un archivo para analizar columnas y filas.</p>
    </div>
</div>

<div class="alert alert-info small mb-0">
    <strong>Encabezados:</strong> el sistema busca la fila con títulos de columna
    (<code>cuenta</code>, <code>debe</code>, <code>haber</code>, etc.) en las primeras 15 filas y
    <strong>no la importa</strong>. Reconoce variantes habituales sin distinguir mayúsculas ni tildes.
    El Excel arma <strong>un solo asiento</strong>: Debe debe igualar Haber (tolerancia 0,01).
    <br><br>
    <strong>Ejemplo:</strong>
    <table class="table table-sm table-bordered bg-white mt-2 mb-0" style="max-width: 36rem;">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th>cuenta</th>
                <th>debe</th>
                <th>haber</th>
                <th>detalle</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>111010001</td><td>1500,00</td><td></td><td>Caja</td></tr>
            <tr><td>411010001</td><td></td><td>1500,00</td><td>Ingreso</td></tr>
        </tbody>
    </table>
</div>
