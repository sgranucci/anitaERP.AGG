{{-- Ferli: emisión de etiquetas con combinación / talle / modelo / cantidad --}}
@once('anita-modal-etiqueta-ferli')
<style>
#modalEtiquetaFerli .modal-content {
    border: 0;
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 1.25rem 3rem rgba(15, 23, 42, .28);
}
#modalEtiquetaFerli .eti-ferli-hero {
    background: linear-gradient(135deg, #0f766e 0%, #155e75 48%, #1e3a5f 100%);
    color: #f8fafc;
    padding: 1.1rem 1.25rem;
}
#modalEtiquetaFerli .eti-ferli-hero .close { color: #fff; opacity: .85; text-shadow: none; }
#modalEtiquetaFerli .eti-ferli-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(255,255,255,.22);
    border-radius: 999px;
    padding: .2rem .7rem;
    font-size: .78rem;
}
#modalEtiquetaFerli label { font-weight: 600; color: #0f172a; font-size: .85rem; }
#modalEtiquetaFerli .form-control {
    border-radius: .65rem;
    border-color: #cbd5e1;
}
#modalEtiquetaFerli .btn-imprimir-ferli {
    background: linear-gradient(135deg, #0d9488, #0369a1);
    border: 0;
    border-radius: .65rem;
    font-weight: 600;
}
</style>
<div class="modal fade" id="modalEtiquetaFerli" tabindex="-1" role="dialog" aria-labelledby="modalEtiquetaFerliTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="eti-ferli-hero d-flex justify-content-between align-items-start">
                <div>
                    <h5 class="mb-1" id="modalEtiquetaFerliTitulo">
                        <i class="fa fa-tags mr-1"></i> Emitir etiquetas
                    </h5>
                    <div class="eti-ferli-chip mt-1" id="modalEtiquetaFerliSubtitulo">—</div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body px-3 py-3">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="modalEtiquetaFerliModelo">Modelo de etiqueta</label>
                            <select id="modalEtiquetaFerliModelo" class="form-control"></select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="modalEtiquetaFerliCantidad">Cantidad</label>
                            <input type="number" class="form-control" id="modalEtiquetaFerliCantidad"
                                   min="1" step="1" value="1" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="modalEtiquetaFerliCombinacion">Combinación</label>
                            <select id="modalEtiquetaFerliCombinacion" class="form-control"></select>
                            <small class="text-muted">Solo combinaciones activas.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="modalEtiquetaFerliTalle">Talle</label>
                            <select id="modalEtiquetaFerliTalle" class="form-control"></select>
                        </div>
                    </div>
                </div>
                <div id="modalEtiquetaFerliError" class="alert alert-danger py-2 d-none" role="alert"></div>
                <div id="modalEtiquetaFerliLoading" class="text-muted small d-none">
                    <i class="fa fa-spinner fa-spin"></i> Cargando datos…
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm btn-imprimir-ferli" id="modalEtiquetaFerliImprimir">
                    <i class="fa fa-print"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
</div>
@endonce
