@php
    $tieneSolicitud = isset($data) && $data && ($data->id ?? null);
    $cantArchivos = $tieneSolicitud ? (int) (($data->archivos ?? collect())->count()) : 0;
@endphp
@if ($tieneSolicitud)
    <input type="hidden" name="archivos_gestionados" value="1">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
        <p class="text-muted small mb-0">Archivos actuales</p>
        @if ($cantArchivos > 0 && can('listar-solicitud-pago', false))
            <a href="{{ route('unir_archivos_solicitudpago', $data->id) }}"
               class="btn btn-outline-primary btn-sm"
               title="Une PDF e imágenes asociados en un solo PDF para descargar">
                <i class="fa fa-object-group"></i> Unir todos en un PDF
            </a>
        @endif
    </div>
    @include('solicitudpago.solicitudpago.partials.archivos_adjuntos', ['data' => $data])
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
        @if (! $tieneSolicitud)
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
@include('solicitudpago.solicitudpago.partials.template_archivo')
<div class="row">
    <div class="col-md-12">
        <button id="agrega_renglon_archivo" type="button" class="pull-right btn btn-danger">+ Agrega renglón</button>
    </div>
</div>
<small class="form-text text-muted">
    Máximo 10 MB por archivo. Se guardan en el repositorio compartido Anita (<code>/scan/compras/sol_files</code>) y se sincroniza el nombre a Anita si la escritura está activa.
    @if ($cantArchivos > 0)
        <br>Unir todos: combina PDF e imágenes (JPG/PNG/GIF/WEBP) en un único PDF. Otros tipos (Excel, Word, etc.) se omiten.
    @endif
</small>
