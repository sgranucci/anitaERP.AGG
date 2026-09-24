@php
    use App\Support\Compras\ProveedorListadoColumnas;
    use App\Support\Listado\ListadoGrillaConfigSupport;
    $cfg = $cfg ?? null;
    $alinea = is_array($cfg) ? ($cfg['alinea'] ?? 'izquierda') : 'izquierda';
    $cls = ListadoGrillaConfigSupport::claseAlineacion($alinea);
    $valor = ProveedorListadoColumnas::valorCelda($data, $key);
    $title = is_scalar($valor) ? trim((string) $valor) : '';
@endphp
@if ($key === 'apoc')
    <td class="{{ $cls }} lw-col">
        @if (!empty($data->facturas_apocrifas))
            <span class="badge badge-danger" title="Figura en base ARCA de facturas apócrifas">Sí</span>
        @elseif (!empty($data->facturas_apocrifas_consulta_at))
            <span class="badge badge-success" title="Consultado {{ $data->facturas_apocrifas_consulta_at }}">No</span>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>
@else
    <td class="{{ $cls }} lw-col" @if ($title !== '') title="{{ $title }}" @endif><span class="lw-celda-texto">{{ $valor }}</span></td>
@endif
