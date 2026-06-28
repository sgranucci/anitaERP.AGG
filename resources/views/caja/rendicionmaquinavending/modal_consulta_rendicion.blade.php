<div class="modal fade" id="modal-consulta-rendicion-ventas" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rendiciones vending pendientes de caja</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-row mb-2">
                    <div class="col-md-8">
                        <input type="text" class="form-control form-control-sm" id="consulta_rendicion_ventas_texto" placeholder="ID, Nº cierre, máquina…">
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-primary btn-sm" id="btn-buscar-rendicion-ventas"><i class="fa fa-search"></i> Buscar</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover" id="tabla-consulta-rendicion-ventas">
                        <thead class="thead-light">
                            <tr>
                                <th>ID</th>
                                <th>N&ordm; cierre</th>
                                <th>M&aacute;quina / PV</th>
                                <th>Fecha rend.</th>
                                <th>Jornada</th>
                                <th class="text-right">Cobrado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-consulta-rendicion-ventas">
                            <tr><td colspan="7" class="text-muted text-center">Busque rendiciones pendientes.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
