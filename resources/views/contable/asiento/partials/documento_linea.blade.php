@php
    $movLinea = is_object($mov ?? null) ? $mov : null;
    $idxLinea = $indice ?? null;
    $tipoLinea = $idxLinea !== null
        ? (string) old('mov_anita_tipo.'.$idxLinea, $movLinea?->anita_tipo ?? '')
        : (string) ($movLinea?->anita_tipo ?? '');
    $letraLinea = $idxLinea !== null
        ? (string) old('mov_anita_letra.'.$idxLinea, $movLinea?->anita_letra ?? '')
        : (string) ($movLinea?->anita_letra ?? '');
    $sucLinea = (int) ($idxLinea !== null
        ? old('mov_anita_sucursal.'.$idxLinea, $movLinea?->anita_sucursal ?? 0)
        : ($movLinea?->anita_sucursal ?? 0));
    $nroLinea = (int) ($idxLinea !== null
        ? old('mov_anita_nro.'.$idxLinea, $movLinea?->anita_nro ?? 0)
        : ($movLinea?->anita_nro ?? 0));
    $nroOcLinea = (int) ($idxLinea !== null
        ? old('mov_nro_ordencompra.'.$idxLinea, $movLinea?->nro_ordencompra ?? 0)
        : ($movLinea?->nro_ordencompra ?? 0));
    $etiquetaComp = '';
    if ($nroLinea > 0) {
        $compFmt = \App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport::formatearComprobante(
            $tipoLinea,
            $letraLinea !== '' ? $letraLinea : ' ',
            $sucLinea,
            $nroLinea,
        );
        $etiquetaComp = trim(strtoupper(trim($tipoLinea)).' '.$compFmt);
    }
    $textoComp = $idxLinea !== null ? old('comprobante_linea.'.$idxLinea) : null;
    if ($textoComp === null) {
        $textoComp = $etiquetaComp;
    }
    $textoOc = $idxLinea !== null ? old('ordencompra_linea.'.$idxLinea) : null;
    if ($textoOc === null) {
        $textoOc = $nroOcLinea > 0 ? (string) $nroOcLinea : '';
    }
@endphp
<td class="asiento-doc-celda">
    <input type="text" name="comprobante_linea[]" class="form-control form-control-sm comprobante-linea"
           value="{{ $textoComp }}" placeholder="FC A0001-123" autocomplete="off"
           title="Tipo, letra y número. Ejemplo: FC A0001-123">
</td>
<td class="asiento-doc-celda">
    <input type="text" name="ordencompra_linea[]" class="form-control form-control-sm ordencompra-linea"
           value="{{ $textoOc }}" placeholder="Nº" autocomplete="off"
           title="Número de orden de compra">
</td>
