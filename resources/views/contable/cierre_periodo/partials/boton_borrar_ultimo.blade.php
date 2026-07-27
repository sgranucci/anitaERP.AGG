@php
    $fechaConfirmacion = optional($fecha_hasta ?? null)->format('d/m/Y');
    $alcanceValor = $alcance ?? \App\Support\Contable\PeriodoContableCierreSupport::ALCANCE_GENERAL;
@endphp
<form method="post" action="{{ route('borrar_ultimo_cierre_periodo_contable') }}" class="d-inline">
    @csrf
    <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
    <input type="hidden" name="alcance" value="{{ $alcanceValor }}">
    @if (!empty($mes))
        <input type="hidden" name="mes" value="{{ $mes }}">
    @endif
    @if (!empty($anio))
        <input type="hidden" name="anio" value="{{ $anio }}">
    @endif
    <button type="submit" class="btn btn-danger {{ $btn_class ?? 'btn-sm' }}"
        onclick="return confirm('¿Elimina el último cierre registrado (hasta {{ $fechaConfirmacion }})? El período volverá al cierre anterior de ese módulo.');">
        <i class="fa fa-trash"></i> Borrar último
    </button>
</form>
