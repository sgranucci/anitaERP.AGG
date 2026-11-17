{{-- Emisión etiquetas AP Surmar (espejo a-stkmov.c imprime_etiquetas / recepción Surmar). --}}
@php
    $ums = $unidadesmedidaSurmar ?? [];
@endphp
<div class="modal fade" id="modalEmisionEtiquetaApSurmar" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white">
                    <i class="fa fa-tags"></i>
                    Emisión etiquetas AP
                    <span class="badge badge-light text-primary ml-2" id="ms_ap_etiq_badge" style="display:none;"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="ms_ap_etiq_ctx">—</p>
                <div class="form-group row mb-2">
                    <label class="col-sm-4 col-form-label text-right pr-2 requerido" for="ms_ap_etiq_separa">En que separa</label>
                    <div class="col-sm-7">
                        <select id="ms_ap_etiq_separa" class="form-control form-control-sm ms-ap-etiq-nav">
                            @foreach ($ums as $um)
                                @php
                                    $umId = is_array($um) ? (int) ($um['id'] ?? 0) : (int) ($um->id ?? 0);
                                    $umAbr = is_array($um) ? (string) ($um['abreviatura'] ?? '') : (string) ($um->abreviatura ?? '');
                                    $umNom = is_array($um) ? (string) ($um['nombre'] ?? '') : (string) ($um->nombre ?? '');
                                @endphp
                                <option value="{{ $umId }}" data-abrev="{{ $umAbr }}">
                                    {{ $umAbr }} — {{ $umNom }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row mb-2">
                    <label class="col-sm-4 col-form-label text-right pr-2 requerido" for="ms_ap_etiq_cant_unid">Cantidad que separa</label>
                    <div class="col-sm-3">
                        <input type="number" id="ms_ap_etiq_cant_unid" class="form-control form-control-sm ms-ap-etiq-nav"
                               min="1" max="50" value="1" step="1" inputmode="numeric"
                               title="Total de unidades del lote (Anita). Se imprime en cada etiqueta.">
                    </div>
                </div>
                <div class="form-group row mb-2">
                    <label class="col-sm-4 col-form-label text-right pr-2" for="ms_ap_etiq_nro">Nro. de unidad</label>
                    <div class="col-sm-3">
                        <input type="number" id="ms_ap_etiq_nro" class="form-control form-control-sm ms-ap-etiq-nav"
                               min="1" value="1" step="1" inputmode="numeric" readonly>
                        <small class="form-text text-muted">Al confirmar avanza a la siguiente unidad.</small>
                    </div>
                </div>
                <div class="form-group row mb-2">
                    <label class="col-sm-4 col-form-label text-right pr-2" for="ms_ap_etiq_piezas">Cantidad de piezas</label>
                    <div class="col-sm-3">
                        <input type="number" step="0.01" id="ms_ap_etiq_piezas" class="form-control form-control-sm ms-ap-etiq-nav" value="1">
                    </div>
                </div>
                <div class="form-group row mb-2">
                    <label class="col-sm-4 col-form-label text-right pr-2" for="ms_ap_etiq_bruto">Peso bruto</label>
                    <div class="col-sm-3">
                        <input type="number" step="0.01" id="ms_ap_etiq_bruto" class="form-control form-control-sm ms-ap-etiq-nav">
                    </div>
                </div>
                <div class="form-group row mb-2">
                    <label class="col-sm-4 col-form-label text-right pr-2" for="ms_ap_etiq_tara">Tara</label>
                    <div class="col-sm-3">
                        <input type="number" step="0.01" id="ms_ap_etiq_tara" class="form-control form-control-sm ms-ap-etiq-nav" value="0">
                    </div>
                </div>
                <div class="form-group row mb-2">
                    <label class="col-sm-4 col-form-label text-right pr-2" for="ms_ap_etiq_neto">Peso neto</label>
                    <div class="col-sm-3">
                        <input type="number" step="0.01" id="ms_ap_etiq_neto" class="form-control form-control-sm ms-ap-etiq-nav">
                    </div>
                </div>
                <div class="form-group row mb-2">
                    <label class="col-sm-4 col-form-label text-right pr-2" for="ms_ap_etiq_vto">Fecha de vencimiento</label>
                    <div class="col-sm-4">
                        <input type="date" id="ms_ap_etiq_vto" class="form-control form-control-sm ms-ap-etiq-nav">
                    </div>
                </div>
                <div class="table-responsive mt-2" style="max-height: 180px; overflow:auto;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Nro.</th>
                            <th class="text-right">Piezas</th>
                            <th class="text-right">Bruto</th>
                            <th class="text-right">Neto</th>
                            <th>Vto</th>
                            <th style="width:2.5rem;"></th>
                        </tr>
                        </thead>
                        <tbody id="ms_ap_etiq_tbody_unidades">
                        <tr class="ms-ap-etiq-empty">
                            <td colspan="6" class="text-center text-muted">Sin unidades cargadas.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mb-0 mt-2" id="ms_ap_etiq_msg"></p>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancela</button>
                <div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="ms_ap_etiq_confirmar_unidad">
                        <i class="fa fa-plus"></i> Confirma unidad y siguiente
                    </button>
                    <button type="button" class="btn btn-success btn-sm" id="ms_ap_etiq_listo">
                        <i class="fa fa-check"></i> Listo
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
