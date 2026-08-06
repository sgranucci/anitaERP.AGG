{{-- Alerta de posibles artículos duplicados por descripción (alta / edición). --}}
<div class="modal fade" id="articuloSimilaresDescripcionModal" tabindex="-1" role="dialog"
    aria-labelledby="articuloSimilaresDescripcionModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="articuloSimilaresDescripcionModalLabel">
                    <i class="fa fa-exclamation-triangle"></i>
                    Posibles art&iacute;culos duplicados
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    La descripci&oacute;n ingresada se parece a art&iacute;culos ya existentes.
                    Revise la lista antes de crear un c&oacute;digo nuevo.
                </p>
                <p class="small text-muted mb-3">
                    Buscado:
                    <strong id="articulo-similares-descripcion-buscada"></strong>
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0" id="tabla-articulo-similares-descripcion">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width: 4rem;">ID</th>
                                <th style="width: 8rem;">SKU</th>
                                <th>Descripci&oacute;n</th>
                                <th style="width: 6rem;">Estado</th>
                                <th style="width: 6rem;" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-articulo-similares-descripcion">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    Revisar descripci&oacute;n
                </button>
                <button type="button" class="btn btn-warning" id="btn-continuar-apesar-similares">
                    Continuar con esta descripci&oacute;n
                </button>
            </div>
        </div>
    </div>
</div>
