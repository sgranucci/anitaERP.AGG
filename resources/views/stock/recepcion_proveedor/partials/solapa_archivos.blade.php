@php
    $tieneRecepcion = isset($recepcion) && $recepcion && ($recepcion->id ?? null);
@endphp

<h5 class="mb-2">
    Archivos asociados
    <i class="fa fa-question-circle text-muted tooltipsC ml-1"
       title="Remitos OCR y otros adjuntos vinculados a esta recepción. Los archivos OCR se generan al procesar el remito; puede agregar adjuntos adicionales."></i>
</h5>

@if ($tieneRecepcion)
    <p class="text-muted small mb-2">Archivos actuales</p>
    @include('stock.recepcion_proveedor.partials.archivos_adjuntos_recepcion', [
        'recepcion' => $recepcion,
        'ocultarInputsConservar' => $soloLectura ?? true,
    ])
@endif

@if (empty($soloLectura))
    <div class="card card-outline card-primary mb-4 mt-3">
        <div class="card-header py-2">
            <h5 class="card-title mb-0">Agregar archivos nuevos</h5>
        </div>
        <div class="card-body">
            @if ($tieneRecepcion)
                <p class="text-muted small mb-2">
                    Seleccione un archivo por renglón o use <strong>+ Agrega renglón</strong> para adjuntar varios.
                    Los archivos ya cargados aparecen arriba; puede quitarlos con <strong>Quitar</strong> en cada tarjeta (no aplica a OCR).
                </p>
            @else
                <p class="text-muted small mb-2">Guarde el borrador para adjuntar archivos desde esta solapa.</p>
            @endif
            @if ($tieneRecepcion)
            <table class="table" id="rp-archivo-table">
                <thead>
                    <tr>
                        <th>Archivo nuevo</th>
                        <th style="width: 90px;"></th>
                    </tr>
                </thead>
                <tbody id="rp-tbody-tabla-archivo"></tbody>
            </table>
            @include('stock.recepcion_proveedor.partials.template_recepcion_archivos')
            <div class="row">
                <div class="col-md-12">
                    <button id="rp-agrega-renglon-archivo" type="button" class="pull-right btn btn-danger">+ Agrega renglón</button>
                </div>
            </div>
            @endif
        </div>
    </div>
@endif
