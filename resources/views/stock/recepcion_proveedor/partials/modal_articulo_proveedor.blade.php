{{-- Modal validación catálogo articulo_proveedor antes de guardar recepción --}}
<div class="modal fade" id="modalRecepcionArticuloProveedor" tabindex="-1" role="dialog"
    aria-labelledby="modalRecepcionArticuloProveedorTitulo" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title" id="modalRecepcionArticuloProveedorTitulo">
                    Cat&aacute;logo proveedor (articulo_proveedor)
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Revise los datos que se grabar&aacute;n o completar&aacute;n en el cat&aacute;logo del proveedor si los tiene disponibles.
                    Todos los campos son <strong>opcionales</strong>: puede guardar la recepci&oacute;n sin completarlos y actualizar el cat&aacute;logo m&aacute;s adelante desde el art&iacute;culo.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" id="tabla-modal-articulo-proveedor">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>#</th>
                                <th>Art&iacute;culo ERP</th>
                                <th>Acci&oacute;n</th>
                                <th>C&oacute;d. proveedor</th>
                                <th>Descripci&oacute;n proveedor</th>
                                <th>C&oacute;d. barra</th>
                                <th>UM compra</th>
                                <th class="text-right" title="Unidades de compra por 1 unidad de stock ERP">Coef.</th>
                                <th title="Unidad de medida de stock del art&iacute;ulo en el ERP (destino de la conversi&oacute;n)">UM stock</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-modal-articulo-proveedor"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-modal-articulo-proveedor-confirmar">
                    <i class="fa fa-check"></i> Confirmar y guardar
                </button>
            </div>
        </div>
    </div>
</div>
