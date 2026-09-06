@php
    use App\Support\Contable\MayorFuenteConsultaSupport;
    $fuenteMayor = MayorFuenteConsultaSupport::normalizarModo($filtros['fuente_mayor'] ?? 'auto');
    $idPrefix = $id_prefix ?? 'mayor';
    $compact = ! empty($compact);
    $corteCfg = trim((string) ($corte_label ?? ''));
    if ($corteCfg === '') {
        $corteYmd = MayorFuenteConsultaSupport::corteYmd(
            $config_key ?? 'contable.mayor_plano_cuenta.fuente_erp_hasta'
        );
        $corteCfg = $corteYmd > 0
            ? MayorFuenteConsultaSupport::formatearYmd($corteYmd)
            : MayorFuenteConsultaSupport::formatearYmd(MayorFuenteConsultaSupport::CORTE_DEFAULT_YMD);
    }
@endphp
@if ($compact)
    <div class="mb-2">
        <label class="small font-weight-bold d-block mb-1">Fuente del mayor</label>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="fuente_mayor"
                   id="{{ $idPrefix }}-fuente-auto" value="auto"
                   @checked($fuenteMayor === 'auto')>
            <label class="form-check-label" for="{{ $idPrefix }}-fuente-auto">Automático</label>
        </div>
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
            ERP hasta {{ $corteCfg }}; después siempre Anita. «Anita» fuerza bridge también antes del tope.
        </small>
    </div>
@else
    <div class="form-group row mb-2">
        <label class="{{ $col_label ?? 'col-lg-2' }} control-label">Fuente del mayor</label>
        <div class="{{ $col_input ?? 'col-lg-9' }}">
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="fuente_mayor"
                       id="{{ $idPrefix }}-fuente-auto" value="auto"
                       @checked($fuenteMayor === 'auto')>
                <label class="form-check-label" for="{{ $idPrefix }}-fuente-auto">Automático</label>
            </div>
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
                Hasta el {{ $corteCfg }} el ERP puede leer asientos nativos (links azules).
                Desde el día siguiente siempre sale Anita, aunque elijas ERP.
                «Anita (bridge)» fuerza el mayor clásico también en el tramo ya migrado.
            </small>
        </div>
    </div>
@endif
