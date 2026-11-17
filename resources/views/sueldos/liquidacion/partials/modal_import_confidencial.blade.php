<div class="modal fade" id="modal-import-confidencial" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Paridad n&oacute;mina confidencial (Anita auxconf/auxconfh)</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">
                    Herramienta de <strong>paridad / migraci&oacute;n</strong>: lee Anita <code>auxconf</code>/<code>auxconfh</code>
                    del N&deg; de esta corrida. El armado operativo de la confidencial es <strong>Calcular</strong> (mismo motor que la n&oacute;mina normal).
                    Primero analiza (dry-run); reci&eacute;n confirma para persistir. Si ya hay recibo del motor, bloquea por conflicto.
                </p>
                <div id="confidencial-resumen" class="mb-2"></div>
                <div class="table-responsive" style="max-height:280px;overflow:auto;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Legajo</th>
                                <th>Acci&oacute;n</th>
                                <th class="text-right">L&iacute;neas</th>
                                <th class="text-right">Neto</th>
                            </tr>
                        </thead>
                        <tbody id="confidencial-detalle"></tbody>
                    </table>
                </div>
                <div id="confidencial-bloqueantes" class="alert alert-danger mt-2 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-warning" id="btn-ejecutar-confidencial" disabled>
                    Confirmar importaci&oacute;n
                </button>
            </div>
        </div>
    </div>
</div>
