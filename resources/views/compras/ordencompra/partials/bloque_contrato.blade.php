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
    $periodoServicio = old(
        'contrato_periodo_servicio',
        $ocContrato->contrato_periodo_servicio ?? \App\Support\Compras\ContratoPeriodoServicioSupport::MES_VENCIDO
    );
    $periodoServicio = \App\Support\Compras\ContratoPeriodoServicioSupport::normalizar($periodoServicio);
    $requiereValidacionOld = old('contrato_requiere_validacion_abono');
    if ($requiereValidacionOld === null) {
        $requiereValidacion = (bool) ($ocContrato->contrato_requiere_validacion_abono ?? false);
    } else {
        $requiereValidacion = (string) $requiereValidacionOld === '1' || $requiereValidacionOld === 1 || $requiereValidacionOld === true;
    }
    $exigeIngresosOld = old('contrato_exige_ingresos');
    if ($exigeIngresosOld === null) {
        $exigeIngresos = (bool) ($ocContrato->contrato_exige_ingresos ?? false);
    } else {
        $exigeIngresos = (string) $exigeIngresosOld === '1' || $exigeIngresosOld === 1 || $exigeIngresosOld === true;
    }
    $minimoIngresos = old('contrato_minimo_ingresos', $ocContrato->contrato_minimo_ingresos ?? 1);
    $plantillaValidacionId = (int) old('contrato_validacion_plantilla_id', $ocContrato->contrato_validacion_plantilla_id ?? 0);
    $plantillasValidacion = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('validacion_abono_plantilla')) {
        $plantillasValidacion = \App\Models\Compras\Validacion_Abono_Plantilla::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);
    }
    if ($plantillaValidacionId <= 0) {
        $plantillaValidacionId = (int) (optional($plantillasValidacion->firstWhere('codigo', 'estandar'))->id
            ?? optional($plantillasValidacion->first())->id
            ?? 0);
    }
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
            El consumo se toma de las recepciones confirmadas y, para lo que no pasa por recepci&oacute;n,
            de las facturas del proveedor. Honorarios y abonos deben ir con COM para que el &aacute;rea valide la prestaci&oacute;n.
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
                        No requiere recepci&oacute;n &mdash; se factura el contrato sin COM (casos excepcionales)
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

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2">Período de servicio del remito</label>
            <div class="col-lg-8">
                <div class="custom-control custom-radio">
                    <input type="radio" id="contrato_periodo_mes_vencido" name="contrato_periodo_servicio"
                        class="custom-control-input" value="{{ \App\Support\Compras\ContratoPeriodoServicioSupport::MES_VENCIDO }}"
                        {{ $periodoServicio === \App\Support\Compras\ContratoPeriodoServicioSupport::MES_VENCIDO ? 'checked' : '' }}
                        {{ $soloLectura ? 'disabled' : '' }}>
                    <label class="custom-control-label" for="contrato_periodo_mes_vencido">
                        Mes vencido
                    </label>
                    <small class="form-text text-muted mt-0">
                        El remito de agosto se emite cuando agosto ya cerró; se controlan los ingresos de ese mes cerrado.
                    </small>
                </div>
                <div class="custom-control custom-radio mt-2">
                    <input type="radio" id="contrato_periodo_mismo_mes" name="contrato_periodo_servicio"
                        class="custom-control-input" value="{{ \App\Support\Compras\ContratoPeriodoServicioSupport::MISMO_MES }}"
                        {{ $periodoServicio === \App\Support\Compras\ContratoPeriodoServicioSupport::MISMO_MES ? 'checked' : '' }}
                        {{ $soloLectura ? 'disabled' : '' }}>
                    <label class="custom-control-label" for="contrato_periodo_mismo_mes">
                        Dentro del mismo mes
                    </label>
                    <small class="form-text text-muted mt-0">
                        El remito cubre del 1 a la fecha del remito; se controla hasta esa fecha.
                    </small>
                </div>
                <small class="form-text text-muted">
                    No importa cuándo llega el papel: importa el período que cubre el remito.
                    Honorarios y abonos también van por recepción COM para que el área valide la prestación.
                </small>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2">Validaciones obligatorias</label>
            <div class="col-lg-8">
                <div class="custom-control custom-checkbox">
                    <input type="hidden" name="contrato_requiere_validacion_abono" value="0">
                    <input type="checkbox" class="custom-control-input" id="contrato_requiere_validacion_abono"
                        name="contrato_requiere_validacion_abono" value="1"
                        {{ $requiereValidacion ? 'checked' : '' }} {{ $soloLectura ? 'disabled' : '' }}>
                    <label class="custom-control-label" for="contrato_requiere_validacion_abono">
                        Requiere validación de abono antes de grabar la recepción (o la factura, si no hay COM)
                    </label>
                </div>
                <div id="oc-contrato-plantilla-validacion" class="mt-2" style="{{ $requiereValidacion || $exigeIngresos ? '' : 'display:none;' }}">
                    <small class="text-muted d-block">Plantilla de preguntas</small>
                    <select name="contrato_validacion_plantilla_id" id="contrato_validacion_plantilla_id"
                        class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                        @forelse ($plantillasValidacion as $plantilla)
                            <option value="{{ $plantilla->id }}" {{ $plantillaValidacionId === (int) $plantilla->id ? 'selected' : '' }}>
                                {{ $plantilla->nombre }}
                            </option>
                        @empty
                            <option value="">No hay plantillas cargadas</option>
                        @endforelse
                    </select>
                    <small class="form-text text-muted">
                        Hoy hay una sola: las 5 preguntas estándar (servicio, conformidad, ingresos, monto, reclamos).
                        Otras plantillas se agregan en <code>validacion_abono_plantilla</code> (P1: ABM).
                    </small>
                </div>
                <div class="custom-control custom-checkbox mt-3">
                    <input type="hidden" name="contrato_exige_ingresos" value="0">
                    <input type="checkbox" class="custom-control-input" id="contrato_exige_ingresos"
                        name="contrato_exige_ingresos" value="1"
                        {{ $exigeIngresos ? 'checked' : '' }} {{ $soloLectura ? 'disabled' : '' }}>
                    <label class="custom-control-label" for="contrato_exige_ingresos">
                        No habilitar confirmación ni pago si el proveedor no registró tickets de ingreso en el período
                    </label>
                </div>
                <div id="oc-contrato-minimo-ingresos" class="form-group row mt-2 mb-0" style="{{ $exigeIngresos ? '' : 'display:none;' }}">
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Mínimo de ingresos requeridos</small>
                        <input type="number" min="1" max="99" step="1" name="contrato_minimo_ingresos"
                            id="contrato_minimo_ingresos" class="form-control"
                            value="{{ $minimoIngresos !== null && $minimoIngresos !== '' ? $minimoIngresos : 1 }}"
                            {{ $soloLectura ? 'readonly' : '' }}>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Período que se controla</small>
                        <input type="text" class="form-control" value="Según período de servicio del remito ↑" readonly>
                    </div>
                </div>
            </div>
        </div>

    @php
    $esSuscripcion = (bool) old('es_suscripcion', $ocContrato->es_suscripcion ?? false);
    $suscripcionNombre = old('suscripcion_nombre', $ocContrato->suscripcion_nombre ?? '');
    $suscripcionPeriodicidad = old('suscripcion_periodicidad', $ocContrato->suscripcion_periodicidad ?? 'mensual');
    $suscripcionMonto = old('suscripcion_monto_periodo', $ocContrato->suscripcion_monto_periodo ?? '');
    $suscripcionTol = old('suscripcion_tolerancia_pct', $ocContrato->suscripcion_tolerancia_pct ?? \App\Support\Compras\SuscripcionSupport::TOLERANCIA_DEFAULT_PCT);
    $suscripcionTarjeta = old('suscripcion_tarjeta_ult4', $ocContrato->suscripcion_tarjeta_ult4 ?? '');
    $suscripcionSolicitante = old('suscripcion_solicitante', $ocContrato->suscripcion_solicitante ?? '');
    $suscripcionCcRel = $ocContrato->centrocostos ?? null;
    $suscripcionCcId = (int) old(
        'centrocosto_id',
        $ocContrato->centrocosto_id
            ?? (auth()->user()->centrocosto_id ?? 0)
    );
    if ($suscripcionCcId > 0 && (! $suscripcionCcRel || (int) $suscripcionCcRel->id !== $suscripcionCcId)) {
        $suscripcionCcRel = collect($centrocosto_query ?? [])->firstWhere('id', $suscripcionCcId)
            ?? $suscripcionCcRel;
    }
    $suscripcionCcCodigo = old('centrocosto_codigo', $suscripcionCcRel->codigo ?? '');
    $suscripcionCcNombre = old('centrocosto_nombre', $suscripcionCcRel->nombre ?? '');
@endphp

        <div class="form-group row border-top pt-3 mt-2">
            <label class="col-lg-4 control-label text-right pr-2">Suscripción (tarjeta)</label>
            <div class="col-lg-8">
                <div class="custom-control custom-checkbox">
                    <input type="hidden" name="es_suscripcion" value="0">
                    <input type="checkbox" class="custom-control-input" id="es_suscripcion" name="es_suscripcion" value="1"
                        {{ $esSuscripcion ? 'checked' : '' }} {{ $soloLectura ? 'disabled' : '' }}>
                    <label class="custom-control-label" for="es_suscripcion">
                        <strong>Es suscripción SaaS / tarjeta corporativa</strong>
                        <small class="text-muted d-block">
                            Servicio que se paga con tarjeta (Adobe, Zoom, etc.). Queda en el módulo Suscripciones
                            y se concilia mes a mes contra el resumen de la tarjeta.
                        </small>
                    </label>
                </div>
            </div>
        </div>

        <div id="oc-suscripcion-campos" class="mb-2" style="{{ $esSuscripcion ? '' : 'display:none;' }}">
            <div class="form-group row mb-0">
                <div class="offset-lg-4 col-lg-8">
                    <div class="border rounded bg-light px-3 pt-3 pb-2">
                        <div class="form-row">
                            <div class="form-group col-md-8 mb-3">
                                <label class="requerido mb-1" for="suscripcion_nombre">Nombre del servicio</label>
                                <input type="text" name="suscripcion_nombre" id="suscripcion_nombre" class="form-control" maxlength="180"
                                    value="{{ $suscripcionNombre }}" {{ $soloLectura ? 'readonly' : '' }}
                                    placeholder="Ej: Adobe Creative Cloud">
                                <small class="text-muted">Nombre comercial: así figura en listados, PDF y conciliación.</small>
                            </div>
                            <div class="form-group col-md-4 mb-3">
                                <label class="mb-1" for="suscripcion_solicitante">Quién lo pidió</label>
                                <input type="text" name="suscripcion_solicitante" id="suscripcion_solicitante" class="form-control" maxlength="120"
                                    value="{{ $suscripcionSolicitante }}" {{ $soloLectura ? 'readonly' : '' }}
                                    placeholder="Ej: Juan Pérez">
                                <small class="text-muted">Referencia libre (no es usuario AnitaERP).</small>
                            </div>
                        </div>

                        {{-- Área = centro de costo (modal F1). --}}
                        @include('contable.partials.campo_consulta_centrocosto', [
                            'prefix' => 'oc_suscripcion',
                            'layout' => 'inline',
                            'label' => 'Área que lo consume',
                            'inputName' => 'centrocosto_id',
                            'inputId' => 'centrocosto_oc_suscripcion_id',
                            'centrocostoId' => $suscripcionCcId ?: '',
                            'codigo' => $suscripcionCcCodigo,
                            'descripcion' => $suscripcionCcNombre,
                            'solo_lectura' => $soloLectura,
                            'required' => false,
                            'mostrar_editar' => true,
                            'ayuda' => 'Centro de costo del sector. El gerente de Suscripciones › Aprobadores autoriza el alta.',
                        ])

                        <div class="form-row mt-1">
                            <div class="form-group col-sm-6 col-md-3 mb-3">
                                <label class="requerido mb-1" for="suscripcion_monto_periodo">Monto del período</label>
                                <input type="number" step="0.01" min="0" name="suscripcion_monto_periodo" id="suscripcion_monto_periodo"
                                    class="form-control" value="{{ $suscripcionMonto }}" {{ $soloLectura ? 'readonly' : '' }}
                                    placeholder="0.00">
                                <small class="text-muted">Lo que cobra la tarjeta cada ciclo.</small>
                            </div>
                            <div class="form-group col-sm-6 col-md-3 mb-3">
                                <label class="requerido mb-1" for="suscripcion_periodicidad">Cada cuánto</label>
                                <select name="suscripcion_periodicidad" id="suscripcion_periodicidad" class="form-control"
                                    {{ $soloLectura ? 'disabled' : '' }}>
                                    <option value="mensual" @selected($suscripcionPeriodicidad === 'mensual')>Mensual</option>
                                    <option value="anual" @selected($suscripcionPeriodicidad === 'anual')>Anual</option>
                                </select>
                            </div>
                            <div class="form-group col-sm-6 col-md-3 mb-3">
                                <label class="mb-1" for="suscripcion_tolerancia_pct">Tolerancia %</label>
                                <input type="number" step="0.01" min="0" max="100" name="suscripcion_tolerancia_pct"
                                    id="suscripcion_tolerancia_pct" class="form-control"
                                    value="{{ $suscripcionTol }}" {{ $soloLectura ? 'readonly' : '' }} placeholder="10">
                                <small class="text-muted">Holgura TC/IVA. Tope = monto × (1 + %).</small>
                            </div>
                            <div class="form-group col-sm-6 col-md-3 mb-3">
                                <label class="requerido mb-1" for="suscripcion_tarjeta_ult4">Últimos 4 de la tarjeta</label>
                                <input type="text" name="suscripcion_tarjeta_ult4" id="suscripcion_tarjeta_ult4" class="form-control"
                                    maxlength="4" pattern="\d{4}" inputmode="numeric" autocomplete="off"
                                    value="{{ $suscripcionTarjeta }}" {{ $soloLectura ? 'readonly' : '' }} placeholder="4821">
                                <small class="text-muted">Para cruzar el extracto.</small>
                            </div>
                        </div>

                        <p class="text-muted small mb-1">
                            Si no cargás <em>tope del contrato</em> arriba, se usa el tope autorizado (monto × tolerancia).
                            Para facturar sin remito: marcá <em>No requiere recepción</em> e imputá con la cuenta del contrato.
                        </p>
                    </div>
                </div>
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
