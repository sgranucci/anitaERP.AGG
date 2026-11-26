@php
    $colLabel = 'col-lg-4 control-label text-right pr-2';
    $colInput = 'col-lg-6';
    $data = $data ?? null;
    $proveedor = $data->proveedor ?? null;
    $sector = $data->sectorSolicitudpago ?? null;
    $conceptoMaq = $data->conceptoMaquinas ?? null;
    $conceptoBin = $data->conceptoBingo ?? null;
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

<hr class="my-3">
<h5 class="mb-3">Solicitudes de pago (por empresa)</h5>
<p class="text-muted small mb-3">
    Datos para el bot&oacute;n &laquo;Generar solicitudes de pago&raquo; en F2015 Canon entidades.
    Placeholders del detalle: <code>{EMP}</code> (BSA, KSA, RSA) y <code>{MM.AA}</code> (10.26).
</p>

@include('compras.partials.campo_consulta_proveedor', [
    'prefix' => 'canon_ent_cfg',
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

{{-- Dos conceptos en la misma pantalla: el picker compartido de solicitud de pago está atado a un solo campo. --}}
@foreach ([
    ['campo' => 'concepto_maquinas_solicitudpago_id', 'label' => 'Concepto máquinas', 'concepto' => $conceptoMaq, 'key' => 'maq'],
    ['campo' => 'concepto_bingo_solicitudpago_id', 'label' => 'Concepto bingo', 'concepto' => $conceptoBin, 'key' => 'bin'],
] as $fCon)
    <div class="form-group row">
        <label for="{{ $fCon['campo'] }}_codigo" class="{{ $colLabel }} requerido">{{ $fCon['label'] }}</label>
        <div class="{{ $colInput }}">
            <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
                <input type="text" id="{{ $fCon['campo'] }}_codigo" class="form-control codigo-concepto-canon-ent"
                       data-target="{{ $fCon['campo'] }}" style="max-width:110px;"
                       value="{{ old($fCon['campo'].'_codigo', $fCon['concepto']->codigo ?? '') }}"
                       autocomplete="off" placeholder="Código">
                <button type="button" class="btn btn-outline-secondary btn-sm consulta-concepto-canon-ent"
                        data-target="{{ $fCon['campo'] }}" title="Consulta conceptos (F1)">
                    <i class="fa fa-search"></i>
                </button>
                <input type="text" id="{{ $fCon['campo'] }}_nombre" class="form-control" readonly
                       value="{{ old($fCon['campo'].'_nombre', $fCon['concepto']->nombre ?? '') }}">
            </div>
            <input type="hidden" name="{{ $fCon['campo'] }}" id="{{ $fCon['campo'] }}"
                   value="{{ old($fCon['campo'], $data->{$fCon['campo']} ?? '') }}">
        </div>
    </div>
@endforeach

@include('solicitudpago.partials.campo_consulta_formapagosol', [
    'formapagosolId' => old('formapagosol_id', $data->formapagosol_id ?? ''),
    'codigo' => old('formapagosol_codigo', $forma->codigo ?? ''),
    'nombre' => old('formapagosol_nombre', $forma->nombre ?? ''),
    'required' => false,
    'col_label' => $colLabel,
    'col_input' => $colInput,
])

@include('configuracion.partials.campo_consulta_moneda', [
    'prefix' => 'canon_ent_cfg',
    'monedaId' => old('moneda_id', $data->moneda_id ?? ''),
    'codigo' => old('moneda_codigo', $moneda->codigo ?? ''),
    'nombre' => old('moneda_nombre', $moneda->nombre ?? ''),
    'required' => false,
    'col_label' => $colLabel,
    'col_input' => $colInput,
])

@include('contable.partials.campo_consulta_centrocosto', [
    'prefix' => 'canon_ent_cfg',
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
    <label for="plantilla_maquinas" class="{{ $colLabel }}">Plantilla detalle m&aacute;quinas</label>
    <div class="{{ $colInput }}">
        <input type="text" name="plantilla_maquinas" id="plantilla_maquinas" class="form-control"
               value="{{ old('plantilla_maquinas', $data->plantilla_maquinas ?? \App\Models\Contable\Canon_Entidades_Config::PLANTILLA_MAQUINAS_DEFAULT) }}"
               maxlength="180" placeholder="ENTIDADES {EMP} MAQ {MM.AA}">
    </div>
</div>

<div class="form-group row">
    <label for="plantilla_bingo" class="{{ $colLabel }}">Plantilla detalle bingo</label>
    <div class="{{ $colInput }}">
        <input type="text" name="plantilla_bingo" id="plantilla_bingo" class="form-control"
               value="{{ old('plantilla_bingo', $data->plantilla_bingo ?? \App\Models\Contable\Canon_Entidades_Config::PLANTILLA_BINGO_DEFAULT) }}"
               maxlength="180" placeholder="ENTIDADES {EMP} BINGO {MM.AA}">
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
@include('includes.configuracion.modalconsultamoneda')
@include('includes.contable.modalconsultacentrocosto')
@include('includes.contable.modalconsultacuentacontable')

<div class="modal fade" id="modal-canon-ent-concepto" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Consulta de conceptos</h5>
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
