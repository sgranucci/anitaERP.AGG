@extends("theme.$theme.layout")
@section('titulo')
Configuración general del sistema
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/general/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/configuracion/general/form.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-sliders"></i> Configuración general del sistema</h3>
            </div>
            <form action="{{ route('actualizar_configuracion_general') }}" id="form-general" class="form-horizontal" method="POST" autocomplete="off">
                @csrf
                @method('put')
                <div class="card-body">
                    <div class="alert alert-info py-2">
                        Estos valores mandan sobre los defaults de <code>config/</code> y <code>.env</code>.
                        Incluyen facturación (FCE MiPyME), POS, Libro IVA Digital, aprobación de artículos, umbrales de Compras / Suscripciones, la cantidad recibida de la recepción de proveedor, el concepto del débito por cheque rechazado y la cuenta del neto de la nota de crédito por pronto pago.
                    </div>

                    @foreach ($grupos as $nombreGrupo => $parametros)
                        <h5 class="mt-2 mb-3">{{ $nombreGrupo }}</h5>
                        @foreach ($parametros as $parametro)
                            @if ($parametro['tipo'] === 'cuentacaja')
                                @php
                                    $cuentaIdOld = old('parametros.'.$parametro['clave'], $parametro['valor']);
                                    $cuenta = \App\Support\Configuracion\ParametroSistemaSupport::cuentaParaFormulario((int) $cuentaIdOld);
                                @endphp
                                @include('caja.partials.campo_consulta_cuentacaja', [
                                    'prefix' => 'fce_cbu',
                                    'layout' => 'form_row',
                                    'label' => $parametro['etiqueta'],
                                    'inputName' => 'parametros['.$parametro['clave'].']',
                                    'inputId' => 'param_'.$parametro['clave'],
                                    'cuentacajaId' => $cuentaIdOld,
                                    'codigo' => $cuenta['codigo'] ?? '',
                                    'nombre' => $cuenta['nombre'] ?? '',
                                    'col_label' => 'col-lg-4 control-label text-right pr-2',
                                    'col_input' => 'col-lg-7',
                                    'required' => false,
                                    'ayuda' => $parametro['ayuda'],
                                ])
                                <div class="form-group row">
                                    <label for="fce_cbu_preview" class="col-lg-4 control-label text-right pr-2">CBU emisor</label>
                                    <div class="col-lg-7">
                                        <input type="text" id="fce_cbu_preview" class="form-control" readonly
                                            value="{{ $cuenta['cbu'] ?? '' }}"
                                            placeholder="Se completa al elegir la cuenta">
                                        <small class="form-text text-muted">ARCA FCE dato adicional 21. Si queda vacío, cada empresa usa su cuenta en pesos de Banco Macro Gerli.</small>
                                    </div>
                                </div>
                            @elseif ($parametro['tipo'] === 'concepto_ivacompra')
                                @php
                                    $conceptoOldId = (int) old('parametros.'.$parametro['clave'], $parametro['valor']);
                                    $conceptoCfg = $parametro['concepto'] ?? ['id' => 0, 'codigo' => '', 'nombre' => ''];
                                    if ((string) old('parametros.'.$parametro['clave'], '') !== '' && $conceptoOldId !== (int) ($conceptoCfg['id'] ?? 0)) {
                                        $conceptoCfg = \App\Support\Configuracion\ParametroSistemaSupport::conceptoIvacompraParaFormulario($conceptoOldId);
                                    }
                                @endphp
                                <div class="form-group row tm-concepto-ivacompra-campo" id="tm_concepto_ivacompra_ndr_cheque">
                                    <label for="param_{{ $parametro['clave'] }}_codigo" class="col-lg-4 control-label text-right pr-2">{{ $parametro['etiqueta'] }}</label>
                                    <div class="col-lg-7">
                                        <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                                            <input type="hidden" name="parametros[{{ $parametro['clave'] }}]" id="param_{{ $parametro['clave'] }}" class="concepto_ivacompra_id" value="{{ $conceptoOldId > 0 ? $conceptoOldId : '' }}">
                                            <button type="button" title="Consulta conceptos (F1)" class="btn-accion-tabla consultaconcepto-ndr-cheque flex-shrink-0">
                                                <i class="fa fa-search text-primary"></i>
                                            </button>
                                            <input type="text" class="form-control codigo_concepto_ivacompra"
                                                id="param_{{ $parametro['clave'] }}_codigo"
                                                value="{{ $conceptoCfg['codigo'] ?? '' }}"
                                                placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off"
                                                style="width: 5.5rem; flex-shrink: 0;">
                                            <input type="text" class="form-control nombre_concepto_ivacompra text-truncate"
                                                id="param_{{ $parametro['clave'] }}_nombre"
                                                value="{{ $conceptoCfg['nombre'] ?? '' }}"
                                                placeholder="Descripci&oacute;n" readonly
                                                style="min-width: 0; flex: 1 1 auto;">
                                        </div>
                                        <small class="form-text text-muted">{{ $parametro['ayuda'] }}</small>
                                    </div>
                                </div>
                            @elseif ($parametro['tipo'] === 'cuentacontable')
                                @php
                                    $cuentaContableOldId = (int) old('parametros.'.$parametro['clave'], $parametro['valor']);
                                    $cuentaContableCfg = $parametro['cuentacontable'] ?? ['id' => 0, 'codigo' => '', 'nombre' => ''];
                                    if ((string) old('parametros.'.$parametro['clave'], '') !== '' && $cuentaContableOldId !== (int) ($cuentaContableCfg['id'] ?? 0)) {
                                        $cuentaContableCfg = \App\Support\Configuracion\ParametroSistemaSupport::cuentaContableParaFormulario($cuentaContableOldId);
                                    }
                                    $empresaPlanId = (int) ($cuentaContableCfg['empresa_id'] ?? 0);
                                    if ($empresaPlanId <= 0) {
                                        $empresaPlanId = (int) (optional($empresasIibb->sortBy('id')->first())->id ?? 0);
                                    }
                                @endphp
                                <div class="form-group row">
                                    <label for="empresa_id" class="col-lg-4 control-label text-right pr-2">Empresa del plan</label>
                                    <div class="col-lg-4">
                                        <select class="form-control" id="empresa_id">
                                            @foreach ($empresasIibb as $empresaPlan)
                                                <option value="{{ (int) $empresaPlan->id }}" @selected((int) $empresaPlan->id === $empresaPlanId)>{{ $empresaPlan->nombre }}</option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">Solo para buscar la cuenta. Se guarda el código y en las otras empresas se usa el mismo.</small>
                                    </div>
                                </div>
                                @include('sueldos.partials.campo_consulta_cuentacontable', [
                                    'label' => $parametro['etiqueta'],
                                    'inputName' => 'parametros['.$parametro['clave'].']',
                                    'inputId' => 'param_'.$parametro['clave'],
                                    'cuentaId' => $cuentaContableOldId > 0 ? $cuentaContableOldId : '',
                                    'codigo' => $cuentaContableCfg['codigo'] ?? '',
                                    'descripcion' => $cuentaContableCfg['nombre'] ?? '',
                                    'col_label' => 'col-lg-4 control-label text-right pr-2',
                                    'col_input' => 'col-lg-7',
                                    'required' => false,
                                ])
                                <div class="form-group row">
                                    <div class="col-lg-7 offset-lg-4">
                                        <small class="form-text text-muted">{{ $parametro['ayuda'] }}</small>
                                    </div>
                                </div>
                            @elseif ($parametro['tipo'] === 'boolean')
                                @php
                                    $idCampo = 'param_'.$parametro['clave'];
                                    $valorBool = old('parametros.'.$parametro['clave'], $parametro['valor']);
                                    $valorBool = in_array(strtolower((string) $valorBool), ['1', 'true', 's', 'si', 'sí', 'yes', 'on'], true) ? '1' : '0';
                                @endphp
                                <div class="form-group row">
                                    <label for="{{ $idCampo }}" class="col-lg-4 control-label text-right pr-2">{{ $parametro['etiqueta'] }}</label>
                                    <div class="col-lg-4">
                                        <select class="form-control" name="parametros[{{ $parametro['clave'] }}]" id="{{ $idCampo }}" required>
                                            <option value="1" @selected($valorBool === '1')>Activo</option>
                                            <option value="0" @selected($valorBool === '0')>Inactivo</option>
                                        </select>
                                        <small class="form-text text-muted">{{ $parametro['ayuda'] }}</small>
                                    </div>
                                </div>
                            @elseif ($parametro['tipo'] === 'select')
                                @php
                                    $idCampo = 'param_'.$parametro['clave'];
                                    $valorSel = (string) old('parametros.'.$parametro['clave'], $parametro['valor']);
                                    $opciones = $parametro['opciones'] ?? [];
                                @endphp
                                <div class="form-group row">
                                    <label for="{{ $idCampo }}" class="col-lg-4 control-label text-right pr-2">{{ $parametro['etiqueta'] }}</label>
                                    <div class="col-lg-4">
                                        <select class="form-control" name="parametros[{{ $parametro['clave'] }}]" id="{{ $idCampo }}" required>
                                            @foreach ($opciones as $opValor => $opEtiqueta)
                                                <option value="{{ $opValor }}" @selected($valorSel === (string) $opValor)>{{ $opEtiqueta }}</option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">{{ $parametro['ayuda'] }}</small>
                                    </div>
                                </div>
                            @else
                                @php
                                    $idCampo = 'param_'.$parametro['clave'];
                                    $step = $parametro['tipo'] === 'entero' ? '1' : '0.01';
                                @endphp
                                <div class="form-group row">
                                    <label for="{{ $idCampo }}" class="col-lg-4 control-label text-right pr-2">{{ $parametro['etiqueta'] }}</label>
                                    <div class="col-lg-4">
                                        <input type="number"
                                            min="0"
                                            step="{{ $step }}"
                                            class="form-control"
                                            name="parametros[{{ $parametro['clave'] }}]"
                                            id="{{ $idCampo }}"
                                            value="{{ old('parametros.'.$parametro['clave'], $parametro['valor']) }}"
                                            required>
                                        <small class="form-text text-muted">{{ $parametro['ayuda'] }}</small>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    @endforeach
                </div>
                <div class="card-footer">
                    @include('includes.boton-form-editar')
                </div>
            </form>
        </div>

        <div class="card card-outline card-info mt-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-balance-scale"></i> Agentes IIBB por empresa</h3>
            </div>
            <form action="{{ route('actualizar_agentes_iibb') }}" id="form-agentes-iibb" class="form-horizontal" method="POST" autocomplete="off">
                @csrf
                @method('put')
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Tilde en qué jurisdicciones cada empresa jurídica percibe (ventas) o retiene (compras).
                        Las alícuotas y mínimos se cargan en el ABM de provincia: son del fisco, no de la empresa.
                        No es el catálogo PIVA/PNC (percepción IVA nacional).
                    </p>
                    @if (! empty($matrizIibbUsaEnv))
                        <div class="alert alert-warning py-2">
                            Todavía no hay nominación guardada por empresa. Se muestran los tildes del
                            <code>.env</code>
                            @if (! empty($matrizIibbJursEnv))
                                ({{ implode(', ', $matrizIibbJursEnv) }})
                            @else
                                (vacío: no hay <code>ANITA_AGENTE_PERCEPCION_IIBB</code>)
                            @endif
                            para todas las empresas jurídicas de esta instalación.
                            Buenos Aires es jurisdicción <strong>902</strong>; CABA es <strong>901</strong>.
                            Usá el botón <strong>Guardar agentes IIBB</strong> de esta tarjeta (no el
                            «Actualizar» de arriba). Al guardar se materializa el
                            <code>.env</code> en BD para todas las empresas y luego aplican tus tildes;
                            a partir de ahí manda esta grilla (también si destildás todo).
                        </div>
                    @endif
                    @if (count($matrizIibb) === 0)
                        <div class="alert alert-danger py-2">
                            No hay provincias con código de jurisdicción AFIP (901–924).
                            Editá <strong>Buenos Aires</strong> en el ABM de provincia y cargá jurisdicción
                            <strong>902</strong>. Sin ese código la grilla no puede armarse y el motor no percibe.
                        </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="tabla-agentes-iibb">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th rowspan="2" class="align-middle">Jur.</th>
                                    <th rowspan="2" class="align-middle">Provincia</th>
                                    @foreach ($empresasIibb as $empresa)
                                        <th colspan="2" class="text-center">{{ $empresa->nombre }}</th>
                                    @endforeach
                                </tr>
                                <tr>
                                    @foreach ($empresasIibb as $empresa)
                                        <th class="text-center">Percibe</th>
                                        <th class="text-center">Retiene</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($matrizIibb as $fila)
                                    <tr>
                                        <td>{{ $fila['jurisdiccion'] }}</td>
                                        <td>{{ $fila['nombre'] }}</td>
                                        @foreach ($empresasIibb as $empresa)
                                            @php
                                                $celda = $fila['empresas'][(int) $empresa->id] ?? ['percepcion' => false, 'retencion' => false];
                                                $base = 'agentes['.$empresa->id.']['.$fila['provincia_id'].']';
                                            @endphp
                                            <td class="text-center">
                                                <input type="hidden" name="{{ $base }}[percepcion]" value="0">
                                                <input type="checkbox" name="{{ $base }}[percepcion]" value="1"
                                                    {{ ! empty($celda['percepcion']) ? 'checked' : '' }}>
                                            </td>
                                            <td class="text-center">
                                                <input type="hidden" name="{{ $base }}[retencion]" value="0">
                                                <input type="checkbox" name="{{ $base }}[retencion]" value="1"
                                                    {{ ! empty($celda['retencion']) ? 'checked' : '' }}>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Guardar agentes IIBB
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.caja.modalconsultacuentacaja')
@include('includes.compras.modalconsultaconcepto_ivacompra')
@include('includes.contable.modalconsultacuentacontable')
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cuentacontable/consulta.js')) ?: time() }}"></script>
@endsection
