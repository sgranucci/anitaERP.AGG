@php
    use App\Support\Compras\AplicacionCcProveedorComprobanteListadoColumnas;
    use App\Support\Listado\ListadoGrillaConfigSupport as LwGrilla;
    $metaCelda = AplicacionCcProveedorComprobanteListadoColumnas::catalogoActivo()[$key] ?? [];
    $alineaCfg = $cfg['alinea'] ?? ($metaCelda['alinea'] ?? null);
    $clase = $alineaCfg ? LwGrilla::claseAlineacion($alineaCfg) : (($metaCelda['type'] ?? '') === 'decimal' ? 'text-right' : '');
    $texto = AplicacionCcProveedorComprobanteListadoColumnas::valorCelda($data, $key);
@endphp
<td class="{{ trim($clase.' lw-col') }}"
    @if (! empty($styleCol)) style="{{ $styleCol }}" @endif
    title="{{ $texto }}">
    @switch($key)
        @case('estado')
            <span class="badge {{ $data->estado === \App\Models\Compras\AplicacionCcProveedorComprobante::ESTADO_ANULADO ? 'badge-danger' : 'badge-success' }}">{{ $texto }}</span>
            @break
        @case('proveedor')
        @case('proveedor_codigo')
            @if (($puedeVerProveedor ?? false) && (int) ($data->proveedor_id ?? 0) > 0)
                <a href="{{ route('editar_proveedor', ['id' => $data->proveedor_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
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
