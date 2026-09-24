@if (! empty($cp_puede_devolver_compras) && ! empty($url_devolver_compras))
<div class="modal fade" id="modalCpDevolverCompras" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ $url_devolver_compras }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Volver legajo a Compras</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Vuelve el legajo a <strong>COMPRAS</strong>
                        (OC #{{ $data->ordencompras->numeroordencompra ?? $data->ordencompra_id }}).
                        El comentario es obligatorio.
                    </p>
                    <div class="form-group">
                        <label for="cp_dev_com_obs">Comentario / motivo</label>
                        <input type="text" name="observacion" id="cp_dev_com_obs" class="form-control" maxlength="255" required>
                    </div>
                    <div class="form-group">
                        <label for="cp_dev_com_leyenda">Detalle</label>
                        <textarea name="leyenda" id="cp_dev_com_leyenda" class="form-control" rows="3" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Volver legajo a Compras</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
