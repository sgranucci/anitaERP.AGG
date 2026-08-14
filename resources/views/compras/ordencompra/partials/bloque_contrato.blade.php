@php
    $ocContrato = (isset($data) && $data) ? $data : null;
    $esContrato = (bool) old('es_contrato', $ocContrato->es_contrato ?? false);
    $autoRenovable = (bool) old('contrato_auto_renovable', $ocContrato->contrato_auto_renovable ?? false);
    $vigenciaDesde = old('contrato_vigencia_desde', optional($ocContrato->contrato_vigencia_desde ?? null)->format('Y-m-d'));
    $vigenciaHasta = old('contrato_vigencia_hasta', optional($ocContrato->contrato_vigencia_hasta ?? null)->format('Y-m-d'));
    $montoTope = old('contrato_monto_tope', $ocContrato->contrato_monto_tope ?? null);
    $monedaTopeId = (int) old('contrato_moneda_id', $ocContrato->contrato_moneda_id ?? 0);
    $diasPreaviso = old('contrato_dias_preaviso', $ocContrato->contrato_dias_preaviso ?? null);
    $diasAviso = old('contrato_dias_aviso', $ocContrato->contrato_dias_aviso ?? '');
    $responsableId = (int) old('contrato_responsable_id', $ocContrato->contrato_responsable_id ?? 0);
    $diasAvisoDefault = (string) config('compras.contratos_vencimiento.dias_aviso', '60,30,15');
    $requiereRecepcionOld = old('contrato_requiere_recepcion');
    if ($requiereRecepcionOld === null) {
        $requiereRecepcion = $ocContrato
            ? (bool) ($ocContrato->contrato_requiere_recepcion ?? true)
            : true;
    } else {
        $requiereRecepcion = (string) $requiereRecepcionOld === '1' || $requiereRecepcionOld === 1 || $requiereRecepcionOld === true;
    }
    $imputacionContable = old(
        'contrato_imputacion_contable',
        $ocContrato->contrato_imputacion_contable ?? \App\Support\Compras\OrdencompraContratoRutaFacturaSupport::IMPUTACION_ARTICULOS
    );
    $imputacionContable = \App\Support\Compras\OrdencompraContratoRutaFacturaSupport::normalizarImputacion($imputacionContable);
    $cuentaContratoId = (int) old('contrato_cuentacontable_id', $ocContrato->contrato_cuentacontable_id ?? 0);
    $cuentaContrato = $ocContrato->contrato_cuentacontables ?? null;
    $cuentaContratoCodigo = old('contrato_cuentacontable_codigo', $cuentaContrato->codigo ?? '');
    $cuentaContratoNombre = old('contrato_cuentacontable_nombre', $cuentaContrato->nombre ?? '');
    if ($cuentaContratoId > 0 && ($cuentaContratoCodigo === '' || $cuentaContratoCodigo === null)) {
        $ctaContrato = \App\Models\Contable\Cuentacontable::query()->find($cuentaContratoId);
        $cuentaContratoCodigo = $ctaContrato->codigo ?? '';
        $cuentaContratoNombre = $ctaContrato->nombre ?? '';
    }
    $puedeAbrirAbmCuentaContrato = can('editar-cuentas-contables', false) || can('listar-cuentas-contables', false);
    $mostrarCuentaContrato = $esContrato && ! $requiereRecepcion
        && $imputacionContable === \App\Support\Compras\OrdencompraContratoRutaFacturaSupport::IMPUTACION_MANUAL;
@endphp

<div class="card card-outline card-info mb-3">
    <div class="card-header">
        <div class="custom-control custom-checkbox">
            <input type="hidden" name="es_contrato" value="0">
            <input type="checkbox" class="custom-control-input" id="es_contrato" name="es_contrato" value="1"
                {{ $esContrato ? 'checked' : '' }} {{ $soloLectura ? 'disabled' : '' }}>
            <label class="custom-control-label" for="es_contrato">
                <strong>Contrato / OC abierta</strong>
                <small class="text-muted">(abono, honorarios, servicio recurrente)</small>
            </label>
        </div>
    </div>
    <div class="card-body" id="oc-contrato-campos" style="{{ $esContrato ? '' : 'display:none;' }}">
        <p class="text-muted small mb-3">
            El sistema avisa por mail cuando se acerca el fin de vigencia, cuando llega la fecha l&iacute;mite
            para notificar la no renovaci&oacute;n y cuando el consumo alcanza el porcentaje configurado del tope.
            El consumo se toma de las recepciones confirmadas y, para lo que no pasa por recepci&oacute;n
            (abonos, honorarios), de las facturas del proveedor.
            La <strong>ruta de facturaci&oacute;n</strong> (con o sin COM) y el origen de la
            <strong>cuenta contable</strong> se usan al cargar facturas de este contrato.
        </p>

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2">Recepci&oacute;n para facturar</label>
            <div class="col-lg-8">
                <div class="custom-control custom-radio">
                    <input type="radio" id="contrato_requiere_recepcion_si" name="contrato_requiere_recepcion"
                        class="custom-control-input" value="1"
                        {{ $requiereRecepcion ? 'checked' : '' }} {{ $soloLectura ? 'disabled' : '' }}>
                    <label class="custom-control-label" for="contrato_requiere_recepcion_si">
                        Obligatoria &mdash; las facturas se cargan contra recepci&oacute;n COM
                    </label>
                </div>
                <div class="custom-control custom-radio mt-1">
                    <input type="radio" id="contrato_requiere_recepcion_no" name="contrato_requiere_recepcion"
                        class="custom-control-input" value="0"
                        {{ ! $requiereRecepcion ? 'checked' : '' }} {{ $soloLectura ? 'disabled' : '' }}>
                    <label class="custom-control-label" for="contrato_requiere_recepcion_no">
                        No requiere recepci&oacute;n &mdash; se factura el contrato sin COM (abonos, honorarios)
                    </label>
                </div>
            </div>
        </div>

        <div class="form-group row" id="oc-contrato-imputacion" style="{{ $requiereRecepcion ? 'display:none;' : '' }}">
            <label class="col-lg-4 control-label text-right pr-2">Cuenta contable de las facturas</label>
            <div class="col-lg-8">
                <div class="custom-control custom-radio">
                    <input type="radio" id="contrato_imputacion_articulos" name="contrato_imputacion_contable"
                        class="custom-control-input" value="{{ \App\Support\Compras\OrdencompraContratoRutaFacturaSupport::IMPUTACION_ARTICULOS }}"
                        {{ $imputacionContable === \App\Support\Compras\OrdencompraContratoRutaFacturaSupport::IMPUTACION_ARTICULOS ? 'checked' : '' }}
                        {{ $soloLectura ? 'disabled' : '' }}>
                    <label class="custom-control-label" for="contrato_imputacion_articulos">
                        De los art&iacute;culos de la orden de compra
                    </label>
                </div>
                <div class="custom-control custom-radio mt-1">
                    <input type="radio" id="contrato_imputacion_manual" name="contrato_imputacion_contable"
                        class="custom-control-input" value="{{ \App\Support\Compras\OrdencompraContratoRutaFacturaSupport::IMPUTACION_MANUAL }}"
                        {{ $imputacionContable === \App\Support\Compras\OrdencompraContratoRutaFacturaSupport::IMPUTACION_MANUAL ? 'checked' : '' }}
                        {{ $soloLectura ? 'disabled' : '' }}>
                    <label class="custom-control-label" for="contrato_imputacion_manual">
                        Cuenta indicada en este contrato
                    </label>
                </div>
                <small class="form-text text-muted">
                    Solo aplica si la ruta es sin recepci&oacute;n. El neto de la factura usa esa cuenta;
                    IVA y percepciones siguen el concepto de IVA compra.
                </small>
            </div>
        </div>

        <div class="form-group row" id="oc-contrato-cuenta-imputar" style="{{ $mostrarCuentaContrato ? '' : 'display:none;' }}">
            <label class="col-lg-4 control-label text-right pr-2 requerido">Cuenta a imputar</label>
            <div class="col-lg-8">
                <div class="tm-cuentacontable-campo d-flex flex-nowrap align-items-center" style="gap:4px;">
                    <input type="hidden" class="cuentacontable_id" name="contrato_cuentacontable_id" id="contrato_cuentacontable_id"
                        value="{{ $cuentaContratoId > 0 ? $cuentaContratoId : '' }}">
                    @if (! $soloLectura)
                        <button type="button" title="Consulta cuenta contable" class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0">
                            <i class="fa fa-search text-primary"></i>
                        </button>
                    @endif
                    @if ($puedeAbrirAbmCuentaContrato)
                        <a href="{{ $cuentaContratoId > 0 ? route('editar_cuentacontable', ['id' => $cuentaContratoId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
                           target="_blank" rel="noopener"
                           class="btn-accion-tabla btn-link-editar-cuentacontable tooltipsC flex-shrink-0 {{ $cuentaContratoId > 0 ? '' : 'd-none' }}"
                           title="Consultar cuenta contable en ABM">
                            <i class="fa fa-edit"></i>
                        </a>
                    @endif
                    <input type="text" class="codigocuentacontable form-control" id="contrato_cuentacontable_codigo"
                        name="contrato_cuentacontable_codigo"
                        value="{{ $cuentaContratoCodigo }}" placeholder="C&oacute;d." autocomplete="off"
                        style="width:6.85rem;flex-shrink:0;" {{ $soloLectura ? 'readonly' : '' }}>
                    <input type="text" class="nombrecuentacontable form-control" id="contrato_cuentacontable_nombre"
                        name="contrato_cuentacontable_nombre"
                        value="{{ $cuentaContratoNombre }}" placeholder="Descripci&oacute;n" readonly
                        style="min-width:0;flex:1 1 auto;">
                </div>
                <small class="form-text text-muted">
                    Cuenta DEBE del neto de cada factura de este contrato. C&oacute;digo + Enter o lupa.
                </small>
            </div>
        </div>

        <div class="form-group row align-items-end">
            <label for="contrato_vigencia_desde" class="col-lg-4 control-label text-right pr-2">Vigencia</label>
            <div class="col-lg-4">
                <small class="text-muted d-block">Desde</small>
                <input type="date" name="contrato_vigencia_desde" id="contrato_vigencia_desde" class="form-control"
                    value="{{ $vigenciaDesde }}" {{ $soloLectura ? 'readonly' : '' }}>
            </div>
            <div class="col-lg-4">
                <small class="text-muted d-block">Hasta</small>
                <input type="date" name="contrato_vigencia_hasta" id="contrato_vigencia_hasta" class="form-control"
                    value="{{ $vigenciaHasta }}" {{ $soloLectura ? 'readonly' : '' }}>
            </div>
        </div>

        <div class="form-group row align-items-end">
            <label for="contrato_monto_tope" class="col-lg-4 control-label text-right pr-2">Monto contratado</label>
            <div class="col-lg-4">
                <small class="text-muted d-block">Tope (vac&iacute;o = sin tope)</small>
                <input type="number" step="0.0001" min="0" name="contrato_monto_tope" id="contrato_monto_tope"
                    class="form-control" value="{{ $montoTope }}" {{ $soloLectura ? 'readonly' : '' }}>
            </div>
            <div class="col-lg-4">
                <small class="text-muted d-block">Moneda del tope</small>
                <select name="contrato_moneda_id" id="contrato_moneda_id" class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                    <option value="">Moneda local</option>
                    @foreach ($moneda_query as $row)
                        <option value="{{ $row->id }}" {{ $monedaTopeId === (int) $row->id ? 'selected' : '' }}>
                            {{ $row->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group row align-items-end">
            <label for="contrato_auto_renovable" class="col-lg-4 control-label text-right pr-2">Renovaci&oacute;n</label>
            <div class="col-lg-4">
                <div class="custom-control custom-checkbox mt-2">
                    <input type="hidden" name="contrato_auto_renovable" value="0">
                    <input type="checkbox" class="custom-control-input" id="contrato_auto_renovable"
                        name="contrato_auto_renovable" value="1"
                        {{ $autoRenovable ? 'checked' : '' }} {{ $soloLectura ? 'disabled' : '' }}>
                    <label class="custom-control-label" for="contrato_auto_renovable">Se renueva autom&aacute;ticamente</label>
                </div>
            </div>
            <div class="col-lg-4">
                <small class="text-muted d-block">D&iacute;as de preaviso para no renovar</small>
                <input type="number" min="0" max="365" step="1" name="contrato_dias_preaviso" id="contrato_dias_preaviso"
                    class="form-control" value="{{ $diasPreaviso }}" {{ $soloLectura ? 'readonly' : '' }}>
            </div>
        </div>

        <div class="form-group row">
            <div class="col-lg-8 offset-lg-4">
                <small class="text-muted">
                    Con renovaci&oacute;n autom&aacute;tica el aviso se calcula sobre el <strong>l&iacute;mite de preaviso</strong>
                    (fin de vigencia menos estos d&iacute;as): pasada esa fecha el contrato se renueva solo.
                </small>
            </div>
        </div>

        <div class="form-group row">
            <label for="contrato_dias_aviso" class="col-lg-4 control-label text-right pr-2">D&iacute;as de aviso</label>
            <div class="col-lg-8">
                <input type="text" name="contrato_dias_aviso" id="contrato_dias_aviso" class="form-control" maxlength="60"
                    placeholder="{{ $diasAvisoDefault }}" value="{{ $diasAviso }}" {{ $soloLectura ? 'readonly' : '' }}>
                <small class="form-text text-muted">
                    Separados por coma. Vac&iacute;o usa el default del sistema ({{ $diasAvisoDefault }}).
                    Cada umbral avisa una sola vez.
                </small>
            </div>
        </div>

        <div class="form-group row">
            <label for="contrato_responsable_id" class="col-lg-4 control-label text-right pr-2">Responsable</label>
            <div class="col-lg-8">
                <select name="contrato_responsable_id" id="contrato_responsable_id" class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                    <option value="">—</option>
                    @foreach ($usuario_contrato_query as $row)
                        <option value="{{ $row->id }}" {{ $responsableId === (int) $row->id ? 'selected' : '' }}>
                            {{ $row->nombre }}
                        </option>
                    @endforeach
                </select>
                <small class="form-text text-muted">Recibe siempre los avisos de este contrato, adem&aacute;s de los destinatarios configurados.</small>
            </div>
        </div>

        @if (! empty($oc_contrato_resumen))
            <div class="alert alert-light border mb-0">
                <strong>Estado actual:</strong>
                Consumido {{ $oc_contrato_resumen['consumido_texto'] }}
                @if ($oc_contrato_resumen['tope_texto'] !== '')
                    de {{ $oc_contrato_resumen['tope_texto'] }} ({{ $oc_contrato_resumen['porcentaje_texto'] }}%)
                @endif
                @if ($oc_contrato_resumen['vence_texto'] !== '')
                    &middot; Vence el {{ $oc_contrato_resumen['vence_texto'] }} ({{ $oc_contrato_resumen['dias_texto'] }})
                @endif
                <div class="small text-muted mt-1">
                    Recibido {{ $oc_contrato_resumen['recibido_texto'] }}
                    &middot; Facturado {{ $oc_contrato_resumen['facturado_texto'] }}
                    &middot; Origen del consumo: {{ $oc_contrato_resumen['origen_texto'] }}
                </div>
            </div>
        @endif
    </div>
</div>
