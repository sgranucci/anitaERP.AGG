@php
    use App\Support\Contable\MayorFuenteConsultaSupport;
    $fuenteMayor = MayorFuenteConsultaSupport::normalizarModo($filtros['fuente_mayor'] ?? MayorFuenteConsultaSupport::MODO_ERP);
    $idPrefix = $id_prefix ?? 'mayor';
    $compact = ! empty($compact);
    $configKey = $config_key ?? 'contable.mayor_plano_cuenta.fuente_erp_hasta';
    $corteYmd = MayorFuenteConsultaSupport::corteYmd($configKey);
    $hayCorteErp = $corteYmd > 0;
    $corteCfg = trim((string) ($corte_label ?? ''));
    if ($corteCfg === '' && $hayCorteErp) {
        $corteCfg = MayorFuenteConsultaSupport::formatearYmd($corteYmd);
    }
@endphp
@if ($compact)
    <div class="mb-2">
        <label class="small font-weight-bold d-block mb-1">Fuente del mayor</label>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="fuente_mayor"
                   id="{{ $idPrefix }}-fuente-erp" value="erp"
                   @checked($fuenteMayor === 'erp')>
            <label class="form-check-label" for="{{ $idPrefix }}-fuente-erp">ERP nativo</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="fuente_mayor"
                   id="{{ $idPrefix }}-fuente-anita" value="anita"
                   @checked($fuenteMayor === 'anita')>
            <label class="form-check-label" for="{{ $idPrefix }}-fuente-anita">Anita</label>
        </div>
        <small class="form-text text-muted">
            ERP = solo asientos del ERP. Anita = solo bridge Informix.
            @if ($hayCorteErp)
                Import documentado hasta {{ $corteCfg }}.
            @endif
        </small>
    </div>
@else
    <div class="form-group row mb-2">
        <label class="{{ $col_label ?? 'col-lg-2' }} control-label">Fuente del mayor</label>
        <div class="{{ $col_input ?? 'col-lg-9' }}">
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="fuente_mayor"
                       id="{{ $idPrefix }}-fuente-erp" value="erp"
                       @checked($fuenteMayor === 'erp')>
                <label class="form-check-label" for="{{ $idPrefix }}-fuente-erp">ERP nativo</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="fuente_mayor"
                       id="{{ $idPrefix }}-fuente-anita" value="anita"
                       @checked($fuenteMayor === 'anita')>
                <label class="form-check-label" for="{{ $idPrefix }}-fuente-anita">Anita (bridge)</label>
            </div>
            <small class="form-text text-muted d-block mt-1">
                Elegí una sola fuente para todo el período (sin híbrido).
                <strong>ERP nativo</strong> lee asientos vivos del ERP.
                <strong>Anita (bridge)</strong> replica el mayor Informix (ctamov + subdiario).
                @if ($hayCorteErp)
                    Tope de importación documentado: {{ $corteCfg }}.
                @endif
            </small>
        </div>
    </div>
@endif
