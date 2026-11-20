@php
    $colLabel = 'col-lg-4 control-label text-right pr-2';
    $colInput = 'col-lg-6';
    $data = $data ?? null;
    $proveedor = $data->proveedor ?? null;
    $sector = $data->sectorSolicitudpago ?? null;
    $concepto = $data->conceptoSolicitudpago ?? null;
    $forma = $data->formapagosol ?? null;
    $moneda = $data->moneda ?? null;
    $cc = $data->centrocosto ?? null;
    $ctaDebe = $data->cuentacontableDebe ?? null;
    $ctaHaber = $data->cuentacontableHaber ?? null;
@endphp

@include('includes.form-error')

@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => old('empresa_id', $data->empresa_id ?? null),
    'solo_lectura' => isset($data),
    'col_label' => 'col-lg-4 text-right pr-2',
    'col_input' => $colInput,
    'required' => true,
])

<div class="form-group row">
    <label for="municipio" class="{{ $colLabel }} requerido">Municipio</label>
    <div class="{{ $colInput }}">
        <input type="text" name="municipio" id="municipio" class="form-control"
               value="{{ old('municipio', $data->municipio ?? '') }}" required maxlength="120">
    </div>
</div>

<div class="form-group row">
    <label for="legajo" class="{{ $colLabel }} requerido">Legajo municipal</label>
    <div class="{{ $colInput }}">
        <input type="text" name="legajo" id="legajo" class="form-control"
               value="{{ old('legajo', $data->legajo ?? '') }}" required maxlength="40">
    </div>
</div>

<div class="form-group row">
    <label for="periodicidad" class="{{ $colLabel }} requerido">Periodicidad</label>
    <div class="{{ $colInput }}">
        <select name="periodicidad" id="periodicidad" class="form-control" required>
            @foreach ($periodicidad_enum as $val => $label)
                <option value="{{ $val }}" @selected(old('periodicidad', $data->periodicidad ?? 'semanal') === $val)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="plantilla" class="{{ $colLabel }} requerido">Plantilla de nota</label>
    <div class="{{ $colInput }}">
        <select name="plantilla" id="plantilla" class="form-control" required>
            @foreach ($plantilla_enum as $val => $label)
                <option value="{{ $val }}" @selected(old('plantilla', $data->plantilla ?? 'biyemas') === $val)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="alicuota" class="{{ $colLabel }} requerido">Al&iacute;cuota</label>
    <div class="{{ $colInput }}">
        <input type="number" step="0.0001" min="0.0001" max="1" name="alicuota" id="alicuota" class="form-control"
               value="{{ old('alicuota', $data->alicuota ?? 0.04) }}" required>
        <small class="form-text text-muted">Ej. 0,04 = 4%</small>
    </div>
</div>

<div class="form-group row">
    <label for="firmante_nombre" class="{{ $colLabel }} requerido">Firmante</label>
    <div class="{{ $colInput }}">
        <input type="text" name="firmante_nombre" id="firmante_nombre" class="form-control"
               value="{{ old('firmante_nombre', $data->firmante_nombre ?? 'Marisol Gonzalez') }}" required maxlength="120">
    </div>
</div>

<div class="form-group row">
    <label for="firmante_cargo" class="{{ $colLabel }} requerido">Cargo</label>
    <div class="{{ $colInput }}">
        <input type="text" name="firmante_cargo" id="firmante_cargo" class="form-control"
               value="{{ old('firmante_cargo', $data->firmante_cargo ?? 'Impuestos') }}" required maxlength="80">
    </div>
</div>

<div class="form-group row">
    <label for="pie_razon_social" class="{{ $colLabel }}">Raz&oacute;n social al pie</label>
    <div class="{{ $colInput }}">
        <input type="text" name="pie_razon_social" id="pie_razon_social" class="form-control"
               value="{{ old('pie_razon_social', $data->pie_razon_social ?? '') }}" maxlength="120">
    </div>
</div>

<div class="form-group row">
    <label for="direccion_extra" class="{{ $colLabel }}">Direcci&oacute;n extra (membrete)</label>
    <div class="{{ $colInput }}">
        <input type="text" name="direccion_extra" id="direccion_extra" class="form-control"
               value="{{ old('direccion_extra', $data->direccion_extra ?? '') }}" maxlength="255"
               placeholder="Ej. B1875ABF, Wilde, Buenos Aires">
    </div>
</div>

<div class="form-group row">
    <label for="telefono" class="{{ $colLabel }}">Tel&eacute;fono</label>
    <div class="{{ $colInput }}">
        <input type="text" name="telefono" id="telefono" class="form-control"
               value="{{ old('telefono', $data->telefono ?? '') }}" maxlength="80">
    </div>
</div>

<hr class="my-3">
<h5 class="mb-3">Solicitud de pago (por empresa)</h5>
<p class="text-muted small mb-3">
    Datos para el bot&oacute;n &laquo;Generar solicitud de pago&raquo; en Canon municipal.
    Placeholders del detalle: <code>{ORDINAL}</code> (PRIMERA, SEGUNDA&hellip;) y <code>{MES}</code> (NOVIEMBRE).
</p>

@include('compras.partials.campo_consulta_proveedor', [
    'prefix' => 'canon_cfg',
    'proveedorId' => old('proveedor_id', $data->proveedor_id ?? ''),
    'codigo' => old('proveedor_codigo', $proveedor->codigo ?? ''),
    'nombre' => old('proveedor_nombre', $proveedor->nombre ?? ''),
    'inputName' => 'proveedor_id',
    'inputId' => 'proveedor_id',
    'required' => false,
    'col_label' => $colLabel,
    'col_input' => $colInput,
])

@include('solicitudpago.partials.campo_consulta_sector_solicitudpago', [
    'sectorId' => old('sector_solicitudpago_id', $data->sector_solicitudpago_id ?? ''),
    'codigo' => old('sector_codigo', $sector->codigo ?? ''),
    'nombre' => old('sector_nombre', $sector->nombre ?? ''),
    'required' => false,
    'col_label' => $colLabel,
    'col_input' => $colInput,
])

@include('solicitudpago.partials.campo_consulta_concepto_solicitudpago', [
    'conceptoId' => old('concepto_solicitudpago_id', $data->concepto_solicitudpago_id ?? ''),
    'codigo' => old('concepto_codigo', $concepto->codigo ?? ''),
    'nombre' => old('concepto_nombre', $concepto->nombre ?? ''),
    'col_label' => $colLabel.' col-form-label',
    'col_input' => $colInput,
])

@include('solicitudpago.partials.campo_consulta_formapagosol', [
    'formapagosolId' => old('formapagosol_id', $data->formapagosol_id ?? ''),
    'codigo' => old('formapagosol_codigo', $forma->codigo ?? ''),
    'nombre' => old('formapagosol_nombre', $forma->nombre ?? ''),
    'required' => false,
    'col_label' => $colLabel,
    'col_input' => $colInput,
])

@include('configuracion.partials.campo_consulta_moneda', [
    'prefix' => 'canon_cfg',
    'monedaId' => old('moneda_id', $data->moneda_id ?? ''),
    'codigo' => old('moneda_codigo', $moneda->codigo ?? ''),
    'nombre' => old('moneda_nombre', $moneda->nombre ?? ''),
    'required' => false,
    'col_label' => $colLabel,
    'col_input' => $colInput,
])

@include('contable.partials.campo_consulta_centrocosto', [
    'prefix' => 'canon_cfg',
    'layout' => 'form_row',
    'centrocostoId' => old('centrocosto_id', $data->centrocosto_id ?? ''),
    'codigo' => old('centrocosto_codigo', $cc->codigo ?? ''),
    'descripcion' => old('centrocosto_nombre', $cc->nombre ?? ''),
    'required' => false,
    'col_label' => $colLabel,
    'col_input' => $colInput,
])

@include('sueldos.partials.campo_consulta_cuentacontable', [
    'cuentaId' => old('cuentacontable_debe_id', $data->cuentacontable_debe_id ?? ''),
    'codigo' => old('cuenta_debe_codigo', $ctaDebe->codigo ?? ''),
    'descripcion' => old('cuenta_debe_nombre', $ctaDebe->nombre ?? ''),
    'label' => 'Cuenta Debe (pasivo)',
    'inputName' => 'cuentacontable_debe_id',
    'inputId' => 'cuentacontable_debe_id',
    'required' => false,
    'col_label' => $colLabel,
    'col_input' => $colInput,
])

@include('sueldos.partials.campo_consulta_cuentacontable', [
    'cuentaId' => old('cuentacontable_haber_id', $data->cuentacontable_haber_id ?? ''),
    'codigo' => old('cuenta_haber_codigo', $ctaHaber->codigo ?? ''),
    'descripcion' => old('cuenta_haber_nombre', $ctaHaber->nombre ?? ''),
    'label' => 'Cuenta Haber (banco)',
    'inputName' => 'cuentacontable_haber_id',
    'inputId' => 'cuentacontable_haber_id',
    'required' => false,
    'col_label' => $colLabel,
    'col_input' => $colInput,
])

<div class="form-group row">
    <label for="plantilla_detalle" class="{{ $colLabel }}">Plantilla detalle</label>
    <div class="{{ $colInput }}">
        <input type="text" name="plantilla_detalle" id="plantilla_detalle" class="form-control"
               value="{{ old('plantilla_detalle', $data->plantilla_detalle ?? \App\Models\Contable\Canon_Municipal_Config::PLANTILLA_DETALLE_DEFAULT) }}"
               maxlength="180"
               placeholder="{ORDINAL} SEMANA {MES} CANON BINGO">
    </div>
</div>

<div class="form-group row">
    <div class="{{ $colLabel }}"></div>
    <div class="{{ $colInput }}">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1"
                @checked(old('activo', $data->activo ?? true))>
            <label class="custom-control-label" for="activo">Activo</label>
        </div>
    </div>
</div>

@include('includes.compras.modalconsultaproveedor')
@include('includes.solicitudpago.modalconsultaconcepto_solicitudpago')
@include('includes.configuracion.modalconsultamoneda')
@include('includes.contable.modalconsultacentrocosto')
@include('includes.contable.modalconsultacuentacontable')

<div class="modal fade" id="modal-canon-config-lista" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Consulta</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>C&oacute;digo</th>
                            <th>Nombre</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
