<div class="modal fade" id="modal-preview-asiento-cierre-rend" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title" id="modal-preview-asiento-titulo">Preview asiento contable</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="modal-preview-asiento-advertencias" class="alert alert-warning d-none"></div>
                <p id="modal-preview-grupo-info" class="small text-muted d-none mb-2"></p>
                <div class="form-group row mb-2">
                    <label class="col-lg-2 col-form-label">Fecha asiento</label>
                    <div class="col-lg-3">
                        <input type="date" class="form-control" id="modal-preview-fecha-asiento" readonly>
                    </div>
                    <div class="col-lg-7 col-form-label text-muted small">
                        Fecha jornada del grupo (d&iacute;a operativo + punto de venta).
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="tabla-preview-asiento">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Concepto</th>
                                <th class="text-right">Debe</th>
                                <th class="text-right">Haber</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr class="font-weight-bold">
                                <td>Totales</td>
                                <td class="text-right" id="preview-total-debe">0,00</td>
                                <td class="text-right" id="preview-total-haber">0,00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btn-confirmar-cierre-rend">
                    <i class="fa fa-lock"></i> Confirmar cierre contable
                </button>
            </div>
        </div>
    </div>
</div>
