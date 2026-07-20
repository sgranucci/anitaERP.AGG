<div class="modal fade" id="modal-vigencias-base" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title">Vigencias de la base <span id="vigencias-base-titulo"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group" id="vigencias-select-wrap" style="display:none">
                    <label for="vigencias_nombrebase_id" class="mb-1">Base (nombre)</label>
                    <select id="vigencias_nombrebase_id" class="form-control">
                        <option value="">Seleccione una base…</option>
                        @foreach ($nombrebases as $nb)
                            <option value="{{ $nb->id }}">{{ $nb->codigo }} - {{ $nb->descripcion }}</option>
                        @endforeach
                    </select>
                </div>

                <div id="vigencias-error" class="alert alert-danger py-2" style="display:none"></div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-2" id="tabla-vigencias">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:34%">Fecha de vigencia</th>
                                <th class="text-right" style="width:34%">Valor</th>
                                <th style="width:14%">Estado</th>
                                <th style="width:18%" data-orderable="false"></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-vigencias">
                            <tr><td colspan="4" class="text-center text-muted">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-light border mb-0 py-2">
                    <small class="text-muted">
                        <i class="fa fa-info-circle"></i>
                        Cada fila es una vigencia con su fecha y valor. Editá las filas, agregá nuevas o marcá
                        las que quieras eliminar, y confirmá todo con <strong>Guardar cambios</strong>.
                        La fila <strong>vigente</strong> es la de fecha más reciente que no sea futura.
                    </small>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-fila-vigencia" style="display:none">
                    <i class="fa fa-plus"></i> Agregar fila
                </button>
                <div class="ml-auto">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-guardar-vigencias" style="display:none">
                        <i class="fa fa-save"></i> Guardar cambios
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
