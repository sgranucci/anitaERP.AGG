@php
    $fechaConfirmacion = optional($fecha_hasta ?? null)->format('d/m/Y');
@endphp
<form method="post" action="{{ route('borrar_ultimo_cierre_periodo_contable') }}" class="d-inline">
    @csrf
    <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
    <button type="submit" class="btn btn-danger {{ $btn_class ?? 'btn-sm' }}"
        onclick="return confirm('¿Elimina el último cierre registrado (hasta {{ $fechaConfirmacion }})? El período volverá al cierre anterior.');">
        <i class="fa fa-trash"></i> Borrar último cierre
    </button>
</form>
