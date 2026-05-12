@php
    $data = $data ?? null;
    $tieneOrdencompra = isset($data) && $data && ($data->id ?? null);
@endphp

<h5>Archivos asociados</h5>

@if ($tieneOrdencompra)
    <p class="text-muted small mb-2">Archivos actuales</p>
    @include('compras.ordencompra.partials.archivos_adjuntos_ordencompra', [
        'data' => $data,
        'ocultarInputsConservar' => ! empty($visualizar ?? null),
    ])
@endif

@if (empty($visualizar ?? null))
    <div class="card card-outline card-primary mb-4 mt-3">
        <div class="card-header py-2">
            <h5 class="card-title mb-0">Agregar archivos nuevos</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-2">
                Seleccione un archivo por renglón o use <strong>+ Agrega renglón</strong> para adjuntar varios.
                @if ($tieneOrdencompra)
                    Los archivos ya cargados aparecen arriba; puede quitarlos con <strong>Quitar</strong> en cada tarjeta.
                @endif
            </p>
            <table class="table" id="oc-archivo-table">
                <thead>
                    <tr>
                        <th>Archivo nuevo</th>
                        <th style="width: 90px;"></th>
                    </tr>
                </thead>
                <tbody id="oc-tbody-tabla-archivo">
                    @if (! $tieneOrdencompra)
                        <tr class="item-archivo-oc">
                            <td>
                                <input type="file" name="nombrearchivos[]" class="form-control oc-nombrearchivos">
                            </td>
                            <td>
                                <button type="button" title="Elimina esta línea" class="btn-accion-tabla oc-eliminararchivo tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
            @include('compras.ordencompra.partials.template_oc_archivos')
            <div class="row">
                <div class="col-md-12">
                    <button id="oc-agrega-renglon-archivo" type="button" class="pull-right btn btn-danger">+ Agrega renglón</button>
                </div>
            </div>
        </div>
    </div>
@endif
