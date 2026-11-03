<div class="modal fade" id="sliTipoComprobanteModal" role="dialog" aria-labelledby="sliTipoComprobanteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sliTipoComprobanteModalLabel">Tipo de comprobante</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group row mb-2">
                    <label for="sli-tipo-buscar" class="col-form-label col-sm-2">Buscar</label>
                    <div class="col-sm-10">
                        <input type="text" id="sli-tipo-buscar" class="form-control" autocomplete="off"
                            placeholder="Abreviatura o nombre">
                    </div>
                </div>
                <div class="table-responsive" style="max-height: 60vh; overflow: auto;">
                    <table class="table table-sm table-striped table-bordered table-hover mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="sli-tipo-tbody">
                            @forelse ($tiposComprobante ?? [] as $tipo)
                                <tr class="sli-tipo-fila"
                                    data-codigo="{{ $tipo['abreviatura'] }}"
                                    data-nombre="{{ $tipo['nombre'] }}"
                                    data-busca="{{ mb_strtolower($tipo['abreviatura'].' '.$tipo['nombre']) }}">
                                    <td>{{ $tipo['abreviatura'] }}</td>
                                    <td>{{ $tipo['nombre'] }}</td>
                                    <td>
                                        <button type="button" class="btn btn-warning btn-sm sli-elige-tipo">Elegir</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">No hay tipos de comprobante.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
            </div>
        </div>
    </div>
</div>
