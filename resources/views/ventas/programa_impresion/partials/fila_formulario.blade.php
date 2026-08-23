@php
    $formObj = is_array($form ?? null) ? (object) $form : ($form ?? null);
    $tipoForm = old("formularios.$fi.formulario", is_object($formObj) ? ($formObj->formulario ?? 'FACTURA') : 'FACTURA');
    $tituloForm = $formulariosEnum[$tipoForm] ?? $tipoForm;
    $copias = is_array($form ?? null)
        ? ($form['copias'] ?? [])
        : (is_object($formObj) ? ($formObj->copias ?? collect()) : []);
    if ($form === null) {
        $copias = [];
    } elseif (empty($copias) || (is_countable($copias) && count($copias) === 0)) {
        $copias = [null];
    }
    $ordenFormulario = is_object($formObj)
        ? ($formObj->orden ?? (is_numeric($fi) ? ((int) $fi + 1) * 10 : 10))
        : (is_numeric($fi) ? ((int) $fi + 1) * 10 : 10);
    $codigosEnUso = [];
    foreach ($copias as $c) {
        $cObj = is_array($c) ? (object) $c : $c;
        if (is_object($cObj) && ! empty($cObj->codigo)) {
            $codigosEnUso[] = strtoupper((string) $cObj->codigo);
        }
    }
@endphp
<div class="programa-formulario-bloque" data-fi="{{ $fi }}" data-formulario="{{ $tipoForm }}">
    <input type="hidden" name="formularios[{{ $fi }}][id]" value="{{ old("formularios.$fi.id", is_object($formObj) ? ($formObj->id ?? '') : '') }}">
    <input type="hidden" name="formularios[{{ $fi }}][orden]" class="formulario-orden" value="{{ old("formularios.$fi.orden", $ordenFormulario) }}">
    <input type="hidden" name="formularios[{{ $fi }}][formulario]" class="formulario-tipo" value="{{ $tipoForm }}">
    <div class="programa-formulario-cabeza">
        <strong class="formulario-titulo">{{ $tituloForm }}</strong>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-light btn-sm mueve-formulario-izq" title="Mover a la izquierda"><i class="fa fa-arrow-left"></i></button>
            <button type="button" class="btn btn-light btn-sm mueve-formulario-der" title="Mover a la derecha"><i class="fa fa-arrow-right"></i></button>
            <button type="button" class="btn btn-light btn-sm quita-formulario" title="Sacar de la ruta">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </div>
    </div>
    <div class="programa-copia-presets px-2 pt-2">
        <div class="small font-weight-bold mb-1">Elegí las copias de este {{ mb_strtolower($tituloForm) }}</div>
        <div class="programa-copia-chips">
            @foreach(($copiasPreset ?? []) as $preset)
                <button type="button"
                        class="btn btn-sm {{ in_array($preset['codigo'], $codigosEnUso, true) ? 'btn-primary' : 'btn-outline-secondary' }} toggle-copia-preset"
                        data-codigo="{{ $preset['codigo'] }}"
                        data-leyenda="{{ $preset['leyenda'] }}"
                        data-destinatario="{{ $preset['destinatario'] }}">
                    {{ $preset['etiqueta'] }}
                </button>
            @endforeach
        </div>
    </div>
    <div class="programa-copias">
        @foreach($copias as $ci => $copia)
            @include('ventas.programa_impresion.partials.fila_copia', ['fi' => $fi, 'ci' => $ci, 'copia' => $copia])
        @endforeach
    </div>
</div>
