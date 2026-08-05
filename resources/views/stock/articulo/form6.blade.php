@php
    $tieneArticulo = isset($producto) && $producto && ($producto->id ?? null);
    $puedeEditarArchivos = empty($soloConsulta) || !empty($puedeActualizarArticulo);
    if (isset($puedeActualizarArticulo) && empty($puedeActualizarArticulo) && !empty($soloConsulta)) {
        $puedeEditarArchivos = false;
    }
    $archivosList = $tieneArticulo ? ($producto->articulo_archivos ?? collect()) : collect();
    $cantArchivos = $archivosList->count();
@endphp

<div id="tab6" class="card form6 tab-content" style="display: none">
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
                    Documentación del artículo (fichas, planos, certificados, etc.). PDF, imágenes u otros.
                    Los cambios se confirman al guardar el artículo.
                </p>
                @if ($tieneArticulo)
                    <p class="text-muted small mb-2 font-weight-bold">Archivos actuales</p>
                    @include('stock.articulo.partials.archivos_adjuntos', [
                        'producto' => $producto,
                        'ocultarInputsConservar' => ! $puedeEditarArchivos,
                    ])
                @else
                    <div class="text-center text-muted py-3 bg-light rounded mb-0">
                        Guarde el artículo para adjuntar archivos desde esta solapa, o agregue archivos nuevos abajo (se guardan al crear).
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
                        @if ($tieneArticulo)
                            Los archivos ya cargados aparecen arriba; puede quitarlos con <strong>Quitar</strong> en cada tarjeta.
                        @endif
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-2" id="archivo-table">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Archivo nuevo</th>
                                    <th style="width: 90px;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-tabla-archivo">
                                <tr class="item-archivo">
                                    <td>
                                        <input type="file" name="nombrearchivos[]" class="form-control nombrearchivos">
                                    </td>
                                    <td class="text-center align-middle">
                                        <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminararchivo tooltipsC">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @include('stock.articulo.template6')
                    <div class="text-right">
                        <button id="agrega_renglon_archivo" type="button" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-plus"></i> Agrega renglón
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
