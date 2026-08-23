<div class="modal fade" id="ingresoRechazoModal" tabindex="-1" role="dialog" aria-labelledby="ingresoRechazoTitulo" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="ingreso-rechazo-form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="ingresoRechazoTitulo">Rechazar ticket</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">El motivo es obligatorio. Quien cargó el ticket recibe el aviso.</p>
                    <label for="ingreso-motivo-rechazo" class="control-label requerido">Motivo del rechazo</label>
                    <textarea name="motivo_rechazo" id="ingreso-motivo-rechazo" class="form-control" rows="3" required
                              placeholder="Indique por qué no se autoriza el ingreso"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Rechazar ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>
