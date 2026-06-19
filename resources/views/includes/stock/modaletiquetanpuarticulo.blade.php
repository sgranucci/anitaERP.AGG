<div class="modal fade" id="modalEtiquetaNpuArticulo" tabindex="-1" role="dialog" aria-labelledby="modalEtiquetaNpuArticuloTitulo" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="modalEtiquetaNpuArticuloTitulo">Imprimir etiqueta NPU</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body py-2">
        <p class="mb-2 small text-muted" id="modalEtiquetaNpuArticuloSubtitulo"></p>
        <div class="form-group mb-2">
          <label for="modalEtiquetaNpuArticuloValor" class="mb-1">N&uacute;mero de parte &uacute;nica (NPU)</label>
          <input type="number" min="1" step="1" class="form-control" id="modalEtiquetaNpuArticuloValor" autocomplete="off" placeholder="Ej. 123456">
        </div>
        <div id="modalEtiquetaNpuArticuloError" class="alert alert-danger py-2 d-none" role="alert"></div>
        <div id="modalEtiquetaNpuArticuloResumen" class="small border rounded p-2 bg-light d-none">
          <div><strong>Origen:</strong> <span id="modalEtiquetaNpuArticuloOrigen"></span></div>
          <div><strong>C&oacute;d. proveedor:</strong> <span id="modalEtiquetaNpuArticuloCodigoProveedor"></span></div>
          <div><strong>N&ordm; recepci&oacute;n:</strong> <span id="modalEtiquetaNpuArticuloNumeroRecepcion"></span></div>
          <div id="modalEtiquetaNpuArticuloProveedorWrap"><strong>Proveedor:</strong> <span id="modalEtiquetaNpuArticuloProveedor"></span></div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary btn-sm" id="modalEtiquetaNpuArticuloConsultar">
          <i class="fa fa-search"></i> Consultar
        </button>
        <button type="button" class="btn btn-success btn-sm" id="modalEtiquetaNpuArticuloImprimir">
          <i class="fa fa-print"></i> Imprimir
        </button>
      </div>
    </div>
  </div>
</div>
