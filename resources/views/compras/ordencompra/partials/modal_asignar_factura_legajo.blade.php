@php
    $hoy = now()->format('Y-m-d');
@endphp
<div class="modal fade oc-factura-legajo-modal" id="modalAsignarFacturaLegajo" tabindex="-1" role="dialog" aria-labelledby="modalAsignarFacturaLegajoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content oc-factura-legajo-card">
            <form id="formAsignarFacturaLegajo" method="POST" enctype="multipart/form-data" action="">
                @csrf
                <div class="oc-factura-legajo-hero">
                    <div>
                        <p class="oc-factura-legajo-kicker">Legajo de compras</p>
                        <h5 class="modal-title" id="modalAsignarFacturaLegajoTitulo">Asignar factura PDF</h5>
                        <p class="oc-factura-legajo-sub mb-0">
                            Reemplaza el scan de documentos. El PDF queda en el legajo de la OC
                            <strong class="js-oc-factura-numero">—</strong>
                            <span class="js-oc-factura-proveedor text-muted"></span>
                        </p>
                    </div>
                    <button type="button" class="close oc-factura-legajo-close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body oc-factura-legajo-body">
                    <div class="js-oc-factura-alert d-none" role="alert"></div>
                    <div class="row">
                        <div class="col-md-5">
                            <label class="oc-factura-drop js-oc-factura-drop" for="oc_factura_pdf">
                                <input type="file" name="factura_pdf" id="oc_factura_pdf" accept="application/pdf,.pdf" required>
                                <span class="oc-factura-drop-icon"><i class="fa fa-file-pdf-o"></i></span>
                                <span class="oc-factura-drop-title">Soltar PDF o elegir archivo</span>
                                <span class="oc-factura-drop-name js-oc-factura-nombre">Sin archivo</span>
                            </label>
                        </div>
                        <div class="col-md-7">
                            <div class="form-group mb-3">
                                <label class="oc-factura-label">Tipo ARCA</label>
                                <div class="oc-factura-pills js-oc-tipo-pills" role="group" aria-label="Tipo ARCA">
                                    <button type="button" class="oc-factura-pill is-active" data-tipo="001">001 Factura</button>
                                    <button type="button" class="oc-factura-pill" data-tipo="002">002 ND</button>
                                    <button type="button" class="oc-factura-pill" data-tipo="003">003 NC</button>
                                </div>
                                <input type="text" name="tipo_arca" id="oc_factura_tipo" class="form-control oc-factura-input-tipo mt-2" value="001" maxlength="3" inputmode="numeric" autocomplete="off" required>
                                <small class="form-text text-muted">Código de clase A. La letra B suma 5 (001→006); la C suma 10 (001→011).</small>
                            </div>
                            <div class="form-group mb-3">
                                <label class="oc-factura-label">Letra</label>
                                <div class="oc-factura-pills js-oc-letra-pills" role="group" aria-label="Letra">
                                    @foreach (['A','B','C','M','E'] as $let)
                                        <button type="button" class="oc-factura-pill {{ $let === 'A' ? 'is-active' : '' }}" data-letra="{{ $let }}">{{ $let }}</button>
                                    @endforeach
                                </div>
                                <input type="hidden" name="letra" id="oc_factura_letra" value="A">
                            </div>
                            <div class="oc-factura-arca-chip js-oc-arca-chip">ARCA 001 · Factura A</div>
                            <div class="form-row">
                                <div class="form-group col-5">
                                    <label class="oc-factura-label" for="oc_factura_sucursal">Sucursal</label>
                                    <input type="number" name="sucursal" id="oc_factura_sucursal" class="form-control text-right" min="0" max="99999" required placeholder="00000">
                                </div>
                                <div class="form-group col-7">
                                    <label class="oc-factura-label" for="oc_factura_numero">Número</label>
                                    <input type="number" name="numerocomprobante" id="oc_factura_numero" class="form-control text-right" min="1" required placeholder="00000000">
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label class="oc-factura-label" for="oc_factura_fecha">Fecha de factura</label>
                                <input type="date" name="fechafactura" id="oc_factura_fecha" class="form-control" value="{{ $hoy }}" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer oc-factura-legajo-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn oc-factura-submit js-oc-factura-submit">
                        <i class="fa fa-check"></i> Asignar al legajo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
