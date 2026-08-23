@php
    $copiaObj = is_array($copia ?? null) ? (object) $copia : ($copia ?? null);
    $codigoCopia = strtoupper((string) (is_object($copiaObj) ? ($copiaObj->codigo ?? 'ORI') : 'ORI'));
    $leyendaCopia = (string) (is_object($copiaObj) ? ($copiaObj->leyenda ?? 'ORIGINAL') : 'ORIGINAL');
    $presetMatch = null;
    foreach (($copiasPreset ?? []) as $preset) {
        if ($preset['codigo'] === $codigoCopia) {
            $presetMatch = $preset['codigo'];
            break;
        }
    }
    $ordenCopia = is_object($copiaObj)
        ? ($copiaObj->orden ?? (is_numeric($ci) ? ((int) $ci + 1) * 10 : 10))
        : (is_numeric($ci) ? ((int) $ci + 1) * 10 : 10);
    $copiaId = is_object($copiaObj) ? ($copiaObj->id ?? '') : '';
    $copiaDest = is_object($copiaObj) ? ($copiaObj->destinatario ?? '') : '';
    $copiaSalidaId = is_object($copiaObj) ? (int) ($copiaObj->salida_id ?? 0) : 0;
    $copiaPdfSesion = is_object($copiaObj) ? ($copiaObj->incluir_en_pdf_sesion ?? true) : true;
@endphp
<div class="programa-copia-hoja" data-ci="{{ $ci }}" data-codigo="{{ $codigoCopia }}">
    <input type="hidden" name="formularios[{{ $fi }}][copias][{{ $ci }}][id]" value="{{ $copiaId }}">
    <input type="hidden" name="formularios[{{ $fi }}][copias][{{ $ci }}][orden]" class="copia-orden" value="{{ $ordenCopia }}">
    <input type="hidden" name="formularios[{{ $fi }}][copias][{{ $ci }}][codigo]" class="copia-codigo" value="{{ $codigoCopia }}" required>
    <input type="hidden" name="formularios[{{ $fi }}][copias][{{ $ci }}][leyenda]" class="copia-leyenda" value="{{ $leyendaCopia }}" required>
    <div class="form-group mb-1">
        <label class="small">Esta copia es</label>
        <select class="form-control form-control-sm copia-preset">
            @foreach(($copiasPreset ?? []) as $preset)
                <option value="{{ $preset['codigo'] }}"
                        data-leyenda="{{ $preset['leyenda'] }}"
                        data-destinatario="{{ $preset['destinatario'] }}"
                    {{ $presetMatch === $preset['codigo'] ? 'selected' : '' }}>
                    {{ $preset['etiqueta'] }} — {{ $preset['leyenda'] }}
                </option>
            @endforeach
            <option value="OTRA" {{ $presetMatch === null ? 'selected' : '' }}>Otra (texto libre)</option>
        </select>
    </div>
    <div class="form-group mb-1 copia-otra-wrap" style="{{ $presetMatch === null ? '' : 'display:none' }}">
        <label class="small">Leyenda en el PDF</label>
        <input type="text" class="form-control form-control-sm copia-leyenda-otra" maxlength="60" value="{{ $leyendaCopia }}">
    </div>
    <div class="form-group mb-1">
        <label class="small">A quién va / ruta</label>
        <input type="text" name="formularios[{{ $fi }}][copias][{{ $ci }}][destinatario]" class="form-control form-control-sm copia-destinatario" maxlength="80" value="{{ $copiaDest }}" placeholder="Cliente, chofer, archivo NAS…">
    </div>
    <div class="form-group mb-1">
        <label class="small">Sale por</label>
        <select name="formularios[{{ $fi }}][copias][{{ $ci }}][salida_id]" class="form-control form-control-sm copia-salida">
            <option value="">Impresora del usuario</option>
            @foreach($salidas as $salida)
                <option value="{{ $salida->id }}" {{ $copiaSalidaId === (int) $salida->id ? 'selected' : '' }}>{{ $salida->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="programa-copia-acciones">
        <div class="form-check mb-0">
            <input type="hidden" name="formularios[{{ $fi }}][copias][{{ $ci }}][incluir_en_pdf_sesion]" value="0">
            <input type="checkbox" name="formularios[{{ $fi }}][copias][{{ $ci }}][incluir_en_pdf_sesion]" value="1" class="form-check-input copia-pdf-sesion" {{ $copiaPdfSesion ? 'checked' : '' }}>
            <label class="form-check-label small">En PDF de sesión</label>
        </div>
        <button type="button" class="btn btn-outline-danger btn-sm quita-copia" title="Quitar esta copia">
            <i class="fa fa-times-circle"></i> Quitar
        </button>
    </div>
</div>
