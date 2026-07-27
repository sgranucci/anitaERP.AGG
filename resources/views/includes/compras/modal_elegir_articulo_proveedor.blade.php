{{-- Modal elegir proveedor/código del catálogo articulo_proveedor (RQ / OC) --}}
<div class="modal fade" id="modalElegirArticuloProveedor" tabindex="-1" role="dialog"
    aria-labelledby="modalElegirArticuloProveedorTitulo" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color:#85C1E9;color:#17202A;">
                <h5 class="modal-title" id="modalElegirArticuloProveedorTitulo">
                    Elegir proveedor de compra
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="modalElegirArticuloProveedorSubtitulo">
                    Este art&iacute;culo tiene datos en el cat&aacute;logo <strong>articulo_proveedor</strong>.
                    Seleccione el proveedor (y c&oacute;digo) con el que se comprar&aacute;.
                    Si el art&iacute;culo no tuviera cat&aacute;logo, no se mostrar&iacute;a este modal.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered mb-0" id="tabla-elegir-articulo-proveedor">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:8%;"></th>
                                <th>Proveedor</th>
                                <th>C&oacute;d. art. prov.</th>
                                <th>Nombre en proveedor</th>
                                <th>UM compra</th>
                                <th class="text-right" title="Cantidad stock = cantidad compra &times; coef">Coef.</th>
                                <th class="text-right">Precio</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-elegir-articulo-proveedor"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" id="btn-elegir-ap-cancelar">
                    Sin proveedor / Cancelar
                </button>
            </div>
        </div>
    </div>
</div>
