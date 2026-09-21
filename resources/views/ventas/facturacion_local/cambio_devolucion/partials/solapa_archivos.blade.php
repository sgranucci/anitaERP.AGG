@php
    $tieneCambio = isset($data) && $data && ($data->id ?? null);
    $puedeEditarArchivos = ($editable ?? false) && (
        $tieneCambio
            ? can('actualizar-cambio-devolucion-marketplace-facturacion-local', false)
            : can('crear-cambio-devolucion-marketplace-facturacion-local', false)
    );
    $cantArchivos = $tieneCambio ? ($data->archivos?->count() ?? 0) : 0;
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
            Comprobantes de pago, fotos del calzado, labels, etc. Máx. 10&nbsp;MB por archivo.
        </p>
        @if ($tieneCambio)
            @include('ventas.facturacion_local.cambio_devolucion.partials.archivos_adjuntos', [
                'data' => $data,
                'ocultarInputsConservar' => ! $puedeEditarArchivos,
            ])
        @else
            <div class="text-center text-muted py-3 bg-light rounded mb-0">
                Guarde el legajo para adjuntar archivos desde esta solapa.
            </div>
        @endif
    </div>
</div>

@if ($puedeEditarArchivos)
    <div class="card card-outline card-primary mb-0">
        <div class="card-header py-2">
            <h3 class="card-title mb-0"><i class="fa fa-plus-circle"></i> Agregar archivos nuevos</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-2" id="cdm-archivo-table">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Archivo nuevo</th>
                            <th style="width: 90px;" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cdm-tbody-tabla-archivo">
                        <tr class="item-archivo-cdm">
                            <td>
                                <input type="file" name="nombrearchivos[]" class="form-control cdm-nombrearchivos">
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" title="Elimina esta línea" class="btn-accion-tabla cdm-eliminararchivo">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <template id="cdm-template-renglon-archivo">
                <tr class="item-archivo-cdm">
                    <td>
                        <input type="file" name="nombrearchivos[]" class="form-control cdm-nombrearchivos">
                    </td>
                    <td class="text-center align-middle">
                        <button type="button" title="Elimina esta línea" class="btn-accion-tabla cdm-eliminararchivo">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            </template>
            <div class="text-right">
                <button id="cdm-agrega-renglon-archivo" type="button" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-plus"></i> Agrega renglón
                </button>
            </div>
        </div>
    </div>
@endif
