@php
    $flashValidar = $flash ?? $data ?? null;
    $puedeValidarFlash = $puedeValidarFlash ?? \App\Support\Caja\Flash\FlashCajaValidacionSupport::usuarioPuedeValidar();
    $retornoValidar = $retornoListadoQuery ?? $filtrosQuery ?? [];
    $mostrarEtiqueta = ! empty($mostrarEtiqueta);
@endphp
@if ($flashValidar && $puedeValidarFlash)
    @if ($flashValidar->estaValidado())
        <form action="{{ route('quitar_validacion_flash_caja', ['id' => $flashValidar->id] + $retornoValidar) }}"
              method="POST"
              class="d-inline"
              onsubmit="return confirm('¿Quitar la validación de este flash? El tilde verde desaparecerá en Contable.');">
            @csrf
            <button type="submit"
                    class="{{ $mostrarEtiqueta ? 'btn btn-outline-success btn-sm' : 'btn-accion-tabla tooltipsC text-success' }}"
                    title="Quitar validación">
                <i class="fa fa-check-circle"></i>
                @if ($mostrarEtiqueta)
                    Quitar validación
                @endif
            </button>
        </form>
    @else
        <form action="{{ route('validar_flash_caja', ['id' => $flashValidar->id] + $retornoValidar) }}"
              method="POST"
              class="d-inline"
              onsubmit="return confirm('¿Validar este flash? El tilde verde aparecerá junto a los montos en Contable.');">
            @csrf
            <button type="submit"
                    class="{{ $mostrarEtiqueta ? 'btn btn-outline-secondary btn-sm' : 'btn-accion-tabla tooltipsC text-muted' }}"
                    title="Validar flash">
                <i class="fa fa-check-circle-o"></i>
                @if ($mostrarEtiqueta)
                    Validar
                @endif
            </button>
        </form>
    @endif
@endif
