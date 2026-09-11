@php
    $tieneLista = isset($data) && $data && ($data->id ?? null);
    $visualizar = ! empty($visualizar);
    $puedeEditarArchivos = ! $visualizar;
    $cantArchivos = $tieneLista ? ($data->listaprecio_proveedor_archivos?->count() ?? 0) : 0;
@endphp

<div class="card card-outline card-info mb-3">
    <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0">
            <i class="fa fa-paperclip"></i> Archivos asociados
            @if ($cantArchivos > 0)
                <span class="badge badge-info ml-1">{{ $cantArchivos }}</span>
            @endif
        </h3>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            PDF, im&aacute;genes u otros adjuntos de la lista. Los cambios se confirman al guardar.
        </p>
        @if ($tieneLista)
            <p class="text-muted small mb-2 font-weight-bold">Archivos actuales</p>
            @include('compras.listaprecio_proveedor.partials.archivos_adjuntos', [
                'data' => $data,
                'ocultarInputsConservar' => ! $puedeEditarArchivos,
            ])
        @elseif (! $puedeEditarArchivos)
            <div class="text-center text-muted py-3 bg-light rounded mb-0">
                No hay archivos adjuntos.
            </div>
        @else
            <p class="text-muted small mb-0">
                Los archivos que agregue abajo se asociar&aacute;n al guardar la lista.
            </p>
        @endif
    </div>
</div>

@if ($puedeEditarArchivos)
    <div class="card card-outline card-primary mb-0">
        <div class="card-header py-2">
            <h3 class="card-title mb-0"><i class="fa fa-plus-circle"></i> Agregar archivos nuevos</h3>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-2">
                Seleccione un archivo por rengl&oacute;n o use <strong>+ Agrega rengl&oacute;n</strong> para adjuntar varios.
                @if ($tieneLista)
                    Los archivos ya cargados aparecen arriba; puede quitarlos con <strong>Quitar</strong> en cada tarjeta.
                @endif
            </p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-2" id="lp-archivo-table">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Archivo nuevo</th>
                            <th style="width: 90px;" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="lp-tbody-tabla-archivo">
                        <tr class="item-archivo-lp">
                            <td>
                                <input type="file" name="nombrearchivos[]" class="form-control lp-nombrearchivos">
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" title="Elimina esta l&iacute;nea" class="btn-accion-tabla lp-eliminararchivo tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @include('compras.listaprecio_proveedor.template_archivos')
            <div class="text-right">
                <button id="lp-agrega-renglon-archivo" type="button" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-plus"></i> Agrega rengl&oacute;n
                </button>
            </div>
        </div>
    </div>
@endif
