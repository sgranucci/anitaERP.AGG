{{-- Modal autorización en puerta: datos + archivos + Autorizar / Autorizar e ingresar / Rechazar --}}
<div class="modal fade" id="porteriaAutorizacionModal" tabindex="-1" role="dialog"
     aria-labelledby="porteriaAutorizacionTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="porteriaAutorizacionTitulo">
                    Revisar ingreso pendiente
                    <span class="badge badge-dark ml-1" id="porteria-auth-ticket-id">#—</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="porteria-auth-vista-datos">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small text-uppercase">Persona</p>
                            <p class="h5 mb-0" id="porteria-auth-nombre">—</p>
                            <p class="mb-0">DNI <strong id="porteria-auth-doc">—</strong></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small text-uppercase">Empresa</p>
                            <p class="h5 mb-0" id="porteria-auth-empresa">—</p>
                            <p class="mb-0" id="porteria-auth-proveedor">—</p>
                        </div>
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered mb-0">
                            <tbody>
                                <tr>
                                    <th style="width:28%;background:#85C1E9;color:#17202A;">Solicitó</th>
                                    <td id="porteria-auth-solicitante">—</td>
                                </tr>
                                <tr>
                                    <th style="background:#85C1E9;color:#17202A;">Fecha prevista</th>
                                    <td id="porteria-auth-fecha">—</td>
                                </tr>
                                <tr>
                                    <th style="background:#85C1E9;color:#17202A;">Motivo</th>
                                    <td id="porteria-auth-motivo">—</td>
                                </tr>
                                <tr>
                                    <th style="background:#85C1E9;color:#17202A;">Punto</th>
                                    <td id="porteria-auth-punto">—</td>
                                </tr>
                                <tr>
                                    <th style="background:#85C1E9;color:#17202A;">Sector</th>
                                    <td id="porteria-auth-sector">—</td>
                                </tr>
                                <tr>
                                    <th style="background:#85C1E9;color:#17202A;">Área</th>
                                    <td id="porteria-auth-area">—</td>
                                </tr>
                                <tr>
                                    <th style="background:#85C1E9;color:#17202A;">Patente</th>
                                    <td id="porteria-auth-patente">—</td>
                                </tr>
                                <tr>
                                    <th style="background:#85C1E9;color:#17202A;">Título</th>
                                    <td id="porteria-auth-titulo">—</td>
                                </tr>
                                <tr>
                                    <th style="background:#85C1E9;color:#17202A;">Comentario</th>
                                    <td id="porteria-auth-comentario">—</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <h6 class="font-weight-bold">Archivos (ART / seguro / etc.)</h6>
                    <div id="porteria-auth-archivos" class="row"></div>
                    <p id="porteria-auth-sin-archivos" class="text-muted text-center py-3 bg-light rounded d-none mb-0">
                        No hay archivos adjuntos.
                    </p>
                </div>
                <div id="porteria-auth-vista-rechazo" class="d-none">
                    <p class="text-muted">El motivo es obligatorio. Quien cargó el ticket recibe el aviso.</p>
                    <label for="porteria-auth-motivo-rechazo" class="control-label requerido">Motivo del rechazo</label>
                    <textarea id="porteria-auth-motivo-rechazo" class="form-control" rows="3"
                              placeholder="Indique por qué no se autoriza el ingreso"></textarea>
                </div>
                <div id="porteria-auth-error" class="alert alert-danger mt-3 mb-0 d-none" role="alert"></div>
            </div>
            <div class="modal-footer flex-wrap" id="porteria-auth-footer-acciones">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-danger" id="porteria-auth-btn-rechazar">
                    <i class="fa fa-times"></i> Rechazar
                </button>
                <button type="button" class="btn btn-secondary" id="porteria-auth-btn-autorizar">
                    <i class="fa fa-check"></i> Autorizar
                </button>
                <button type="button" class="btn btn-success btn-lg" id="porteria-auth-btn-autorizar-ingresar">
                    <i class="fa fa-sign-in-alt"></i> Autorizar e ingresar
                </button>
            </div>
            <div class="modal-footer d-none" id="porteria-auth-footer-rechazo">
                <button type="button" class="btn btn-outline-secondary" id="porteria-auth-btn-volver">Volver</button>
                <button type="button" class="btn btn-danger" id="porteria-auth-btn-confirmar-rechazo">
                    Confirmar rechazo
                </button>
            </div>
        </div>
    </div>
</div>
