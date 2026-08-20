@php
    $bannerVal = $validacionAbono ?? null;
    $bannerPolitica = $politicaValidacionAbono ?? [];
    $bannerAplica = (bool) ($bannerPolitica['aplica'] ?? false);
@endphp
@if ($bannerAplica)
    @if ($bannerVal && $bannerVal->estaCompleta())
        <div class="alert alert-success">
            Validación de abono completa
            @if ($bannerVal->usuarios)
                — respondida por {{ $bannerVal->usuarios->nombre }}
            @endif
            @if ($bannerVal->confirmado_at)
                el {{ $bannerVal->confirmado_at->format('d/m/Y H:i') }}
            @endif
            <a href="{{ $urlValidacionAbono }}" class="btn btn-sm btn-outline-success ml-2">Ver validación</a>
        </div>
    @elseif (! empty($urlValidacionAbono))
        <div class="alert alert-warning">
            Esta OC es un contrato de servicio / abono. Hay que completar la
            <strong>Validación de Abono</strong> antes de {{ $accionValidacionAbono ?? 'confirmar' }}.
            <div class="mt-2">
                <a href="{{ $urlValidacionAbono }}" class="btn btn-sm btn-primary">Completar validación</a>
            </div>
        </div>
    @endif
@endif
