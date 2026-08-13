@php
    $tieneCliente = isset($data) && $data && ($data->id ?? null);
    $archivoClienteUifRestringido = esSoloVisualizacionClienteUif();
@endphp
<div class="card form5" style="display: none">
    <div class="card-body" id="div-archivos-uif">
        @if ($tieneCliente)
            <p class="text-muted small mb-2">Archivos actuales</p>
            @include('uif.cliente_uif.partials.archivos_adjuntos', ['data' => $data, 'ocultarInputsConservar' => $archivoClienteUifRestringido])
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
                @if (! $tieneCliente)
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
        @include('uif.cliente_uif.template5')
        @unless($archivoClienteUifRestringido)
        <div class="row">
            <div class="col-md-12">
                <button id="agrega_renglon_archivo" type="button" class="pull-right btn btn-danger">+ Agrega renglón</button>
            </div>
        </div>
        @endunless
    </div>
</div>
