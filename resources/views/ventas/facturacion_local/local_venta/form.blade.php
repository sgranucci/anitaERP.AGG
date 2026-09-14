@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => old('empresa_id', $data->empresa_id ?? null),
    'solo_lectura' => false,
])

<div class="form-group row">
    <label for="codigo" class="col-lg-4 control-label text-right pr-2 requerido">Código</label>
    <div class="col-lg-4">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $data->codigo) }}" required maxlength="20" autofocus>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-4 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-6">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre) }}" required maxlength="120">
    </div>
</div>

<input type="hidden" name="puntoventa_id" id="puntoventa_id" value="{{ old('puntoventa_id', $data->puntoventa_id) }}">

<div class="form-group row">
    <label class="col-lg-4 control-label text-right pr-2 requerido">Puntos de venta</label>
    <div class="col-lg-8">
        <div class="card card-outline card-info mb-2">
            <div class="card-body p-2">
                <table class="table table-sm table-bordered mb-2" id="tabla-local-puntoventa">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th style="width:5rem;">Default</th>
                            <th>Punto de venta</th>
                            <th style="width:4rem;"></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-local-puntoventa">
                        @foreach ($puntoventasSeleccionados as $idx => $pvRow)
                        <tr class="local-pv-row">
                            <td class="text-center align-middle">
                                <input type="radio" name="puntoventa_default_radio" class="local-pv-default"
                                    value="1"
                                    @if (! empty($pvRow['es_default']))
                                        checked
                                    @endif
                                    title="PV default">
                            </td>
                            <td>
                                @include('ventas.partials.campo_consulta_puntoventa', [
                                    'prefix' => 'local_pv_'.$idx,
                                    'layout' => 'inline',
                                    'inputName' => 'puntoventa_ids[]',
                                    'inputId' => 'puntoventa_ids_'.$idx,
                                    'puntoventaId' => $pvRow['id'] ?? '',
                                    'codigo' => $pvRow['codigo'] ?? '',
                                    'nombre' => $pvRow['nombre'] ?? '',
                                    'required' => $idx === 0,
                                    'mostrar_editar' => true,
                                ])
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" class="btn-accion-tabla local-pv-quitar" title="Quitar">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-primary" id="local-pv-agregar">
                    <i class="fa fa-plus"></i> Agregar punto de venta
                </button>
            </div>
        </div>
    </div>
</div>

@include('stock.partials.campo_consulta_deposito', [
    'prefix' => 'local_venta',
    'layout' => 'form_row',
    'inputName' => 'deposito_id',
    'inputId' => 'deposito_id',
    'depositoId' => old('deposito_id', $data->deposito_id),
    'codigo' => old('deposito_codigo', $depositoModel->codigo ?? ''),
    'descripcion' => old('deposito_descripcion', $depositoModel->nombre ?? ''),
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-8',
    'required' => true,
    'label' => 'Depósito ERP',
])

<div class="form-group row">
    <label for="anita_deposito" class="col-lg-4 control-label text-right pr-2">Depósito Anita local</label>
    <div class="col-lg-3">
        <input type="number" name="anita_deposito" id="anita_deposito" class="form-control"
            value="{{ old('anita_deposito', $data->anita_deposito) }}" min="0" step="1"
            placeholder="C&oacute;d. Anita">
    </div>
    <div class="col-lg-5">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="local-sync-depositos-anita"
            title="Importa dep&oacute;sitos del bridge Anita del local sin modificar los de f&aacute;brica">
            <i class="fa fa-download"></i> Importar dep&oacute;sitos Anita del local
        </button>
        <small class="form-text text-muted d-block">
            Solo crea dep&oacute;sitos nuevos en el ERP. Si el c&oacute;digo ya existe (f&aacute;brica), no lo pisa.
            El n&uacute;mero Anita se usa en consultas de stock del local.
        </small>
        <div id="local-sync-depositos-msg" class="small mt-1"></div>
    </div>
</div>

@include('stock.partials.campo_consulta_listaprecio', [
    'prefix' => 'local_venta',
    'listaprecioId' => old('listaprecio_id', $data->listaprecio_id),
    'codigo' => old('listaprecio_codigo', $listaprecioModel->codigo ?? ''),
    'nombre' => old('listaprecio_nombre', $listaprecioModel->nombre ?? ''),
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-8',
])

@include('ventas.partials.campo_consulta_tipotransaccion', [
    'prefix' => 'fac',
    'label' => 'Tipo FAC',
    'inputName' => 'tipotransaccion_fac_id',
    'inputId' => 'tipotransaccion_fac_id',
    'tipoId' => old('tipotransaccion_fac_id', $data->tipotransaccion_fac_id),
    'abreviatura' => old('tipotransaccion_fac_abrev', $tipoFac->abreviatura ?? ''),
    'nombre' => old('tipotransaccion_fac_nombre', $tipoFac->nombre ?? ''),
    'ayuda' => 'Vacío = default de configuración.',
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-8',
])

@include('ventas.partials.campo_consulta_tipotransaccion', [
    'prefix' => 'nc',
    'label' => 'Tipo NC',
    'inputName' => 'tipotransaccion_nc_id',
    'inputId' => 'tipotransaccion_nc_id',
    'tipoId' => old('tipotransaccion_nc_id', $data->tipotransaccion_nc_id),
    'abreviatura' => old('tipotransaccion_nc_abrev', $tipoNc->abreviatura ?? ''),
    'nombre' => old('tipotransaccion_nc_nombre', $tipoNc->nombre ?? ''),
    'ayuda' => 'Vacío = default de configuración.',
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-8',
])

@include('caja.partials.campo_consulta_cuentacaja', [
    'prefix' => 'efectivo',
    'label' => 'Caja efectivo default',
    'inputName' => 'cuentacaja_efectivo_id',
    'inputId' => 'cuentacaja_efectivo_id',
    'cuentacajaId' => old('cuentacaja_efectivo_id', $data->cuentacaja_efectivo_id),
    'codigo' => old('cuentacaja_efectivo_codigo', $cuentacajaEfectivoModel->codigo ?? ''),
    'nombre' => old('cuentacaja_efectivo_nombre', $cuentacajaEfectivoModel->nombre ?? ''),
    'col_label' => 'col-lg-4 col-form-label text-right pr-2',
    'col_input' => 'col-lg-8',
    'required' => false,
    'ayuda' => 'Cuenta de efectivo del POS (fondo / arqueo). Distinta de la grilla de medios de cobro.',
])

@include('sueldos.partials.campo_consulta_cuentacontable', [
    'label' => 'Cuenta contable ventas',
    'inputName' => 'cuentacontable_venta_id',
    'inputId' => 'cuentacontable_venta_id',
    'cuentaId' => old('cuentacontable_venta_id', $data->cuentacontable_venta_id),
    'codigo' => old('cuentacontable_venta_codigo', $cuentacontableVentaModel->codigo ?? ''),
    'descripcion' => old('cuentacontable_venta_nombre', $cuentacontableVentaModel->nombre ?? ''),
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-8',
    'required' => false,
])
<small class="form-text text-muted mb-3" style="margin-left:33.333%;padding-left:15px;">
    Imputaci&oacute;n de ventas de este local (puede diferir de la cuenta de f&aacute;brica).
</small>

<div class="form-group row">
    <label class="col-lg-4 control-label text-right pr-2">Cuentas de caja del local</label>
    <div class="col-lg-8">
        <div class="card card-outline card-info mb-2">
            <div class="card-body p-2">
                <table class="table table-sm table-bordered mb-2" id="tabla-local-cuentacaja">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Cuenta de caja</th>
                            <th style="width:4rem;"></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-local-cuentacaja">
                        @foreach ($cuentasSeleccionadas as $idx => $ccRow)
                        <tr class="local-cc-row">
                            <td>
                                <div class="tm-cuentacaja-campo d-flex flex-nowrap align-items-center w-100" style="gap:4px;" id="tm_cuentacaja_local_{{ $idx }}">
                                    <input type="hidden" name="cuentacaja_ids[]" class="cuentacaja_id" value="{{ $ccRow['id'] ?? '' }}">
                                    <button type="button" title="Consulta cuentas de caja" class="btn-accion-tabla consultacuentacaja flex-shrink-0">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" class="form-control form-control-sm codigocuentacaja" value="{{ $ccRow['codigo'] ?? '' }}"
                                        placeholder="C&oacute;d." autocomplete="off" style="width:5.5rem;flex-shrink:0;">
                                    <input type="text" class="form-control form-control-sm descripcioncuentacaja text-truncate" value="{{ $ccRow['nombre'] ?? '' }}"
                                        placeholder="Descripci&oacute;n" readonly style="min-width:0;flex:1 1 auto;">
                                </div>
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" class="btn-accion-tabla local-cc-quitar" title="Quitar">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-primary" id="local-cc-agregar">
                    <i class="fa fa-plus"></i> Agregar cuenta de caja
                </button>
                <small class="form-text text-muted d-block">
                    Medios de cobro del POS para este local (tarjeta, transferencia, etc.).
                    No confundir con &laquo;Caja efectivo default&raquo; (arriba).
                </small>
            </div>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="anita_servidor" class="col-lg-4 control-label text-right pr-2">Anita servidor</label>
    <div class="col-lg-3">
        <input type="text" name="anita_servidor" id="anita_servidor" class="form-control" value="{{ old('anita_servidor', $data->anita_servidor) }}">
    </div>
    <label for="anita_ifx_server" class="col-lg-2 control-label text-right pr-2">IFX</label>
    <div class="col-lg-3">
        <input type="text" name="anita_ifx_server" id="anita_ifx_server" class="form-control" value="{{ old('anita_ifx_server', $data->anita_ifx_server) }}">
    </div>
</div>
<div class="form-group row">
    <label for="activo" class="col-lg-4 control-label text-right pr-2">Activo</label>
    <div class="col-lg-6">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" name="activo" id="activo" value="1"
            @if (old('activo', $data->activo ?? true))
                checked
            @endif
        >
    </div>
</div>
<div class="form-group row">
    <label for="observacion" class="col-lg-4 control-label text-right pr-2">Observación</label>
    <div class="col-lg-6">
        <textarea name="observacion" id="observacion" class="form-control" rows="2">{{ old('observacion', $data->observacion) }}</textarea>
    </div>
</div>

<template id="template-local-pv-row">
    <tr class="local-pv-row">
        <td class="text-center align-middle">
            <input type="radio" name="puntoventa_default_radio" class="local-pv-default" value="1" title="PV default">
        </td>
        <td>
            <div class="tm-puntoventa-campo d-flex flex-nowrap align-items-center w-100" style="gap:4px;">
                <input type="hidden" name="puntoventa_ids[]" class="puntoventa_id" value="">
                <button type="button" title="Consulta puntos de venta (F1)" class="btn-accion-tabla consultapuntoventa flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <a href="#" target="_blank" rel="noopener"
                    class="btn-accion-tabla btn-link-editar-puntoventa tooltipsC flex-shrink-0 d-none"
                    title="Abrir punto de venta en ABM">
                    <i class="fa fa-edit"></i>
                </a>
                <input type="text" class="form-control form-control-sm codigopuntoventa" value=""
                    placeholder="C&oacute;d." autocomplete="off" style="width:5.5rem;flex-shrink:0;">
                <input type="text" class="form-control form-control-sm descripcionpuntoventa text-truncate" value=""
                    placeholder="Descripci&oacute;n" readonly style="min-width:0;flex:1 1 auto;">
            </div>
        </td>
        <td class="text-center align-middle">
            <button type="button" class="btn-accion-tabla local-pv-quitar" title="Quitar">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>

<template id="template-local-cc-row">
    <tr class="local-cc-row">
        <td>
            <div class="tm-cuentacaja-campo d-flex flex-nowrap align-items-center w-100" style="gap:4px;">
                <input type="hidden" name="cuentacaja_ids[]" class="cuentacaja_id" value="">
                <button type="button" title="Consulta cuentas de caja" class="btn-accion-tabla consultacuentacaja flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm codigocuentacaja" value=""
                    placeholder="C&oacute;d." autocomplete="off" style="width:5.5rem;flex-shrink:0;">
                <input type="text" class="form-control form-control-sm descripcioncuentacaja text-truncate" value=""
                    placeholder="Descripci&oacute;n" readonly style="min-width:0;flex:1 1 auto;">
            </div>
        </td>
        <td class="text-center align-middle">
            <button type="button" class="btn-accion-tabla local-cc-quitar" title="Quitar">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
