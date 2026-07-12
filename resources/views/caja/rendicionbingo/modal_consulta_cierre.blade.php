<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultacierreModal" role="dialog" aria-labelledby="consultacierreModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="consultacierreModalLabel">Rendiciones pendientes de presentar en caja</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="consultacierre-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="consultacierre-tab-busqueda" data-toggle="tab"
                           href="#consultacierre-panel-busqueda" role="tab" aria-selected="true">
                            <i class="fa fa-search"></i> Búsqueda
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="consultacierre-tab-pdf" data-toggle="tab"
                           href="#consultacierre-panel-pdf" role="tab" aria-selected="false">
                            <i class="fa fa-file-pdf-o"></i> Comprobante
                        </a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="consultacierre-panel-busqueda" role="tabpanel">
                        <div class="form-group row mb-2">
                            <label for="consultacierre" class="col-form-label col-md-2">Buscar:</label>
                            <div class="col-md-10">
                                <input type="text" name="consultacierre" id="consultacierre" class="form-control"
                                       placeholder="Nº operativo, turno, terminal, fecha…"/>
                            </div>
                        </div>
                        <table class="table table-striped table-bordered table-hover table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Nº op.</th>
                                    <th>Turno</th>
                                    <th>Terminal</th>
                                    <th>Cierre</th>
                                    <th>Jornada</th>
                                    <th class="text-right">Recaudación</th>
                                    <th class="width120">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="datoscierre"></tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="consultacierre-panel-pdf" role="tabpanel">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                            <span id="consultacierre-pdf-titulo" class="small text-muted">Seleccione «Ver PDF» en un cierre.</span>
                            <a href="#" id="consultacierre-pdf-nueva-pestana"
                               class="btn btn-sm btn-outline-secondary d-none" target="_blank" rel="noopener">
                                <i class="fa fa-external-link"></i> Abrir en nueva pestaña
                            </a>
                        </div>
                        <iframe id="consultacierre-pdf-iframe" class="w-100 border rounded bg-light"
                                style="height: min(70vh, 640px);"
                                title="Comprobante de cierre de turno"></iframe>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
