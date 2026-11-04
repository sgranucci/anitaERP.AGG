<div class="modal fade" id="modal-revertir-mesa" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" id="form-revertir-mesa" action="" autocomplete="off" data-enter-campos="1">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Revertir <span id="revertir-mesa-numero"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Se graba otro movimiento, con el número siguiente y las cantidades al revés. El original queda.</p>
                    <div class="form-group">
                        <label class="control-label text-right pr-2 d-block" for="revertir-mesa-motivo">Motivo</label>
                        <textarea name="motivo" id="revertir-mesa-motivo" class="form-control" rows="2" maxlength="500" required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="control-label text-right pr-2 d-block" for="revertir-mesa-usuario">Usuario</label>
                        <input type="text" name="firma_usuario" id="revertir-mesa-usuario" class="form-control" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label class="control-label text-right pr-2 d-block" for="revertir-mesa-clave">Clave</label>
                        <input type="password" name="firma_password" id="revertir-mesa-clave" class="form-control" autocomplete="new-password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa fa-undo"></i> Revertir</button>
                </div>
            </div>
        </form>
    </div>
</div>
