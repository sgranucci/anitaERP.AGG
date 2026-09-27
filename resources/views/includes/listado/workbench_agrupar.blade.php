{{-- Agrupación multi-nivel — UI gráfica (misma línea que Ordenar) --}}
@php
    use App\Support\Listado\ListadoAgrupacionSupport;
    $camposGrupo = $camposAgrupables ?? [];
    $agruparVals = ListadoAgrupacionSupport::normalizar($agrupar ?? [], $camposGrupo);
    if ($agruparVals === []) {
        $agruparVals = [''];
    }
    $maxGrupos = ListadoAgrupacionSupport::MAX_NIVELES;
    $camposGrupoJson = [];
    foreach ($camposGrupo as $k => $m) {
        $camposGrupoJson[] = [
            'key' => $k,
            'label' => $etiquetasColumnas[$k] ?? ($m['label'] ?? $k),
        ];
    }
    $hayGrupoActivo = collect($agruparVals)->contains(fn ($c) => trim((string) $c) !== '');
@endphp
<div class="lw-group" id="lw-group-panel"
     data-max="{{ $maxGrupos }}"
     data-campos='@json($camposGrupoJson)'>
    <div class="lw-orden-head">
        <div class="lw-orden-head-text">
            <span class="lw-orden-badge-ico lw-group-badge-ico" aria-hidden="true"><i class="fa fa-object-group"></i></span>
            <div>
                <h4>Agrupar filas</h4>
                <div class="lw-hint">Hasta {{ $maxGrupos }} niveles · cabeceras con conteo del universo filtrado (Pack C)</div>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-primary" id="btn-lw-add-group" title="Agregar nivel"
                @if (count(array_filter($agruparVals)) >= $maxGrupos) disabled @endif>
            <i class="fa fa-plus"></i><span class="d-none d-sm-inline"> Nivel</span>
        </button>
    </div>

    <div class="lw-orden-empty {{ $hayGrupoActivo ? 'd-none' : '' }}" id="lw-group-empty">
        <div class="lw-orden-empty-ico"><i class="fa fa-object-group"></i></div>
        <div class="lw-orden-empty-title">Sin agrupación</div>
        <div class="lw-orden-empty-hint">Las filas se listan planas. Elegí un campo para agrupar (ej. Zona → Vendedor).</div>
    </div>

    <div class="lw-orden-stack" id="lw-group-criterios">
        @foreach ($agruparVals as $i => $campoAct)
            <div class="lw-orden-chip lw-group-chip {{ $campoAct === '' ? 'lw-orden-chip--idle' : 'lw-orden-chip--on' }}" data-idx="{{ $i }}">
                <div class="lw-orden-prio" title="Nivel">{{ $i + 1 }}</div>
                <div class="lw-orden-field">
                    <label class="sr-only">Campo</label>
                    <div class="lw-orden-field-wrap">
                        <i class="fa fa-sitemap lw-orden-field-ico" aria-hidden="true"></i>
                        <select name="group[{{ $i }}]" class="form-control form-control-sm lw-orden-campo lw-group-campo">
                            <option value="">Elegir campo…</option>
                            @foreach ($camposGrupo as $key => $meta)
                                <option value="{{ $key }}" @if ($key === $campoAct) selected @endif>
                                    {{ $etiquetasColumnas[$key] ?? $meta['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="lw-group-nivel-tag">
                    {{ $i === 0 ? 'Grupo' : 'Luego' }}
                </div>
                <div class="lw-orden-actions">
                    <button type="button" class="lw-orden-ico-btn lw-group-move-up" title="Subir nivel">
                        <i class="fa fa-chevron-up"></i>
                    </button>
                    <button type="button" class="lw-orden-ico-btn lw-group-move-down" title="Bajar nivel">
                        <i class="fa fa-chevron-down"></i>
                    </button>
                    <button type="button" class="lw-orden-ico-btn lw-orden-ico-btn--danger lw-group-remove" title="Quitar">
                        <i class="fa fa-trash-o"></i>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>

<template id="lw-group-row-template">
    <div class="lw-orden-chip lw-group-chip lw-orden-chip--idle" data-idx="__i__">
        <div class="lw-orden-prio" title="Nivel">n</div>
        <div class="lw-orden-field">
            <label class="sr-only">Campo</label>
            <div class="lw-orden-field-wrap">
                <i class="fa fa-sitemap lw-orden-field-ico" aria-hidden="true"></i>
                <select name="group[__i__]" class="form-control form-control-sm lw-orden-campo lw-group-campo"></select>
            </div>
        </div>
        <div class="lw-group-nivel-tag lw-group-prio-label">Luego</div>
        <div class="lw-orden-actions">
            <button type="button" class="lw-orden-ico-btn lw-group-move-up" title="Subir nivel">
                <i class="fa fa-chevron-up"></i>
            </button>
            <button type="button" class="lw-orden-ico-btn lw-group-move-down" title="Bajar nivel">
                <i class="fa fa-chevron-down"></i>
            </button>
            <button type="button" class="lw-orden-ico-btn lw-orden-ico-btn--danger lw-group-remove" title="Quitar">
                <i class="fa fa-trash-o"></i>
            </button>
        </div>
    </div>
</template>
