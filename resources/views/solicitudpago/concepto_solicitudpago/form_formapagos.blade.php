<p class="text-muted mb-3">Formas de pago habilitadas para este concepto (tabla Anita formapagosol).</p>
@php
    $seleccionadas = collect(old(
        'formapagosol_ids',
        isset($data) ? $data->formapagos->pluck('formapagosol_id')->all() : []
    ))->map(fn ($id) => (int) $id);
@endphp
<div class="row">
    @forelse ($formapagosol_query as $fp)
        <div class="col-md-4 col-lg-3 mb-2">
            <div class="custom-control custom-checkbox border rounded px-3 py-2 h-100">
                <input type="checkbox" class="custom-control-input" id="fp_{{ $fp->id }}"
                       name="formapagosol_ids[]" value="{{ $fp->id }}"
                       {{ $seleccionadas->contains((int) $fp->id) ? 'checked' : '' }}>
                <label class="custom-control-label" for="fp_{{ $fp->id }}">
                    <strong>{{ $fp->codigo }}</strong> — {{ $fp->nombre }}
                </label>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="alert alert-warning mb-0">No hay formas de pago cargadas. Abr&iacute; primero el ABM de Formas de pago para sincronizarlas.</div>
        </div>
    @endforelse
</div>
