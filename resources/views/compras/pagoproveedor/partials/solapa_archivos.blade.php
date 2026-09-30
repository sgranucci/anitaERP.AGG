@php
    $cantArchivosOp = $cantArchivosOp ?? (isset($data) ? $data->pagoproveedor_archivos->count() : 0);
    $puedeGestionarArchivos = ! empty($puedeActualizar);
@endphp

<div class="form7" style="display: none;">
    <div class="card card-outline card-info mb-3">
        <div class="card-header py-2 d-flex align-items-center justify-content-between">
            <h3 class="card-title mb-0">
                <i class="fa fa-paperclip"></i> Archivos asociados
                @if ($cantArchivosOp > 0)
                    <span class="badge badge-info ml-1">{{ $cantArchivosOp }}</span>
                @endif
            </h3>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Documentos de la orden de pago, incluidos los que se adjuntan al enviarla por correo.
                PDF, imágenes u otros; máx. 10&nbsp;MB por archivo.
                @if ($puedeGestionarArchivos)
                    Los cambios de esta solapa se confirman al actualizar la orden de pago.
                @endif
            </p>
            @include('compras.pagoproveedor.partials.archivos_adjuntos', [
                'data' => $data,
                'ocultarInputsConservar' => ! $puedeGestionarArchivos,
            ])
        </div>
    </div>

    @if ($puedeGestionarArchivos)
        <input type="hidden" name="sincronizar_archivos_op" value="1">
        <div class="card card-outline card-primary mb-0">
            <div class="card-header py-2">
                <h3 class="card-title mb-0"><i class="fa fa-plus-circle"></i> Agregar archivos nuevos</h3>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">
                    Seleccione un archivo por renglón o use <strong>+ Agrega renglón</strong> para adjuntar varios.
                    Los archivos ya cargados aparecen arriba; puede quitarlos con <strong>Quitar</strong> en cada tarjeta.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-2" id="op-archivo-table">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Archivo nuevo</th>
                                <th style="width: 90px;" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="op-tbody-tabla-archivo"></tbody>
                    </table>
                </div>
                @include('compras.pagoproveedor.partials.template_archivos')
                <div class="text-right">
                    <button id="op-agrega-renglon-archivo" type="button" class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-plus"></i> Agrega renglón
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
