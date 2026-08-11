{{-- Modal Anita «Etiquetas proveedor»: captura + preview + Imprime / Modifica --}}
@php
    $proveedorTitulo = $proveedorNombreEtiqueta ?? 'proveedor';
    $ums = $unidadesmedida ?? collect();
@endphp
<div class="modal fade" id="modalEtiquetaProveedorSurmar" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white">
                    <i class="fa fa-tags"></i>
                    Etiquetas «{{ $proveedorTitulo }}»
                    <span class="badge badge-light text-primary ml-2" id="etiq_proceso_badge" style="display:none;"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="etiq_linea_id" value="">
                <input type="hidden" id="etiq_modo" value="alta">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group row mb-2">
                            <label class="col-sm-5 col-form-label text-right pr-2">Artículo</label>
                            <div class="col-sm-7">
                                <input type="text" id="etiq_sku" class="form-control form-control-sm" readonly>
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label class="col-sm-5 col-form-label text-right pr-2">Descripción</label>
                            <div class="col-sm-7">
                                <input type="text" id="etiq_descripcion" class="form-control form-control-sm" readonly>
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label class="col-sm-5 col-form-label text-right pr-2">Nº certificado / lote</label>
                            <div class="col-sm-7">
                                <input type="text" id="etiq_lote" class="form-control form-control-sm surmar-etiq-nav" maxlength="30">
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label class="col-sm-5 col-form-label text-right pr-2 requerido">En que separa</label>
                            <div class="col-sm-7">
                                <select id="etiq_separa" class="form-control form-control-sm surmar-etiq-nav">
                                    @foreach ($ums as $um)
                                        @php
                                            $umId = is_array($um) ? ($um['id'] ?? 0) : ($um->id ?? 0);
                                            $umAbr = is_array($um) ? ($um['abreviatura'] ?? '') : ($um->abreviatura ?? '');
                                            $umNom = is_array($um) ? ($um['nombre'] ?? '') : ($um->nombre ?? '');
                                        @endphp
                                        <option value="{{ $umId }}" data-abrev="{{ $umAbr }}">
                                            {{ $umAbr }} — {{ $umNom }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label class="col-sm-5 col-form-label text-right pr-2 requerido">Cantidad que separa</label>
                            <div class="col-sm-3">
                                <input type="number" id="etiq_cant_unid" class="form-control form-control-sm surmar-etiq-nav" min="1" max="50" value="1"
                                       title="Total de unidades del lote (Anita). Se imprime en la etiqueta; cada Imprime graba una unidad y pasa a la siguiente.">
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label class="col-sm-5 col-form-label text-right pr-2">Nro. de unidad</label>
                            <div class="col-sm-3">
                                <input type="number" id="etiq_nro_apertura" class="form-control form-control-sm surmar-etiq-nav" min="1" value="1"
                                       title="Número de esta unidad (BIN: N - Nro.: X). En proceso de alta avanza solo al imprimir.">
                                <small class="form-text text-muted" id="etiq_nro_ayuda">Al imprimir se guarda y pasa a la siguiente unidad.</small>
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label class="col-sm-5 col-form-label text-right pr-2">Cantidad de piezas</label>
                            <div class="col-sm-3">
                                <input type="number" step="0.01" id="etiq_piezas" class="form-control form-control-sm surmar-etiq-nav" value="1">
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label class="col-sm-5 col-form-label text-right pr-2">Peso bruto</label>
                            <div class="col-sm-3">
                                <input type="number" step="0.01" id="etiq_bruto" class="form-control form-control-sm surmar-etiq-nav">
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label class="col-sm-5 col-form-label text-right pr-2">Tara</label>
                            <div class="col-sm-3">
                                <input type="number" step="0.01" id="etiq_tara" class="form-control form-control-sm surmar-etiq-nav" value="0">
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label class="col-sm-5 col-form-label text-right pr-2">Peso neto</label>
                            <div class="col-sm-3">
                                <input type="number" step="0.01" id="etiq_neto" class="form-control form-control-sm surmar-etiq-nav">
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label class="col-sm-5 col-form-label text-right pr-2">Fecha de vencimiento</label>
                            <div class="col-sm-4">
                                <input type="date" id="etiq_vto" class="form-control form-control-sm surmar-etiq-nav">
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label class="col-sm-5 col-form-label text-right pr-2">Cantidad de etiquetas</label>
                            <div class="col-sm-3">
                                <input type="number" id="etiq_copias" class="form-control form-control-sm surmar-etiq-nav" min="1" max="10" value="1"
                                       title="Copias a imprimir (1–10)">
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label class="col-sm-5 col-form-label text-right pr-2">Destino impresión</label>
                            <div class="col-sm-7">
                                <div class="btn-group btn-group-toggle" data-toggle="buttons" id="etiq_destino_group">
                                    <label class="btn btn-outline-primary btn-sm active" id="etiq_destino_lbl_impresora">
                                        <input type="radio" name="etiq_destino" id="etiq_destino_impresora" value="impresora" checked autocomplete="off">
                                        Impresora red
                                    </label>
                                    <label class="btn btn-outline-secondary btn-sm" id="etiq_destino_lbl_pdf">
                                        <input type="radio" name="etiq_destino" id="etiq_destino_pdf" value="pdf" autocomplete="off">
                                        PDF
                                    </label>
                                </div>
                                <small class="form-text text-muted mb-0">
                                    Impresora = Zebra en red vía «Configura salida». PDF = archivo para ver/imprimir.
                                </small>
                            </div>
                        </div>
                        <p class="small text-muted mb-0 pl-2" id="etiq_ayuda_proceso">
                            <strong>Imprime</strong> guarda esta unidad, imprime y pasa a la siguiente sin cerrar (pesos/piezas pueden cambiar).
                            Desde la grilla puede abrir cada etiqueta para modificar o reimprimir.
                        </p>
                    </div>
                    <div class="col-lg-6">
                        <div class="surmar-etiqueta-preview card card-outline card-info mb-0">
                            <div class="card-header py-1"><strong>Vista previa</strong></div>
                            <div class="card-body p-2">
                                <div id="surmar-preview-label" class="surmar-preview-label">
                                    <div class="pv-art">Articulo: <span data-k="codigo_articulo">—</span></div>
                                    <div class="pv-prov">Prov: <span data-k="proveedor">—</span></div>
                                    <div class="pv-desc" data-k="descripcion">—</div>
                                    <div class="pv-pesos">
                                        <div><span class="pv-lbl">PESO BRUTO</span><br><strong data-k="peso_bruto">0.00</strong></div>
                                        <div><span class="pv-lbl">PESO NETO</span><br><strong data-k="peso_neto">0.00</strong></div>
                                    </div>
                                    <div>PIEZAS: <span data-k="cant_pieza">0.00</span>
                                        <span class="ml-3">Prom: <span data-k="peso_promedio">—</span></span>
                                    </div>
                                    <div class="pv-separa" data-k="linea_separa">UN: 1 - Nro.: 1</div>
                                    <div>Fecha : <span data-k="fecha">—</span></div>
                                    <div>F.Vto.: <span data-k="fecha_vto">—</span></div>
                                    <div>Lote Nro.: <span data-k="lote">—</span></div>
                                    <div class="pv-id text-muted">ID: <span data-k="id">—</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancela</button>
                <div>
                    <button type="button" class="btn btn-outline-primary" id="btn-etiq-actualizar-preview">
                        <i class="fa fa-eye"></i> Actualizar preview
                    </button>
                    <button type="button" class="btn btn-success" id="btn-etiq-guardar-imprimir">
                        <i class="fa fa-print"></i> Imprime y siguiente
                    </button>
                    <button type="button" class="btn btn-primary" id="btn-etiq-guardar">
                        <i class="fa fa-save"></i> Guarda
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.surmar-preview-label {
    font-family: "DejaVu Sans", Arial, sans-serif;
    background: #fafafa;
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 12px 14px;
    min-height: 320px;
    font-size: 15px;
    line-height: 1.35;
    color: #17202A;
}
.surmar-preview-label .pv-art { font-size: 18px; font-weight: 700; }
.surmar-preview-label .pv-prov { margin: 6px 0 10px; }
.surmar-preview-label .pv-desc { font-size: 20px; font-weight: 700; margin-bottom: 12px; min-height: 2.4em; }
.surmar-preview-label .pv-pesos { display: flex; justify-content: space-between; margin-bottom: 10px; }
.surmar-preview-label .pv-pesos strong { font-size: 22px; }
.surmar-preview-label .pv-lbl { font-size: 12px; color: #555; }
.surmar-preview-label .pv-separa { font-weight: 600; margin: 8px 0; }
.surmar-preview-label .pv-id { margin-top: 14px; font-size: 12px; }
</style>
