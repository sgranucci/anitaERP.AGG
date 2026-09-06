@php
    $nombreTipoRe = \App\Models\Configuracion\Arbolaprobacion::$enumTipoArbol[array_search('RE', array_column(\App\Models\Configuracion\Arbolaprobacion::$enumTipoArbol, 'valor'))]['nombre'];
    $esRequisicionesCircuito = (old('tipoarbol', isset($data) ? ($data->tipoarbol ?? '') : '') === $nombreTipoRe);
@endphp
<div id="re-circuito-cuentas-panel" class="anita-arbol-panel" style="{{ $esRequisicionesCircuito ? '' : 'display:none;' }}">
    <div class="anita-arbol-panel-head">
        <div>
            <h2 class="anita-arbol-panel-title">Circuito RE por cuentas</h2>
            <p class="anita-arbol-panel-hint">
                Uso habitual: cargá la <strong>allowlist</strong> del CC (decide Rama A / B sola).
                Los triggers quedan colapsados abajo para casos avanzados.
            </p>
        </div>
        <span class="anita-arbol-chip anita-arbol-chip-ok">Requisiciones</span>
    </div>

    <div class="anita-arbol-callout">
        <strong>Uso habitual.</strong>
        Cargá la allowlist del CC: todas las líneas en la lista → Rama A; alguna fuera → Rama B.
        Los <em>triggers avanzados</em> (abajo, colapsados) son opcionales: solo si necesitás reglas que no alcanza con la lista.
    </div>

    @include('configuracion.arbolaprobacion.partials.re_cuenta_excepcion', [
        'data' => $data ?? null,
        'nested' => true,
    ])

    @include('configuracion.arbolaprobacion.partials.re_triggers', [
        'data' => $data ?? null,
        'nested' => true,
    ])
</div>
