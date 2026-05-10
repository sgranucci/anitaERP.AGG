{{-- Alta/edición: mismos controles que compras/proveedor form5 (tabla por renglón + plantilla). Solo lectura: no se muestra. --}}
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
                Seleccione un archivo por renglón o use <strong>+ Agrega renglón</strong> para adjuntar varios.
                Los archivos ya cargados aparecen arriba; puede quitarlos con <strong>Quitar</strong> en cada tarjeta.
            </p>
            <table class="table" id="archivo-table">
                <thead>
                    <tr>
                        <th>Archivo nuevo</th>
                        <th style="width: 90px;"></th>
                    </tr>
                </thead>
                <tbody id="tbody-tabla-archivo">
                    @if (! $tieneRequisicion)
                        <tr class="item-archivo">
                            <td>
                                <input type="file" name="nombrearchivos[]" class="form-control nombrearchivos">
                            </td>
                            <td>
                                <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminararchivo tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
            @include('compras.requisicion.partials.template_archivos_requisicion')
            <div class="row">
                <div class="col-md-12">
                    <button id="agrega_renglon_archivo" type="button" class="pull-right btn btn-danger">+ Agrega renglón</button>
                </div>
            </div>
        </div>
    </div>
@endif
