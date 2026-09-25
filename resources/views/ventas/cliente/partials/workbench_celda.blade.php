@php
    use App\Support\Ventas\ClienteListadoColumnas;
    use App\Support\Listado\ListadoGrillaConfigSupport;
    $cfg = $cfg ?? null;
    $alinea = is_array($cfg) ? ($cfg['alinea'] ?? 'izquierda') : 'izquierda';
    $cls = ListadoGrillaConfigSupport::claseAlineacion($alinea);
    $valor = ClienteListadoColumnas::valorCelda($data, $key);
    $title = is_scalar($valor) ? trim((string) $valor) : '';
@endphp
@if ($key === 'apoc')
    <td class="{{ $cls }} lw-col text-center">
        @if (!empty($data->facturas_apocrifas))
            <span class="badge badge-danger" title="Figura en base ARCA de facturas apócrifas">Sí</span>
        @elseif (!empty($data->facturas_apocrifas_consulta_at))
            <span class="badge badge-success" title="Consultado {{ $data->facturas_apocrifas_consulta_at }}">No</span>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>
@elseif ($key === 'estado')
    <td class="{{ $cls }} lw-col text-center">
        @if (($data->estado ?? '') === '1')
            <span class="badge badge-danger" title="Suspendido">S</span>
        @elseif (($data->estado ?? '') === 'R')
            <span class="badge badge-warning text-dark" title="Regularizado: facturación permitida pese a ARCA">R</span>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>
@else
    <td class="{{ $cls }} lw-col" @if ($title !== '') title="{{ $title }}" @endif><span class="lw-celda-texto">{{ $valor }}</span></td>
@endif
