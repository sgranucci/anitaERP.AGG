<div id="tab-suitecrm" class="tab-pane fade card form9" role="tabpanel">
    <div class="card-body">
        <div id="suitecrm-alerta" class="alert alert-warning" style="display: none" role="alert"></div>

        <div class="card card-outline card-primary mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <strong>Cuenta SuiteCRM (accounts)</strong>
                @if ($suitecrmPuedeSincronizarCuenta ?? false)
                <button type="button" id="suitecrm-cuenta-sincronizar" class="btn btn-success btn-sm">
                    <i class="fa fa-refresh"></i> Sincronizar cuenta
                </button>
                @endif
            </div>
            <div class="card-body py-2">
                <div id="suitecrm-cuenta-cargando" class="text-muted" style="display: none">
                    <i class="fa fa-spinner fa-spin"></i> Consultando cuenta en SuiteCRM…
                </div>
                <div id="suitecrm-cuenta-sin-enlace" class="text-muted small" style="display: none"></div>
                <div id="suitecrm-cuenta-datos" style="display: none">
                    <table class="table table-sm table-bordered mb-0">
                        <tbody id="suitecrm-cuenta-tbody"></tbody>
                    </table>
                </div>
                <p class="text-muted small mb-0 mt-2">
                    Al guardar el cliente en Anita, si tenés permiso de sincronización, se crea o actualiza la cuenta en SuiteCRM
                    (tablas <code>accounts</code> y <code>accounts_cstm</code>) usando código y CUIT.
                </p>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Notas SuiteCRM</h5>
            @if ($suitecrmPuedeEditar ?? false)
            <button type="button" id="suitecrm-nota-nueva" class="btn btn-success btn-sm">
                <i class="fa fa-plus"></i> Nueva nota
            </button>
            @endif
        </div>
        <div id="suitecrm-notas-cargando" class="text-muted mb-2" style="display: none">
            <i class="fa fa-spinner fa-spin"></i> Cargando notas…
        </div>
        <table class="table table-sm table-bordered" id="suitecrm-notas-table">
            <thead>
                <tr>
                    <th style="width: 14%;">Fecha alta</th>
                    <th style="width: 14%;">Últ. modificación</th>
                    <th style="width: 22%;">Asunto</th>
                    <th style="width: 40%;">Descripción</th>
                    <th style="width: 10%;"></th>
                </tr>
            </thead>
            <tbody id="suitecrm-notas-tbody">
            </tbody>
        </table>
    </div>
</div>
