@php
    use App\Support\Contable\BienUsoListadoColumnas;
    use App\Support\Listado\ListadoGrillaConfigSupport as LwGrilla;
    $decimals = ['valor_origen', 'valor_baja', 'monto_factura'];
    $esId = $key === 'id';
    $alineaCfg = $cfg['alinea'] ?? null;
    if ($alineaCfg) {
        $clase = LwGrilla::claseAlineacion($alineaCfg);
    } else {
        $clase = in_array($key, $decimals, true) ? 'text-right' : '';
    }
    $texto = BienUsoListadoColumnas::valorCelda($data, $key);
@endphp
<td class="{{ trim($clase.' lw-col') }}"
    @if (! empty($styleCol)) style="{{ $styleCol }}" @endif
    title="{{ $texto }}">
    @if ($esId && ($puede_ver_bien_uso ?? false))
        <a href="{{ route('editar_bien_uso', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
           class="text-primary" target="_blank" rel="noopener">{{ $data->id }}</a>
    @else
        <span class="lw-celda-texto">{{ $texto }}</span>
    @endif
</td>
