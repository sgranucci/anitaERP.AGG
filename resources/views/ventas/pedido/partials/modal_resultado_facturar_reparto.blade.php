<div class="modal fade" id="resultadoFacturarRepartoModal" role="dialog" aria-labelledby="resultadoFacturarRepartoLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resultadoFacturarRepartoLabel">Facturas emitidas</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="resultado-facturar-reparto-resumen"></p>
                <div id="alert-resultado-facturar-reparto" class="alert alert-warning d-none mb-2" role="alert"></div>
                <div class="table-responsive mb-3" style="max-height: 260px; overflow-y: auto;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Pedido</th>
                                <th>Cliente</th>
                                <th>Factura</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-resultado-facturar-reparto"></tbody>
                    </table>
                </div>
                <div id="opciones-impresion-reparto">
                    <p class="mb-2 font-weight-bold">¿Imprimir estas facturas?</p>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="radio" name="reparto_impresion_modo" id="reparto_imp_ninguna" value="ninguna">
                        <label class="form-check-label" for="reparto_imp_ninguna">No imprimir ahora</label>
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="radio" name="reparto_impresion_modo" id="reparto_imp_completa" value="completa" checked>
                        <label class="form-check-label" for="reparto_imp_completa">
                            Imprimir todas las copias del programa (como al facturar un pedido)
                        </label>
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="radio" name="reparto_impresion_modo" id="reparto_imp_elegir" value="elegir">
                        <label class="form-check-label" for="reparto_imp_elegir">
                            Abrir el programa y elegir qué copias mandar
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" id="cierraResultadoFacturarReparto">Cierra</button>
                <button type="button" class="btn btn-primary" id="aceptaResultadoFacturarReparto">Acepta</button>
            </div>
        </div>
    </div>
</div>
