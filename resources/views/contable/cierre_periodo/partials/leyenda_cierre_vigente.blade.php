@php
    $fechaCierreVigente = $cierre_vigente ?? null;
    $heredado = ! empty($cierre_heredado);
    $origen = $cierre_vigente_alcance ?? null;
@endphp
@if ($fechaCierreVigente)
    <small class="d-block text-muted">
        Cerrado hasta {{ \Carbon\Carbon::parse($fechaCierreVigente)->format('d/m/Y') }}
        @if ($heredado && $origen)
            <span class="text-info">
                (por {{ \App\Support\Contable\PeriodoContableCierreSupport::etiquetaAlcance($origen) }})
            </span>
        @endif
    </small>
@endif
