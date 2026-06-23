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
        <p class="mb-2 small text-muted">
          <strong>Consultar</strong> sin criterio lista los NPUs registrados del art&iacute;culo.
          <strong>Un NPU:</strong> solo en Desde.
          <strong>Varios:</strong> en Desde separados por coma (ej. <strong>100,105</strong>).
          <strong>Rango:</strong> Desde y Hasta o atajo <strong>100/110</strong> (solo NPUs existentes en ese rango).
        </p>
        <div class="form-group mb-2">
          <label class="mb-1">N&uacute;mero de parte &uacute;nica (NPU)</label>
          <div class="row">
            <div class="col-md-6">
              <label for="modalEtiquetaNpuArticuloDesde" class="small text-muted mb-1">Desde</label>
              <input type="text" class="form-control" id="modalEtiquetaNpuArticuloDesde" autocomplete="off"
                placeholder="123456, 100/110 o 100,105">
            </div>
            <div class="col-md-6">
              <label for="modalEtiquetaNpuArticuloHasta" class="small text-muted mb-1">Hasta</label>
              <input type="text" class="form-control" id="modalEtiquetaNpuArticuloHasta" autocomplete="off"
                placeholder="Hasta (rango)">
            </div>
          </div>
        </div>
        <div id="modalEtiquetaNpuArticuloListaWrap" class="d-none mb-2">
          <div class="small text-muted mb-1">NPUs registrados para este art&iacute;culo (clic para elegir):</div>
          <div class="table-responsive border rounded" style="max-height: 160px;">
            <table class="table table-sm table-striped table-hover mb-0">
              <thead class="thead-light">
                <tr><th>NPU</th></tr>
              </thead>
              <tbody id="modalEtiquetaNpuArticuloLista"></tbody>
            </table>
          </div>
          <div id="modalEtiquetaNpuArticuloPaginacion" class="d-flex justify-content-center align-items-center mt-1 small"></div>
        </div>
        <div id="modalEtiquetaNpuArticuloError" class="alert alert-danger py-2 d-none" role="alert"></div>
        <div id="modalEtiquetaNpuArticuloAvisoImpresion" class="alert alert-warning py-2 d-none" role="alert"></div>
        <div id="modalEtiquetaNpuArticuloResumen" class="small border rounded p-2 bg-light d-none">
          <div id="modalEtiquetaNpuArticuloCriterioWrap" class="d-none">
            <strong>Criterio:</strong> <span id="modalEtiquetaNpuArticuloCriterio"></span>
            (<span id="modalEtiquetaNpuArticuloCantidad"></span> etiqueta(s))
          </div>
          <div id="modalEtiquetaNpuArticuloDetalleWrap">
            <div><strong>Origen:</strong> <span id="modalEtiquetaNpuArticuloOrigen"></span></div>
            <div><strong>C&oacute;d. proveedor:</strong> <span id="modalEtiquetaNpuArticuloCodigoProveedor"></span></div>
            <div><strong>N&ordm; recepci&oacute;n:</strong> <span id="modalEtiquetaNpuArticuloNumeroRecepcion"></span></div>
            <div id="modalEtiquetaNpuArticuloProveedorWrap"><strong>Proveedor:</strong> <span id="modalEtiquetaNpuArticuloProveedor"></span></div>
          </div>
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
