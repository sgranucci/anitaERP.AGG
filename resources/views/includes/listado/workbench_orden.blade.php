{{-- Ordenamiento multi-criterio — UI gráfica (chips + iconos Asc/Desc) --}}
@php
    use App\Support\Listado\ListadoOrdenamientoSupport;
    $camposOrden = $camposOrdenables ?? [];
    $ordenVals = ListadoOrdenamientoSupport::normalizar($orden ?? [], $camposOrden);
    if ($ordenVals === []) {
        $ordenVals[] = ['campo' => '', 'dir' => ListadoOrdenamientoSupport::DIR_ASC];
    }
    $maxOrden = ListadoOrdenamientoSupport::MAX_CRITERIOS;
    $camposOrdenJson = [];
    foreach ($camposOrden as $k => $m) {
        $camposOrdenJson[] = [
            'key' => $k,
            'label' => $etiquetasColumnas[$k] ?? ($m['label'] ?? $k),
            'type' => $m['type'] ?? 'texto',
        ];
    }
    $hayOrdenActivo = collect($ordenVals)->contains(fn ($c) => trim((string) ($c['campo'] ?? '')) !== '');
@endphp
<div class="lw-orden" id="lw-orden-panel"
     data-max="{{ $maxOrden }}"
     data-campos='@json($camposOrdenJson)'>
    <div class="lw-orden-head">
        <div class="lw-orden-head-text">
            <span class="lw-orden-badge-ico" aria-hidden="true"><i class="fa fa-sort-amount-down"></i></span>
            <div>
                <h4>Ordenar filas</h4>
                <div class="lw-hint">Prioridad de arriba a abajo · hasta {{ $maxOrden }} campos</div>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-primary lw-orden-add" id="btn-lw-add-orden" title="Agregar criterio">
            <i class="fa fa-plus"></i><span class="d-none d-sm-inline"> Criterio</span>
        </button>
    </div>

    <div class="lw-orden-empty {{ $hayOrdenActivo ? 'd-none' : '' }}" id="lw-orden-empty">
        <div class="lw-orden-empty-ico"><i class="fa fa-exchange"></i></div>
        <div class="lw-orden-empty-title">Sin orden personalizado</div>
        <div class="lw-orden-empty-hint">Por defecto: ID descendente. Elegí un campo o usá el clic en el encabezado de la grilla.</div>
    </div>

    <div class="lw-orden-stack" id="lw-orden-criterios">
        @foreach ($ordenVals as $i => $c)
            @php
                $campoAct = (string) ($c['campo'] ?? '');
                $dirAct = ($c['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
                $tipoAct = $camposOrden[$campoAct]['type'] ?? 'texto';
            @endphp
            <div class="lw-orden-chip {{ $campoAct === '' ? 'lw-orden-chip--idle' : 'lw-orden-chip--on' }}" data-idx="{{ $i }}">
                <div class="lw-orden-prio" title="Prioridad">{{ $i + 1 }}</div>
                <div class="lw-orden-field">
                    <label class="sr-only">Campo</label>
                    <div class="lw-orden-field-wrap">
                        <i class="fa fa-columns lw-orden-field-ico" aria-hidden="true"></i>
                        <select name="sort[{{ $i }}][campo]" class="form-control form-control-sm lw-orden-campo">
                            <option value="">Elegir campo…</option>
                            @foreach ($camposOrden as $key => $meta)
                                <option value="{{ $key }}" data-type="{{ $meta['type'] ?? 'texto' }}"
                                    @if ($key === $campoAct) selected @endif>
                                    {{ $etiquetasColumnas[$key] ?? $meta['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="lw-orden-dir-group" role="group" aria-label="Dirección">
                    <input type="hidden" name="sort[{{ $i }}][dir]" class="lw-orden-dir" value="{{ $dirAct }}">
                    <button type="button" class="lw-orden-dir-btn lw-orden-dir-asc {{ $dirAct === 'asc' ? 'is-active' : '' }}" data-dir="asc" title="Ascendente A→Z / 1→9">
                        <i class="fa fa-sort-amount-asc"></i>
                        <span class="lw-orden-dir-label">A→Z</span>
                    </button>
                    <button type="button" class="lw-orden-dir-btn lw-orden-dir-desc {{ $dirAct === 'desc' ? 'is-active' : '' }}" data-dir="desc" title="Descendente Z→A / 9→1">
                        <i class="fa fa-sort-amount-desc"></i>
                        <span class="lw-orden-dir-label">Z→A</span>
                    </button>
                </div>
                <div class="lw-orden-dir-pill {{ $dirAct === 'desc' ? 'is-desc' : 'is-asc' }}" aria-hidden="true">
                    <i class="fa {{ $dirAct === 'desc' ? 'fa-long-arrow-down' : 'fa-long-arrow-up' }}"></i>
                    {{ $dirAct === 'desc' ? 'Desc' : 'Asc' }}
                </div>
                <div class="lw-orden-actions">
                    <button type="button" class="lw-orden-ico-btn lw-orden-move-up" title="Subir prioridad">
                        <i class="fa fa-chevron-up"></i>
                    </button>
                    <button type="button" class="lw-orden-ico-btn lw-orden-move-down" title="Bajar prioridad">
                        <i class="fa fa-chevron-down"></i>
                    </button>
                    <button type="button" class="lw-orden-ico-btn lw-orden-ico-btn--danger lw-orden-remove" title="Quitar">
                        <i class="fa fa-trash-o"></i>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>

<template id="lw-orden-row-template">
    <div class="lw-orden-chip lw-orden-chip--idle" data-idx="__i__">
        <div class="lw-orden-prio" title="Prioridad">n</div>
        <div class="lw-orden-field">
            <label class="sr-only">Campo</label>
            <div class="lw-orden-field-wrap">
                <i class="fa fa-columns lw-orden-field-ico" aria-hidden="true"></i>
                <select name="sort[__i__][campo]" class="form-control form-control-sm lw-orden-campo"></select>
            </div>
        </div>
        <div class="lw-orden-dir-group" role="group" aria-label="Dirección">
            <input type="hidden" name="sort[__i__][dir]" class="lw-orden-dir" value="asc">
            <button type="button" class="lw-orden-dir-btn lw-orden-dir-asc is-active" data-dir="asc" title="Ascendente A→Z">
                <i class="fa fa-sort-amount-asc"></i>
                <span class="lw-orden-dir-label">A→Z</span>
            </button>
            <button type="button" class="lw-orden-dir-btn lw-orden-dir-desc" data-dir="desc" title="Descendente Z→A">
                <i class="fa fa-sort-amount-desc"></i>
                <span class="lw-orden-dir-label">Z→A</span>
            </button>
        </div>
        <div class="lw-orden-dir-pill is-asc" aria-hidden="true">
            <i class="fa fa-long-arrow-up"></i>
            Asc
        </div>
        <div class="lw-orden-actions">
            <button type="button" class="lw-orden-ico-btn lw-orden-move-up" title="Subir prioridad">
                <i class="fa fa-chevron-up"></i>
            </button>
            <button type="button" class="lw-orden-ico-btn lw-orden-move-down" title="Bajar prioridad">
                <i class="fa fa-chevron-down"></i>
            </button>
            <button type="button" class="lw-orden-ico-btn lw-orden-ico-btn--danger lw-orden-remove" title="Quitar">
                <i class="fa fa-trash-o"></i>
            </button>
        </div>
    </div>
</template>
