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
    $ocIdLinea = (int) (($ocPorNumero ?? [])[$nroOcLinea] ?? ($ocPorNumero ?? [])[(string) $nroOcLinea] ?? 0);
    $puedeVerOcLinea = $nroOcLinea > 0 && $ocIdLinea > 0 && (can('listar-ordencompra', false) || can('editar-ordencompra', false));
@endphp
<td class="text-nowrap small">
    <input type="hidden" name="mov_anita_tipo[]" value="{{ $tipoLinea }}">
    <input type="hidden" name="mov_anita_letra[]" value="{{ $letraLinea }}">
    <input type="hidden" name="mov_anita_sucursal[]" value="{{ $sucLinea }}">
    <input type="hidden" name="mov_anita_nro[]" value="{{ $nroLinea }}">
    {{ $etiquetaComp !== '' ? $etiquetaComp : '—' }}
</td>
<td class="text-nowrap small">
    <input type="hidden" name="mov_nro_ordencompra[]" value="{{ $nroOcLinea }}">
    @if ($puedeVerOcLinea)
        <a href="{{ route('editar_ordencompra', ['id' => $ocIdLinea, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
           target="_blank" rel="noopener" class="text-primary">{{ $nroOcLinea }}</a>
    @elseif ($nroOcLinea > 0)
        {{ $nroOcLinea }}
    @else
        —
    @endif
</td>
