@php
    use App\Support\Contable\AsientoImportColumnasSupport;
    use App\Support\Contable\AsientoImportPreferenciasUsuario;

    $prefs = AsientoImportPreferenciasUsuario::leer();
    $pref = static function (string $campo, mixed $default = null) use ($prefs) {
        if (session()->hasOldInput($campo)) {
            return old($campo);
        }
        if (array_key_exists($campo, $prefs)) {
            return $prefs[$campo];
        }

        return $default;
    };

    $fechaImport = old('fecha', date('Y-m-d'));
    try {
        $fechaImport = \Carbon\Carbon::parse($fechaImport)->format('Y-m-d');
    } catch (\Throwable $e) {
        $fechaImport = date('Y-m-d');
    }
    $tipoasientoIdImport = $pref('tipoasiento_id', session('tipoasiento_id', ''));
    $monedaIdImport = $pref('moneda_id', 1);
    $modoColumnas = AsientoImportColumnasSupport::normalizarModoColumnas($pref('modo_columnas'));
    $modoImporte = AsientoImportColumnasSupport::normalizarModoImporte($pref('modo_importe'));
    $csvSeparador = AsientoImportColumnasSupport::valorFormularioSeparadorCsv(
        AsientoImportColumnasSupport::normalizarSeparadorCsv($pref('csv_separador'))
    );
    $porNumero = $modoColumnas === AsientoImportColumnasSupport::MODO_COLUMNAS_NUMERO;
    $montoDh = $modoImporte === AsientoImportColumnasSupport::MODO_IMPORTE_MONTO_DH;
    $filaEncabezadoPref = $pref('fila_encabezado', '');
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
    <h6 class="mb-3">Configuración del archivo</h6>

    <div class="form-group row">
        <label class="col-lg-3 control-label text-right pr-2 requerido">Columnas</label>
        <div class="col-lg-9">
            <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="modo_columnas_titulo" name="modo_columnas" value="titulo"
                    class="custom-control-input js-modo-columnas"
                    @checked($modoColumnas === AsientoImportColumnasSupport::MODO_COLUMNAS_TITULO)>
                <label class="custom-control-label" for="modo_columnas_titulo">Por título de encabezado</label>
            </div>
            <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="modo_columnas_numero" name="modo_columnas" value="numero"
                    class="custom-control-input js-modo-columnas"
                    @checked($modoColumnas === AsientoImportColumnasSupport::MODO_COLUMNAS_NUMERO)>
                <label class="custom-control-label" for="modo_columnas_numero">Por número de columna (sin título)</label>
            </div>
            <div class="text-muted small mt-1">
                Sin título: indique el número (1 = primera) o letra Excel (A, B…).
            </div>
        </div>
    </div>

    <div class="form-group row">
        <label class="col-lg-3 control-label text-right pr-2 requerido">Importes</label>
        <div class="col-lg-9">
            <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="modo_importe_debe_haber" name="modo_importe" value="debe_haber"
                    class="custom-control-input js-modo-importe"
                    @checked($modoImporte === AsientoImportColumnasSupport::MODO_IMPORTE_DEBE_HABER)>
                <label class="custom-control-label" for="modo_importe_debe_haber">Columnas Debe y Haber</label>
            </div>
            <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="modo_importe_monto_dh" name="modo_importe" value="monto_dh"
                    class="custom-control-input js-modo-importe"
                    @checked($modoImporte === AsientoImportColumnasSupport::MODO_IMPORTE_MONTO_DH)>
                <label class="custom-control-label" for="modo_importe_monto_dh">Una columna de monto + D/H</label>
            </div>
        </div>
    </div>

    <div class="form-group row">
        <label for="col_cuenta" class="col-lg-3 control-label text-right pr-2 requerido">
            <span class="js-label-col-cuenta">Columna cuenta</span>
        </label>
        <div class="col-lg-3">
            <input type="text" name="col_cuenta" id="col_cuenta" class="form-control js-col-config"
                value="{{ $pref('col_cuenta', $porNumero ? AsientoImportColumnasSupport::COL_CUENTA_NUMERO_DEFAULT : AsientoImportColumnasSupport::COL_CUENTA_DEFAULT) }}"
                data-default-titulo="{{ AsientoImportColumnasSupport::COL_CUENTA_DEFAULT }}"
                data-default-numero="{{ AsientoImportColumnasSupport::COL_CUENTA_NUMERO_DEFAULT }}"
                placeholder="cuenta" />
        </div>
        <div class="col-lg-6 col-form-label text-muted small js-ayuda-col-cuenta">
            Código de cuenta contable. Alias: <code>cuenta</code>, <code>codigo_cuenta</code>, <code>cta</code>…
        </div>
    </div>

    <div class="js-panel-debe-haber" @if ($montoDh) style="display:none;" @endif>
        <div class="form-group row">
            <label for="col_debe" class="col-lg-3 control-label text-right pr-2 requerido">Columna Debe</label>
            <div class="col-lg-3">
                <input type="text" name="col_debe" id="col_debe" class="form-control js-col-config"
                    value="{{ $pref('col_debe', $porNumero ? AsientoImportColumnasSupport::COL_DEBE_NUMERO_DEFAULT : AsientoImportColumnasSupport::COL_DEBE_DEFAULT) }}"
                    data-default-titulo="{{ AsientoImportColumnasSupport::COL_DEBE_DEFAULT }}"
                    data-default-numero="{{ AsientoImportColumnasSupport::COL_DEBE_NUMERO_DEFAULT }}"
                    placeholder="debe" />
            </div>
            <div class="col-lg-6 col-form-label text-muted small">
                Importe al Debe. Filas con Debe y Haber juntos se omiten.
            </div>
        </div>

        <div class="form-group row">
            <label for="col_haber" class="col-lg-3 control-label text-right pr-2 requerido">Columna Haber</label>
            <div class="col-lg-3">
                <input type="text" name="col_haber" id="col_haber" class="form-control js-col-config"
                    value="{{ $pref('col_haber', $porNumero ? AsientoImportColumnasSupport::COL_HABER_NUMERO_DEFAULT : AsientoImportColumnasSupport::COL_HABER_DEFAULT) }}"
                    data-default-titulo="{{ AsientoImportColumnasSupport::COL_HABER_DEFAULT }}"
                    data-default-numero="{{ AsientoImportColumnasSupport::COL_HABER_NUMERO_DEFAULT }}"
                    placeholder="haber" />
            </div>
        </div>
    </div>

    <div class="js-panel-monto-dh" @if (! $montoDh) style="display:none;" @endif>
        <div class="form-group row">
            <label for="col_monto" class="col-lg-3 control-label text-right pr-2 requerido">Columna monto</label>
            <div class="col-lg-3">
                <input type="text" name="col_monto" id="col_monto" class="form-control js-col-config"
                    value="{{ $pref('col_monto', $porNumero ? AsientoImportColumnasSupport::COL_MONTO_NUMERO_DEFAULT : AsientoImportColumnasSupport::COL_MONTO_DEFAULT) }}"
                    data-default-titulo="{{ AsientoImportColumnasSupport::COL_MONTO_DEFAULT }}"
                    data-default-numero="{{ AsientoImportColumnasSupport::COL_MONTO_NUMERO_DEFAULT }}"
                    placeholder="monto" />
            </div>
            <div class="col-lg-6 col-form-label text-muted small">
                Una sola columna de importe. Alias: <code>monto</code>, <code>importe</code>, <code>valor</code>…
            </div>
        </div>

        <div class="form-group row">
            <label for="col_dh" class="col-lg-3 control-label text-right pr-2 requerido">Columna D/H</label>
            <div class="col-lg-3">
                <input type="text" name="col_dh" id="col_dh" class="form-control js-col-config"
                    value="{{ $pref('col_dh', $porNumero ? AsientoImportColumnasSupport::COL_DH_NUMERO_DEFAULT : AsientoImportColumnasSupport::COL_DH_DEFAULT) }}"
                    data-default-titulo="{{ AsientoImportColumnasSupport::COL_DH_DEFAULT }}"
                    data-default-numero="{{ AsientoImportColumnasSupport::COL_DH_NUMERO_DEFAULT }}"
                    placeholder="dh" />
            </div>
            <div class="col-lg-6 col-form-label text-muted small">
                Valores admitidos: <code>D</code> / <code>Debe</code> o <code>H</code> / <code>Haber</code>.
            </div>
        </div>
    </div>

    <div class="form-group row">
        <label for="col_centrocosto" class="col-lg-3 control-label text-right pr-2">Columna centro de costo</label>
        <div class="col-lg-3">
            <input type="text" name="col_centrocosto" id="col_centrocosto" class="form-control js-col-config"
                value="{{ $pref('col_centrocosto', $porNumero ? '' : AsientoImportColumnasSupport::COL_CENTROCOSTO_DEFAULT) }}"
                data-default-titulo="{{ AsientoImportColumnasSupport::COL_CENTROCOSTO_DEFAULT }}"
                data-default-numero=""
                placeholder="centrocosto" />
        </div>
        <div class="col-lg-6 col-form-label text-muted small">Opcional. Vacío = no se usa. En modo número: indique 4, 5…</div>
    </div>

    <div class="form-group row">
        <label for="col_moneda" class="col-lg-3 control-label text-right pr-2">Columna moneda</label>
        <div class="col-lg-3">
            <input type="text" name="col_moneda" id="col_moneda" class="form-control js-col-config"
                value="{{ $pref('col_moneda', $porNumero ? '' : AsientoImportColumnasSupport::COL_MONEDA_DEFAULT) }}"
                data-default-titulo="{{ AsientoImportColumnasSupport::COL_MONEDA_DEFAULT }}"
                data-default-numero=""
                placeholder="moneda" />
        </div>
        <div class="col-lg-6 col-form-label text-muted small">Opcional. Vacío = no se usa.</div>
    </div>

    <div class="form-group row">
        <label for="col_cotizacion" class="col-lg-3 control-label text-right pr-2">Columna cotización</label>
        <div class="col-lg-3">
            <input type="text" name="col_cotizacion" id="col_cotizacion" class="form-control js-col-config"
                value="{{ $pref('col_cotizacion', $porNumero ? '' : AsientoImportColumnasSupport::COL_COTIZACION_DEFAULT) }}"
                data-default-titulo="{{ AsientoImportColumnasSupport::COL_COTIZACION_DEFAULT }}"
                data-default-numero=""
                placeholder="cotizacion" />
        </div>
        <div class="col-lg-6 col-form-label text-muted small">Opcional. Vacío = no se usa.</div>
    </div>

    <div class="form-group row mb-0">
        <label for="col_detalle" class="col-lg-3 control-label text-right pr-2">Columna detalle</label>
        <div class="col-lg-3">
            <input type="text" name="col_detalle" id="col_detalle" class="form-control js-col-config"
                value="{{ $pref('col_detalle', $porNumero ? '' : AsientoImportColumnasSupport::COL_DETALLE_DEFAULT) }}"
                data-default-titulo="{{ AsientoImportColumnasSupport::COL_DETALLE_DEFAULT }}"
                data-default-numero=""
                placeholder="detalle" />
        </div>
        <div class="col-lg-6 col-form-label text-muted small">
            Opcional. Vacío = no se usa.
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="fila_encabezado" class="col-lg-3 control-label text-right pr-2">
        <span id="label-fila-encabezado">Fila del encabezado</span>
    </label>
    <div class="col-lg-2">
        <input type="number" name="fila_encabezado" id="fila_encabezado" class="form-control" min="1" max="50"
            value="{{ $filaEncabezadoPref }}" placeholder="Auto" />
    </div>
    <div class="col-lg-7 col-form-label text-muted small" id="ayuda-fila-encabezado">
        Vacío = detectar automáticamente (título arriba, encabezado en fila 2, 3, etc.)
    </div>
</div>

<div class="form-group row">
    <label for="file" class="col-lg-3 control-label text-right pr-2 requerido">Archivo Excel / CSV</label>
    <div class="col-lg-6">
        <input type="file" name="file" id="file" class="form-control" accept=".xlsx,.xls,.csv,text/csv" required />
        <small class="form-text text-muted">CSV (UTF-8 o Latin-1) y Excel (.xlsx / .xls). Use siempre la vista previa antes de importar.</small>
    </div>
    <div class="col-lg-3">
        <button type="button" id="btn-preview-import-asiento" class="btn btn-outline-primary btn-sm" disabled>
            <i class="fa fa-search"></i> Vista previa
        </button>
    </div>
</div>

<div class="form-group row" id="panel-csv-separador">
    <label for="csv_separador" class="col-lg-3 control-label text-right pr-2">Separador CSV</label>
    <div class="col-lg-3">
        <select name="csv_separador" id="csv_separador" class="form-control">
            <option value="auto" @selected($csvSeparador === 'auto' || $csvSeparador === '')>Automático (detectar)</option>
            <option value="," @selected($csvSeparador === ',')>Coma (,)</option>
            <option value=";" @selected($csvSeparador === ';')>Punto y coma (;)</option>
            <option value="tab" @selected($csvSeparador === 'tab')>Tabulación</option>
            <option value="|" @selected($csvSeparador === '|')>Pipe (|)</option>
        </select>
    </div>
    <div class="col-lg-6 col-form-label text-muted small">
        Para CSV/TXT. Con «Automático» se detecta por el contenido. En Excel se ignora.
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
    <strong>Encabezados:</strong> con títulos, el sistema busca la fila
    (<code>cuenta</code>, <code>debe</code>/<code>haber</code> o <code>monto</code>+<code>dh</code>) en las primeras 15 filas y
    <strong>no la importa</strong>. Sin títulos, configure por número de columna.
    El archivo arma <strong>un solo asiento</strong>. Las filas sin importe se cargan igual y la pantalla pasa a la edición del asiento para completarlas. Si no balancea, no se envía a Anita hasta que cierre.
    Formatos: <strong>CSV</strong> y <strong>Excel</strong>. Siempre revise la <strong>vista previa</strong>.
    <br><br>
    <strong>Ejemplo con Debe/Haber:</strong>
    <table class="table table-sm table-bordered bg-white mt-2 mb-2" style="max-width: 36rem;">
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
    <strong>Ejemplo con monto + D/H:</strong>
    <table class="table table-sm table-bordered bg-white mt-2 mb-0" style="max-width: 36rem;">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th>cuenta</th>
                <th>monto</th>
                <th>dh</th>
                <th>detalle</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>111010001</td><td>1500,00</td><td>D</td><td>Caja</td></tr>
            <tr><td>411010001</td><td>1500,00</td><td>H</td><td>Ingreso</td></tr>
        </tbody>
    </table>
</div>
