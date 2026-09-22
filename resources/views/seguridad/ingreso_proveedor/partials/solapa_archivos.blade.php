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
            Los tres primeros son <strong>ART</strong> (con vencimiento), <strong>931 PAGO</strong> y <strong>Seguro de vida obligatorio</strong>.
            Use <strong>+ Agrega rengl&oacute;n</strong> para sumar archivos libres.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-2" id="ingreso-archivo-table">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Documento</th>
                        <th style="width: 90px;" class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="ingreso-tbody-tabla-archivo">
                    @foreach (\App\Support\Seguridad\IngresoProveedorArchivoTipos::slotsPendientes($data->archivos ?? []) as $indice => $slot)
                        @include('seguridad.ingreso_proveedor.partials.renglon_archivo_nuevo', [
                            'slot' => $slot,
                            'indice' => $indice,
                            'claseEliminar' => 'ingreso-eliminararchivo',
                        ])
                    @endforeach
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
