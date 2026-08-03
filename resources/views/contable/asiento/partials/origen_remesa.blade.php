@php
    use App\Support\Contable\AsientoOrigenProcesoSupport;

    $fks = AsientoOrigenProcesoSupport::fksActivas($data ?? []);
    $chips = [];
    foreach ($fks as $fk => $id) {
        $meta = AsientoOrigenProcesoSupport::FKS[$fk] ?? null;
        if ($meta === null) {
            continue;
        }
        $puede = false;
        foreach ($meta['permiso'] as $slug) {
            if (can($slug, false)) {
                $puede = true;
                break;
            }
        }
        $url = null;
        if ($puede && ! empty($meta['route']) && \Illuminate\Support\Facades\Route::has($meta['route'])) {
            $params = ['id' => $id, 'origen' => 'modal_consulta'];
            if (! in_array($fk, ['caja_movimiento_id', 'cobranza_id'], true)) {
                $params['vista'] = 'consulta';
            }
            $url = route($meta['route'], $params);
        }
        $chips[] = [
            'label' => $meta['label'].' #'.$id,
            'url' => $url,
        ];
    }
@endphp
@if ($chips !== [])
<div class="alert alert-light border mb-3 py-2 px-3" id="asiento-origen-proceso">
    <div class="d-flex flex-wrap align-items-center" style="gap: .5rem 1rem;">
        <span class="text-muted small"><i class="fa fa-link mr-1"></i>Origen del asiento</span>
        @foreach ($chips as $chip)
            @if (! empty($chip['url']))
                <a href="{{ $chip['url'] }}" class="text-primary" target="_blank" rel="noopener">{{ $chip['label'] }}</a>
            @else
                <span>{{ $chip['label'] }}</span>
            @endif
        @endforeach
        <span class="text-muted small ml-auto">
            Revertir/borrar solo desde la operaci&oacute;n del subsistema.
        </span>
    </div>
</div>
@endif
