@php
    $flashValidar = $flash ?? $data ?? null;
    $puedeValidarFlash = $puedeValidarFlash ?? \App\Support\Caja\Flash\FlashCajaValidacionSupport::usuarioPuedeValidar();
    $retornoValidar = $retornoListadoQuery ?? $filtrosQuery ?? [];
    $estaValidado = $flashValidar && $flashValidar->estaValidado();
@endphp
@if ($flashValidar && $puedeValidarFlash)
    @if ($estaValidado)
        <form action="{{ route('quitar_validacion_flash_caja', ['id' => $flashValidar->id] + $retornoValidar) }}"
              method="POST"
              class="d-inline"
              onsubmit="return confirm('¿Quitar la validación de este flash? El tilde verde desaparecerá en Contable.');">
            @csrf
            <button type="submit" class="btn btn-success btn-sm" title="Quitar validación">
                <i class="fa fa-check"></i> Validado
            </button>
        </form>
    @else
        <form action="{{ route('validar_flash_caja', ['id' => $flashValidar->id] + $retornoValidar) }}"
              method="POST"
              class="d-inline"
              onsubmit="return confirm('¿Validar este flash? El tilde verde aparecerá junto a los montos en Contable.');">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm" title="Validar flash">
                <i class="fa fa-check"></i> Validar
            </button>
        </form>
    @endif
@endif
