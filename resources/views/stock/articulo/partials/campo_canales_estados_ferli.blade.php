{{-- Ferli: canales + estados fábrica / local --}}
@php
    use App\Support\Stock\ArticuloEstadoCanalSupport;
    $canalesForm = $canalesForm ?? ArticuloEstadoCanalSupport::canalesDisponiblesParaForm();
    $canalIdsSel = old('canal_ids', isset($producto) && $producto->relationLoaded('canales')
        ? $producto->canales->pluck('id')->all()
        : (isset($producto) ? $producto->canales()->pluck('canal.id')->all() : []));
    $canalIdsSel = array_map('intval', (array) $canalIdsSel);
    $estadoFab = old('estado_fabrica', $producto->estado_fabrica ?? $producto->estado ?? 'ACTIVO');
    $estadoLoc = old('estado_local', $producto->estado_local ?? $producto->estado ?? 'ACTIVO');
@endphp
@if (\App\Support\Stock\ArticuloEstadoCanalSupport::uiFerliActiva())
<div class="form-group row">
    <label class="col-lg-4 control-label text-right pr-2">Canales</label>
    <div class="col-lg-8">
        <div class="d-flex flex-wrap align-items-center">
            @foreach ($canalesForm as $canal)
                <div class="custom-control custom-checkbox mr-4 mb-1">
                    <input type="checkbox"
                           class="custom-control-input"
                           id="canal_id_{{ $canal['id'] }}"
                           name="canal_ids[]"
                           value="{{ $canal['id'] }}"
                           @if (in_array((int) $canal['id'], $canalIdsSel, true)) checked @endif>
                    <label class="custom-control-label" for="canal_id_{{ $canal['id'] }}">
                        {{ $canal['nombre'] }}
                        <span class="text-muted small">({{ $canal['codigo'] }})</span>
                    </label>
                </div>
            @endforeach
        </div>
        <small class="form-text text-muted">
            Fabricado y vendido en fábrica y locales: marcar ambos.
            Solo locales (cinturones, etc.): solo Local.
            Solo mayorista fábrica: solo Fábrica.
        </small>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-4 control-label text-right pr-2">Estado fábrica</label>
    <div class="col-lg-2">
        <select name="estado_fabrica" id="estado_fabrica" class="form-control">
            <option value="ACTIVO" @if ($estadoFab === 'ACTIVO') selected @endif>ACTIVO</option>
            <option value="INACTIVO" @if ($estadoFab === 'INACTIVO') selected @endif>INACTIVO</option>
        </select>
    </div>
    <label class="col-lg-2 control-label text-right pr-2">Estado local</label>
    <div class="col-lg-2">
        <select name="estado_local" id="estado_local" class="form-control">
            <option value="ACTIVO" @if ($estadoLoc === 'ACTIVO') selected @endif>ACTIVO</option>
            <option value="INACTIVO" @if ($estadoLoc === 'INACTIVO') selected @endif>INACTIVO</option>
        </select>
    </div>
</div>
@endif
