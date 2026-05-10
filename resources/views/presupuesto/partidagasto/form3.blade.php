@php
    $tienePartida = isset($data) && $data && ($data->id ?? null);
@endphp
<div class="card form3" style="display: none">
    <div class="card-body">
        @if ($tienePartida)
            <p class="text-muted small mb-2">Archivos actuales</p>
            @include('presupuesto.partidagasto.partials.archivos_adjuntos', ['data' => $data])
            <p class="text-muted small mb-2 mt-3">Agregar archivos nuevos</p>
        @else
            <p class="text-muted small mb-2">Archivos</p>
        @endif
        <table class="table" id="archivo-table">
            <thead>
                <tr>
                    <th>Archivo nuevo</th>
                    <th style="width: 90px;"></th>
                </tr>
            </thead>
            <tbody id="tbody-tabla-archivo">
                @if (! $tienePartida)
                    <tr class="item-archivo">
                        <td>
                            <input type="file" name="nombrearchivos[]" class="form-control nombrearchivos"
                                onchange="actualizaArchivo(this)">
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
        <template id="template-renglon-archivo">
            <tr class="item-archivo">
                <td>
                    <input type="file" name="nombrearchivos[]" class="form-control nombrearchivos" onchange="actualizaArchivo(this)">
                </td>
                <td>
                    <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminararchivo tooltipsC">
                        <i class="fa fa-times-circle text-danger"></i>
                    </button>
                </td>
            </tr>
        </template>
        <div class="row">
            <div class="col-md-12">
                <button id="agrega_renglon_archivo" type="button" class="pull-right btn btn-danger">+ Agrega renglón</button>
            </div>
        </div>
    </div>
</div>
