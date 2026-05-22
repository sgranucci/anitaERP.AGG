@if ($suitecrmPuedeEditar ?? false)
<div class="modal fade" id="suitecrmNotaModal" tabindex="-1" role="dialog" aria-labelledby="suitecrmNotaModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="suitecrmNotaModalLabel">Nota SuiteCRM</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body py-2">
                <input type="hidden" id="suitecrm-nota-modal-id" value="">
                <div class="form-group mb-2">
                    <label for="suitecrm-nota-modal-name">Asunto</label>
                    <input type="text" id="suitecrm-nota-modal-name" class="form-control form-control-sm" maxlength="255">
                </div>
                <div class="form-group mb-0">
                    <label for="suitecrm-nota-modal-description">Descripción</label>
                    <textarea id="suitecrm-nota-modal-description" class="form-control form-control-sm" rows="6"></textarea>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" id="suitecrm-nota-modal-guardar" class="btn btn-success btn-sm">
                    <i class="fa fa-save"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>
@endif
