@php
    $reExcRows = collect();
    if (old('re_exc_ids')) {
        $reExcRows = collect(old('re_exc_ids'))->map(function ($id, $i) {
            return (object) [
                'id' => $id,
                'centrocosto_id' => old('re_exc_centrocosto_ids.'.$i),
                'empresa_id' => old('re_exc_empresa_ids.'.$i),
                'cuentacontable_id' => old('re_exc_cuentacontable_ids.'.$i),
                'activo' => old('re_exc_activos.'.$i, 'S'),
                'cuentacontables' => (object) [
                    'codigo' => old('re_exc_cuenta_codigos.'.$i, ''),
                    'nombre' => old('re_exc_cuenta_nombres.'.$i, ''),
                ],
            ];
        });
    } elseif (isset($data) && ($data->cuenta_excepciones ?? null)) {
        $reExcRows = $data->cuenta_excepciones;
    }
    $nombreTipoRe = \App\Models\Configuracion\Arbolaprobacion::$enumTipoArbol[array_search('RE', array_column(\App\Models\Configuracion\Arbolaprobacion::$enumTipoArbol, 'valor'))]['nombre'];
    $esRequisicionesPanel = (old('tipoarbol', isset($data) ? ($data->tipoarbol ?? '') : '') === $nombreTipoRe);
    $empresaArbolId = (int) old('empresa_id', $data->empresa_id ?? session('empresa_id') ?? 0);
    $nested = ! empty($nested);
@endphp
@if($nested)
<div id="re-cuenta-excepcion-panel" class="anita-arbol-subsection">
@else
<div id="re-cuenta-excepcion-panel" class="anita-arbol-panel" style="{{ $esRequisicionesPanel ? '' : 'display:none;' }}">
@endif
    <div class="anita-arbol-subsection-head">
        <div>
            <h3 class="anita-arbol-subsection-title">1. Allowlist (cuentas → Rama A)</h3>
            <p class="anita-arbol-panel-hint mb-0">
                Cuentas “habituales” del CC. Si <em>todas</em> las líneas de la RE están en esta lista → Rama A.
                Si <em>alguna</em> no está (o no tiene cuenta) → Rama B.
            </p>
        </div>
        <span class="anita-arbol-chip anita-arbol-chip-teal">Dato compartido</span>
    </div>
    <div class="anita-arbol-toolbar mb-2" style="margin-top:0;">
        <span class="text-muted small">Lupa / F1 / código+Enter — mismo modal de cuentas del sistema. Esta lista la usan los triggers de abajo.</span>
        <button type="button" class="btn btn-sm anita-arbol-btn-teal" id="agrega_re_exc">
            <i class="fa fa-plus"></i> Agregar cuenta
        </button>
    </div>
    <div class="anita-arbol-table-wrap">
        <table class="table table-sm mb-0" id="tabla-re-exc">
            <thead>
                <tr>
                    <th style="width:20%;">Centro de costo</th>
                    <th style="width:16%;">Empresa</th>
                    <th style="width:48%;" colspan="2">Cuenta contable</th>
                    <th style="width:8%;">Activo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-re-exc">
                @foreach($reExcRows as $exc)
                    @include('configuracion.arbolaprobacion.partials.re_cuenta_excepcion_fila', [
                        'exc' => $exc,
                        'empresa_default_id' => $empresaArbolId,
                    ])
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<script type="text/template" id="template-re-exc-fila">
@include('configuracion.arbolaprobacion.partials.re_cuenta_excepcion_fila', [
    'exc' => null,
    'empresa_default_id' => $empresaArbolId,
])
</script>
