<div class="modal fade" id="modalEtiquetaCantidadArticulo" tabindex="-1" role="dialog" aria-labelledby="modalEtiquetaCantidadArticuloTitulo" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="modalEtiquetaCantidadArticuloTitulo">Imprimir etiqueta</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body py-2">
        <p class="mb-2 small text-muted" id="modalEtiquetaCantidadArticuloSubtitulo"></p>
        <p class="mb-2 small text-muted">
          Indique cu&aacute;ntas copias de la etiqueta desea imprimir (mismo dise&ntilde;o, sin NPU).
        </p>
        <div class="form-group mb-2">
          <label for="modalEtiquetaCantidadArticuloCantidad" class="mb-1">Cantidad de etiquetas</label>
          <input type="number" class="form-control" id="modalEtiquetaCantidadArticuloCantidad"
            min="1" max="{{ \App\Support\Stock\ArticuloEtiquetaNpuRangoSupport::MAX_ETIQUETAS }}" step="1" value="1"
            autocomplete="off" placeholder="1">
          <small class="form-text text-muted">
            M&aacute;ximo {{ \App\Support\Stock\ArticuloEtiquetaNpuRangoSupport::MAX_ETIQUETAS }} por impresi&oacute;n.
          </small>
        </div>
        <div id="modalEtiquetaCantidadArticuloError" class="alert alert-danger py-2 d-none" role="alert"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success btn-sm" id="modalEtiquetaCantidadArticuloImprimir">
          <i class="fa fa-print"></i> Imprimir
        </button>
      </div>
    </div>
  </div>
</div>
