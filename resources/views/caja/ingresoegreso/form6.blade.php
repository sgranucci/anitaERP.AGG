@php
    $tieneMovimiento = isset($data) && $data && ($data->id ?? null);
    $puedeEditarArchivos = $puedeEditarArchivos ?? true;
    $cantArchivos = $tieneMovimiento
        ? ($data->caja_movimiento_archivos?->count() ?? 0)
        : 0;
@endphp

<div class="card card-outline card-info form6 mb-0 border-0 shadow-none" style="display: none">
    <div class="card-body">
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
                    Comprobantes, extractos u otros adjuntos del movimiento. PDF, imágenes u otros.
                    Los cambios se confirman al guardar.
                </p>
                @if ($tieneMovimiento)
                    <p class="text-muted small mb-2 font-weight-bold">Archivos actuales</p>
                    @include('caja.ingresoegreso.partials.archivos_adjuntos', [
                        'data' => $data,
                        'ocultarInputsConservar' => ! $puedeEditarArchivos,
                    ])
                @else
                    <div class="text-center text-muted py-3 bg-light rounded mb-0">
                        Puede adjuntar archivos nuevos abajo; se guardan al crear el movimiento.
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
                    <p class="text-muted small mb-2">
                        Seleccione un archivo por renglón o use <strong>+ Agrega renglón</strong> para adjuntar varios.
                        @if ($tieneMovimiento)
                            Los archivos ya cargados aparecen arriba; puede quitarlos con <strong>Quitar</strong> en cada tarjeta.
                        @endif
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-2" id="ie-archivo-table">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Archivo nuevo</th>
                                    <th style="width: 90px;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="ie-tbody-tabla-archivo">
                                <tr class="item-archivo-ie">
                                    <td>
                                        <input type="file" name="nombrearchivos[]" class="form-control ie-nombrearchivos">
                                    </td>
                                    <td class="text-center align-middle">
                                        <button type="button" title="Elimina esta línea" class="btn-accion-tabla ie-eliminararchivo tooltipsC">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @include('caja.ingresoegreso.partials.template_archivos')
                    <div class="text-right">
                        <button id="ie-agrega-renglon-archivo" type="button" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-plus"></i> Agrega renglón
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
