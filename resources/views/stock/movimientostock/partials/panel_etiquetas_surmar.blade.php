{{-- Piqueo etiquetas Surmar (AP/DES). Visible solo empresa Surmar + tipo AP/DES. --}}
@php
    $surmarEmpresaId = \App\Support\Stock\SurmarSupport::EMPRESA_ID;
    // Entorno Bierzo+Surmar: el panel se muestra/oculta en JS según #empresa_id y tipo AP/DES.
    $surmarEntornoOk = \App\Support\Stock\SurmarSupport::esEmpresaSurmar($surmarEmpresaId);
@endphp
<div id="ms-panel-etiquetas-surmar" class="card card-outline card-info mb-3" style="display:none;"
     data-empresa-surmar="{{ $surmarEmpresaId }}"
     data-surmar-activo="{{ $surmarEntornoOk ? '1' : '0' }}"
     data-tipos="AP,DES,TRA">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-barcode"></i> Etiquetas Surmar a consumir</h3>
    </div>
    <div class="card-body py-3">
        <p class="text-muted small mb-2">
            Escaneá el <strong>ID ERP</strong> o el código Anita
            <code>sku-nint-nap</code> / <code>nint-nap</code> (etiquetas viejas Anita o nuevas anitaERP).
            Solo <strong>DISPONIBLE</strong>. Obligatorio para AP, DES y TRA.
        </p>
        <div class="form-row align-items-end">
            <div class="form-group col-md-6 mb-2">
                <label for="ms_etiqueta_scan">Leer etiqueta</label>
                <div class="input-group">
                    <input type="text" id="ms_etiqueta_scan" class="form-control" autocomplete="off"
                           placeholder="ID o sku-nint-nap" disabled>
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-primary" id="ms_etiqueta_agregar" disabled>
                            <i class="fa fa-plus"></i> Agregar
                        </button>
                    </div>
                </div>
            </div>
            <div class="form-group col-md-6 mb-2">
                <span id="ms_etiqueta_msg" class="small"></span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" id="ms-tabla-etiquetas-surmar">
                <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>ID</th>
                    <th>SKU</th>
                    <th>Descripción</th>
                    <th class="text-right">Neto</th>
                    <th>Lote</th>
                    <th>Depósito</th>
                    <th class="text-center" style="width:4rem;">Quitar</th>
                </tr>
                </thead>
                <tbody id="ms-tbody-etiquetas-surmar">
                <tr class="ms-etiq-empty">
                    <td colspan="7" class="text-center text-muted">Sin etiquetas piqueadas.</td>
                </tr>
                </tbody>
                <tfoot>
                <tr>
                    <th colspan="3" class="text-right">Total neto</th>
                    <th class="text-right" id="ms_etiqueta_total_neto">0.00</th>
                    <th colspan="3"></th>
                </tr>
                </tfoot>
            </table>
        </div>
        <div id="ms-etiquetas-consumo-hiddens"></div>
        <div class="form-check mt-2">
            <input type="checkbox" class="form-check-input" name="imprimir_etiquetas_surmar" id="imprimir_etiquetas_surmar" value="1" checked>
            <label class="form-check-label" for="imprimir_etiquetas_surmar">Imprimir etiquetas nuevas (ZPL) al guardar</label>
        </div>
    </div>
</div>
<input type="hidden" id="ms-resolver-etiqueta-surmar-url" value="{{ route('movimientostock_resolver_etiqueta_surmar') }}">
<input type="hidden" id="ms-zpl-etiquetas-surmar-url" value="{{ route('movimientostock_zpl_etiquetas_surmar') }}">
