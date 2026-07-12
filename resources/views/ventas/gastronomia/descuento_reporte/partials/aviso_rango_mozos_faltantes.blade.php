@php
    $codigosFaltantes = $mozos_rango_codigos_faltantes ?? [];
    $totalFaltantes = count($codigosFaltantes);
@endphp
@if ($totalFaltantes > 0)
    <div class="card border-warning mb-2">
        <div class="card-header py-2 bg-light" id="heading-rango-mozos-faltantes">
            <button class="btn btn-link btn-block text-left text-warning p-0 collapsed"
                    type="button"
                    data-toggle="collapse"
                    data-target="#collapse-rango-mozos-faltantes"
                    aria-expanded="false"
                    aria-controls="collapse-rango-mozos-faltantes">
                <i class="fa fa-chevron-down mr-1"></i>
                {{ $totalFaltantes }} c&oacute;digo(s) del rango sin mozo registrado
                <span class="text-muted font-weight-normal small ml-1">(detalle opcional)</span>
            </button>
        </div>
        <div id="collapse-rango-mozos-faltantes" class="collapse" aria-labelledby="heading-rango-mozos-faltantes">
            <div class="card-body py-2 small text-muted">
                <p class="mb-2">
                    El rango num&eacute;rico incluye c&oacute;digos que no existen en el maestro de mozos.
                    Es habitual en intervalos amplios; el reporte sigue usando solo los mozos registrados del rango.
                </p>
                <p class="mb-0 font-monospace" style="word-break: break-word;">
                    {{ implode(', ', $codigosFaltantes) }}
                </p>
            </div>
        </div>
    </div>
@endif
