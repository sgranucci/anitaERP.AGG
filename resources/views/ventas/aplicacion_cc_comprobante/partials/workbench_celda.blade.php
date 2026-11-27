@php
    use App\Support\Ventas\AplicacionCcClienteComprobanteListadoColumnas;
    use App\Support\Listado\ListadoGrillaConfigSupport as LwGrilla;
    $metaCelda = AplicacionCcClienteComprobanteListadoColumnas::catalogoActivo()[$key] ?? [];
    $alineaCfg = $cfg['alinea'] ?? ($metaCelda['alinea'] ?? null);
    $clase = $alineaCfg ? LwGrilla::claseAlineacion($alineaCfg) : (($metaCelda['type'] ?? '') === 'decimal' ? 'text-right' : '');
    $texto = AplicacionCcClienteComprobanteListadoColumnas::valorCelda($data, $key);
@endphp
<td class="{{ trim($clase.' lw-col') }}"
    @if (! empty($styleCol)) style="{{ $styleCol }}" @endif
    title="{{ $texto }}">
    @switch($key)
        @case('estado')
            <span class="badge {{ $data->estado === \App\Models\Ventas\AplicacionCcClienteComprobante::ESTADO_ANULADO ? 'badge-danger' : 'badge-success' }}">{{ $texto }}</span>
            @break
        @case('cliente')
        @case('cliente_codigo')
            @if (($puedeVerCliente ?? false) && (int) ($data->cliente_id ?? 0) > 0)
                <a href="{{ route('editar_cliente', ['id' => $data->cliente_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                   class="text-primary" target="_blank" rel="noopener">{{ $texto }}</a>
            @else
                <span class="lw-celda-texto">{{ $texto }}</span>
            @endif
            @break
        @case('numero')
            <strong class="text-nowrap">{{ $texto }}</strong>
            @break
        @default
            <span class="lw-celda-texto">{{ $texto }}</span>
    @endswitch
</td>
