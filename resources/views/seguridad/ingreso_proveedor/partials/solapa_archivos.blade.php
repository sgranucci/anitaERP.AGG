@php
    $tieneTicket = isset($data) && $data && ($data->id ?? null);
    $cantArchivos = $tieneTicket ? ($data->archivos?->count() ?? 0) : 0;
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
        @if ($tieneTicket)
            @include('seguridad.ingreso_proveedor.partials.archivos_adjuntos', [
                'data' => $data,
                'ocultarInputsConservar' => false,
            ])
        @else
            <div class="text-center text-muted py-3 bg-light rounded mb-0">
                Guarde el ticket para adjuntar archivos desde esta solapa, o agregue renglones abajo al crear.
            </div>
        @endif
    </div>
</div>

<div class="card card-outline card-primary mb-0">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-plus-circle"></i> Agregar archivos nuevos</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-2">
            Un archivo por rengl&oacute;n. Use <strong>+ Agrega rengl&oacute;n</strong> para adjuntar varios.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-2" id="ingreso-archivo-table">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Archivo nuevo</th>
                        <th style="width: 90px;" class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="ingreso-tbody-tabla-archivo">
                    <tr class="item-archivo-ingreso">
                        <td>
                            <input type="file" name="nombrearchivos[]" class="form-control ingreso-nombrearchivos">
                        </td>
                        <td class="text-center align-middle">
                            <button type="button" title="Elimina esta l&iacute;nea" class="btn-accion-tabla ingreso-eliminararchivo tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        @include('seguridad.ingreso_proveedor.partials.template_archivos')
        <div class="text-right">
            <button id="ingreso-agrega-renglon-archivo" type="button" class="btn btn-outline-primary btn-sm">
                <i class="fa fa-plus"></i> Agrega rengl&oacute;n
            </button>
        </div>
    </div>
</div>
