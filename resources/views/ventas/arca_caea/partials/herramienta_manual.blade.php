@if ($puedeInformar ?? false)
    <div class="card card-outline card-primary mt-3 mb-2" id="arca-caea-herramienta-manual"
        data-id="{{ (int) $registro->id }}"
        data-url-proximos="{{ route('arca_caea_proximos_manual', $registro->id) }}"
        data-url-preview="{{ route('arca_caea_previsualizar_manual', $registro->id) }}"
        data-url-informar="{{ route('arca_caea_informar_uno_manual', $registro->id) }}">
        <div class="card-header py-2">
            <h3 class="card-title mb-0" style="font-size:1rem;">
                <i class="fa fa-wrench"></i> Herramienta: presentar un comprobante
            </h3>
        </div>
        <div class="card-body py-2">
            <p class="small text-muted mb-2">
                Busca el próximo pendiente (último ARCA + 1) en ERP y, si no está, en Anita.
                También puede cargar PV + tipo AFIP + número a mano (útil para FCE 201).
            </p>

            <div class="mb-2">
                <button type="button" class="btn btn-outline-info btn-sm js-arca-caea-manual-proximos"
                    @disabled($procesoActivo ?? false)>
                    <i class="fa fa-search"></i> Ver próximos pendientes
                </button>
            </div>

            <div class="table-responsive mb-2 d-none" id="arca-caea-manual-proximos-wrap">
                <table class="table table-sm table-bordered mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>PV</th>
                            <th>Tipo</th>
                            <th>Último ARCA</th>
                            <th>Próximo</th>
                            <th>Fuente</th>
                            <th>Fecha / total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="arca-caea-manual-proximos-body"></tbody>
                </table>
            </div>

            <div class="form-row align-items-end">
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0" for="arca_manual_pto">PV</label>
                    <input type="number" class="form-control form-control-sm" id="arca_manual_pto" min="1" placeholder="5">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0" for="arca_manual_tipo">Tipo AFIP</label>
                    <input type="number" class="form-control form-control-sm" id="arca_manual_tipo" min="1" placeholder="201">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0" for="arca_manual_numero">Número</label>
                    <input type="number" class="form-control form-control-sm" id="arca_manual_numero" min="1" placeholder="664">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0" for="arca_manual_tipo_anita">Tipo Anita</label>
                    <input type="text" class="form-control form-control-sm" id="arca_manual_tipo_anita" maxlength="10" placeholder="FCE">
                </div>
                <div class="form-group col-md-1 mb-2">
                    <label class="small mb-0" for="arca_manual_letra">Letra</label>
                    <input type="text" class="form-control form-control-sm" id="arca_manual_letra" maxlength="1" placeholder="A">
                </div>
                <div class="form-group col-md-3 mb-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm js-arca-caea-manual-preview"
                        @disabled($procesoActivo ?? false)>
                        <i class="fa fa-eye"></i> Previsualizar
                    </button>
                    <button type="button" class="btn btn-primary btn-sm js-arca-caea-manual-informar"
                        @disabled($procesoActivo ?? false)>
                        <i class="fa fa-paper-plane"></i> Presentar
                    </button>
                </div>
            </div>

            <div id="arca-caea-manual-msg" class="small mt-1"></div>
        </div>
    </div>
@endif
