{{-- Piqueo etiquetas Surmar por renglón (AP/DES/TRA). Layout split con la grilla de ítems. --}}
@php
    $surmarEmpresaId = \App\Support\Stock\SurmarSupport::EMPRESA_ID;
    $surmarEntornoOk = \App\Support\Stock\SurmarSupport::esEmpresaSurmar($surmarEmpresaId);
    $etiquetasSurmarInicial = $etiquetasSurmarPorLinea ?? [];
@endphp
<div id="ms-panel-etiquetas-surmar" class="card card-outline card-info mb-0 h-100"
     style="display:none;"
     data-empresa-surmar="{{ $surmarEmpresaId }}"
     data-surmar-activo="{{ $surmarEntornoOk ? '1' : '0' }}"
     data-tipos="AP,DES,TRA">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">
            <i class="fa fa-barcode"></i>
            Etiquetas del ítem
        </h3>
    </div>
    <div class="card-body py-2 d-flex flex-column" style="min-height: 280px;">
        <p class="text-muted small mb-2" id="ms_etiqueta_linea_ctx">
            Seleccioná un renglón a la izquierda para piquear sus etiquetas.
        </p>
        <p class="text-muted small mb-2">
            Escaneá el <strong>ID ERP</strong> o el código Anita
            <code>sku-nint-nap</code> / <code>nint-nap</code>. Solo <strong>DISPONIBLE</strong>.
            Obligatorio por ítem en AP, DES y TRA.
        </p>
        <div class="form-row align-items-end">
            <div class="form-group col-12 mb-2">
                <label for="ms_etiqueta_scan">Leer etiqueta</label>
                <div class="input-group input-group-sm">
                    <input type="text" id="ms_etiqueta_scan" class="form-control" autocomplete="off"
                           placeholder="ID o sku-nint-nap" disabled>
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-primary" id="ms_etiqueta_agregar" disabled>
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <span id="ms_etiqueta_msg" class="small"></span>
            </div>
        </div>
        <div class="table-responsive flex-grow-1" style="max-height: 320px; overflow:auto;">
            <table class="table table-sm table-bordered mb-0" id="ms-tabla-etiquetas-surmar">
                <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>ID</th>
                    <th>SKU</th>
                    <th class="text-right">Neto</th>
                    <th>Lote</th>
                    <th class="text-center" style="width:2.5rem;"></th>
                </tr>
                </thead>
                <tbody id="ms-tbody-etiquetas-surmar">
                <tr class="ms-etiq-empty">
                    <td colspan="5" class="text-center text-muted">Sin etiquetas.</td>
                </tr>
                </tbody>
                <tfoot>
                <tr>
                    <th colspan="2" class="text-right">Total neto</th>
                    <th class="text-right" id="ms_etiqueta_total_neto">0.00</th>
                    <th colspan="2"></th>
                </tr>
                </tfoot>
            </table>
        </div>
        <div id="ms-etiquetas-consumo-hiddens" class="d-none"></div>
        <div class="form-check mt-2 mb-0">
            <input type="checkbox" class="form-check-input" name="imprimir_etiquetas_surmar" id="imprimir_etiquetas_surmar" value="1" checked>
            <label class="form-check-label small" for="imprimir_etiquetas_surmar">Imprimir etiquetas nuevas (ZPL) al guardar</label>
        </div>
    </div>
</div>
<input type="hidden" id="ms-resolver-etiqueta-surmar-url" value="{{ route('movimientostock_resolver_etiqueta_surmar') }}">
<input type="hidden" id="ms-zpl-etiquetas-surmar-url" value="{{ route('movimientostock_zpl_etiquetas_surmar') }}">
<script type="application/json" id="ms-etiquetas-surmar-inicial">@json($etiquetasSurmarInicial)</script>

<style>
    #ms-surmar-workbench.ms-surmar-activo #tabla-items-movimientostock tbody tr.item-pedido {
        cursor: pointer;
    }
    #ms-surmar-workbench.ms-surmar-activo #tabla-items-movimientostock tbody tr.item-pedido.ms-linea-activa {
        background-color: #D6EAF8 !important;
        outline: 2px solid #2471A3;
        outline-offset: -2px;
    }
    #ms-surmar-workbench.ms-surmar-activo #tabla-items-movimientostock tbody tr.item-pedido .ms-etiq-badge {
        display: inline-block;
        min-width: 1.4rem;
        font-size: 0.7rem;
        font-weight: 600;
    }
    #ms-surmar-workbench:not(.ms-surmar-activo) .ms-etiq-badge-wrap {
        display: none !important;
    }
    @media (min-width: 992px) {
        #ms-surmar-workbench.ms-surmar-activo .ms-surmar-col-items {
            max-height: 70vh;
            overflow: auto;
        }
        #ms-surmar-workbench.ms-surmar-activo .ms-surmar-col-etiq {
            position: sticky;
            top: 0.5rem;
        }
    }
</style>
