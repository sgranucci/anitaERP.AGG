@php
    use App\Support\Caja\CajeroCrownSistemaExternoSupport;
    $sistema = (string) ($sistema ?? '');
    $usaMozo = CajeroCrownSistemaExternoSupport::usaMozo($sistema);
    $usaCodigo = $sistema === '' || CajeroCrownSistemaExternoSupport::usaCodigoExterno($sistema);
@endphp
<tr class="tc-externo-renglon">
    <td>
        <select name="externos[{{ $indice }}][sistema]" class="form-control form-control-sm tc-externo-sistema">
            <option value="">— Elegir —</option>
            @foreach ($sistemas as $opcion)
                <option value="{{ $opcion }}" @selected($sistema === $opcion)>{{ $opcion }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="text" name="externos[{{ $indice }}][codigo_externo]" class="form-control form-control-sm tc-externo-codigo"
               maxlength="30" value="{{ $codigoExterno }}"
               @if (! $usaCodigo) disabled @endif
               placeholder="PXSOL / GST">
    </td>
    <td>
        <input type="text" name="externos[{{ $indice }}][mozo_codigo]" class="form-control form-control-sm tc-externo-mozo"
               maxlength="20" value="{{ $mozoCodigo }}"
               @if (! $usaMozo) disabled @endif
               placeholder="Sólo GASTRONOMIA">
    </td>
    <td class="text-nowrap">
        <button type="button" class="btn-accion-tabla tooltipsC tc-externo-quitar" title="Quitar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
