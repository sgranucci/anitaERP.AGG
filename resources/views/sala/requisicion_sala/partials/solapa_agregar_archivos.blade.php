@if (empty($visualizar ?? null))
    @php
        $data = $data ?? null;
        $tieneRequisicion = isset($data) && $data && ($data->id ?? null);
    @endphp
    <div class="card card-outline card-primary mb-4">
        <div class="card-header py-2">
            <h5 class="card-title mb-0">Agregar archivos nuevos</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-2">
                Seleccione un archivo por rengl&oacute;n o use <strong>+ Agrega rengl&oacute;n</strong> para adjuntar varios.
                @if ($tieneRequisicion)
                    Los archivos ya cargados aparecen arriba; puede quitarlos con <strong>Quitar</strong> en cada tarjeta.
                @endif
            </p>
            <table class="table" id="requisicion-sala-archivo-table">
                <thead>
                    <tr>
                        <th>Archivo nuevo</th>
                        <th style="width: 90px;"></th>
                    </tr>
                </thead>
                <tbody id="tbody-tabla-archivo-sala">
                    @if (! $tieneRequisicion)
                        <tr class="item-archivo-sala">
                            <td>
                                <input type="file" name="nombrearchivos[]" class="form-control nombrearchivos-sala">
                            </td>
                            <td>
                                <button type="button" title="Elimina esta l&iacute;nea" class="btn-accion-tabla eliminararchivo-sala tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
            @include('sala.requisicion_sala.partials.template_archivos_requisicion_sala')
            <div class="row">
                <div class="col-md-12">
                    <button id="agrega_renglon_archivo_sala" type="button" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
                </div>
            </div>
        </div>
    </div>
@endif
