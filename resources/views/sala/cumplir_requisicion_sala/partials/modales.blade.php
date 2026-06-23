<div class="modal fade" id="consultarequisicionsalaCumpleModal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Requisiciones de sala aprobadas</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Solo requisiciones en estado <strong>APROBADA</strong> o <strong>PARCIAL</strong> con &iacute;tems pendientes.</p>
                <div class="form-group row">
                    <label for="consultarequisicionsalaCumple" class="col-sm-2 col-form-label">Buscar</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="consultarequisicionsalaCumple" placeholder="N&uacute;mero, id, comentario&hellip;" autocomplete="off" autofocus>
                    </div>
                </div>
                <table class="table table-striped table-bordered table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Id</th>
                            <th>N&ordm; Req.</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Empresa</th>
                            <th>Dep&oacute;sito</th>
                            <th>Centro costo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="datosrequisicionsalaCumple"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMotivoParcialCumple" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Motivo entrega parcial</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="modal-parcial-articulo"></p>
                <div class="form-group">
                    <label for="modal-parcial-motivo">Motivo</label>
                    <select id="modal-parcial-motivo" class="form-control">
                        <option value="">Seleccione&hellip;</option>
                        @foreach ($estado_parcial_enum ?? [] as $motivo)
                            <option value="{{ $motivo['valor'] }}">{{ $motivo['nombre'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-aceptar-motivo-parcial">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAutorizacionLineaCumple" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Autorizaci&oacute;n de entrega</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="modal-auth-estado">Estado de la l&iacute;nea</label>
                    <select id="modal-auth-estado" class="form-control">
                        @foreach ($estado_linea_enum ?? [] as $est)
                            @if (in_array($est['valor'], ['E', 'R', 'P', 'A'], true))
                                <option value="{{ $est['valor'] }}">{{ $est['nombre'] }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="modal-auth-fecha">Fecha entrega</label>
                    <input type="date" id="modal-auth-fecha" class="form-control" value="{{ date('Y-m-d') }}">
                </div>
                <div class="form-group">
                    <label for="modal-auth-remito">N&ordm; remito</label>
                    <input type="text" id="modal-auth-remito" class="form-control" maxlength="50">
                </div>
                <div class="form-group">
                    <label for="modal-auth-responsable">Responsable</label>
                    <input type="text" id="modal-auth-responsable" class="form-control" maxlength="255">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-aceptar-autorizacion-linea">Aceptar</button>
            </div>
        </div>
    </div>
</div>
