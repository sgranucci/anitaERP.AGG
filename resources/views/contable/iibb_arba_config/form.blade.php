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
    $ctaBanco = $data->cuentacontableBanco ?? null;
    // Al volver con errores respeta lo tildado; si no, el valor guardado (o true al crear).
    $activoMarcado = session()->hasOldInput() ? (bool) old('activo') : ($data->activo ?? true);
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

<hr class="my-3">
<h5 class="mb-3">Liquidación ARBA</h5>
<p class="text-muted small mb-3">
    Parámetros del neteo, de los pendientes y del control contra el mayor de la liquidación quincenal.
</p>

<div class="form-group row">
    <label for="dias_aviso_pendiente" class="{{ $colLabel }} requerido">D&iacute;as de aviso de pendientes</label>
    <div class="{{ $colInput }}">
        <input type="number" step="1" min="0" max="365" name="dias_aviso_pendiente" id="dias_aviso_pendiente"
               inputmode="numeric" class="form-control" required
               value="{{ old('dias_aviso_pendiente', $data->dias_aviso_pendiente ?? \App\Models\Contable\IibbSolicitudpagoConfig::DIAS_AVISO_PENDIENTE_DEFAULT) }}">
        <small class="form-text text-muted">Un pendiente sin neteo con más días que este valor se muestra como aviso.</small>
    </div>
</div>


<p class="text-muted small mb-0">
    La tolerancia de conciliación contra el mayor se configura en <strong>Configuración IIBB</strong>
    (es la misma que usa la conciliación del proceso).
</p>

<hr class="my-3">
<h5 class="mb-3">Solicitud de pago del VEP</h5>
<p class="text-muted small mb-3">
    Datos para el bot&oacute;n &laquo;Generar solicitud de pago&raquo; de la liquidaci&oacute;n ARBA.
    Placeholders del detalle: <code>{1Q|2Q}</code>, <code>{MM.AAAA}</code> y <code>{EMP}</code>.
</p>

<div class="form-group row">
    <label for="dias_duplicado" class="{{ $colLabel }} requerido">D&iacute;as para detectar duplicados</label>
    <div class="{{ $colInput }}">
        <input type="number" step="1" min="0" max="365" name="dias_duplicado" id="dias_duplicado"
               inputmode="numeric" class="form-control" required
               value="{{ old('dias_duplicado', $data->dias_duplicado ?? \App\Models\Contable\IibbSolicitudpagoConfig::DIAS_DUPLICADO_DEFAULT) }}">
        <small class="form-text text-muted">Ventana hacia atrás en la que se busca una solicitud igual (mismo concepto y mismo monto o detalle).</small>
    </div>
</div>

@include('compras.partials.campo_consulta_proveedor', [
    'prefix' => 'iibb_arba',
    'proveedorId' => old('proveedor_id', $data->proveedor_id ?? ''),
    'codigo' => old('proveedor_codigo', $proveedor->codigo ?? ''),
    'nombre' => old('proveedor_nombre', $proveedor->nombre ?? ''),
    'inputName' => 'proveedor_id',
    'inputId' => 'proveedor_id',
    'required' => true,
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
    'col_label' => $colLabel,
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
    'prefix' => 'iibb_arba',
    'monedaId' => old('moneda_id', $data->moneda_id ?? ''),
    'codigo' => old('moneda_codigo', $moneda->codigo ?? ''),
    'nombre' => old('moneda_nombre', $moneda->nombre ?? ''),
    'required' => false,
    'col_label' => $colLabel,
    'col_input' => $colInput,
])

@include('contable.partials.campo_consulta_centrocosto', [
    'prefix' => 'iibb_arba',
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
    'label' => 'Cuenta Debe (214010014)',
    'inputName' => 'cuentacontable_debe_id',
    'inputId' => 'cuentacontable_debe_id',
    'required' => false,
    'col_label' => $colLabel,
    'col_input' => $colInput,
])

@include('sueldos.partials.campo_consulta_cuentacontable', [
    'cuentaId' => old('cuentacontable_banco_id', $data->cuentacontable_banco_id ?? ''),
    'codigo' => old('cuenta_banco_codigo', $ctaBanco->codigo ?? ''),
    'descripcion' => old('cuenta_banco_nombre', $ctaBanco->nombre ?? ''),
    'label' => 'Cuenta Banco (111050009)',
    'inputName' => 'cuentacontable_banco_id',
    'inputId' => 'cuentacontable_banco_id',
    'required' => false,
    'col_label' => $colLabel,
    'col_input' => $colInput,
])

<div class="form-group row">
    <label for="plantilla_detalle" class="{{ $colLabel }} requerido">Plantilla detalle</label>
    <div class="{{ $colInput }}">
        <input type="text" name="plantilla_detalle" id="plantilla_detalle" class="form-control" required
               value="{{ old('plantilla_detalle', $data->plantilla_detalle ?? \App\Models\Contable\IibbSolicitudpagoConfig::PLANTILLA_DETALLE_DEFAULT) }}"
               maxlength="160">
    </div>
</div>

<div class="form-group row">
    <div class="{{ $colLabel }}"></div>
    <div class="{{ $colInput }}">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1"
                @checked($activoMarcado)>
            <label class="custom-control-label" for="activo">Activo</label>
        </div>
    </div>
</div>

@include('includes.compras.modalconsultaproveedor')
@include('includes.solicitudpago.modalconsultaconcepto_solicitudpago')
@include('includes.configuracion.modalconsultamoneda')
@include('includes.contable.modalconsultacentrocosto')
@include('includes.contable.modalconsultacuentacontable')

<div class="modal fade" id="modal-iibb-arba-lista" tabindex="-1" role="dialog" aria-hidden="true">
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
