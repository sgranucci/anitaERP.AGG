{{-- Modal elección CBU de pago al proveedor (Anita propago / proveedor_formapago) --}}
<div class="modal fade" id="modal-consulta-cbu-pago" tabindex="-1" role="dialog" aria-labelledby="modal-consulta-cbu-pago-label" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-consulta-cbu-pago-label">Formas de pago — CBU del proveedor</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="modal-cbu-pago-proveedor-nombre"></p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0" id="tabla-cbu-pago">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>F. pago</th>
                                <th>Banco</th>
                                <th>Titular</th>
                                <th>CBU</th>
                                <th style="width:5rem;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-cbu-pago">
                            <tr>
                                <td colspan="5" class="text-center text-muted">Sin datos</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>
