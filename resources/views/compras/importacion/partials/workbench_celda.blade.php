@php
    use App\Support\Compras\ImportacionEstados;
    use App\Support\Compras\ImportacionListadoColumnas;
    use App\Support\Listado\ListadoGrillaConfigSupport;
    $cfg = $cfg ?? null;
    $alinea = is_array($cfg) ? ($cfg['alinea'] ?? 'izquierda') : 'izquierda';
    $cls = ListadoGrillaConfigSupport::claseAlineacion($alinea);
    $valor = ImportacionListadoColumnas::valorCelda($data, $key);
    $title = is_scalar($valor) ? trim((string) $valor) : '';
@endphp
@if ($key === 'codigo')
    <td class="{{ $cls }} lw-col" @if ($title !== '') title="{{ $title }}" @endif>
        @if (can('editar-importacion', false))
            <a href="{{ route('editar_importacion', ['id' => $data->id] + ($retornoListadoQuery ?? [])) }}" class="text-primary font-weight-bold">
                {{ $valor }}
            </a>
        @else
            <span class="lw-celda-texto">{{ $valor }}</span>
        @endif
    </td>
@elseif ($key === 'estado')
    <td class="{{ $cls }} lw-col" @if ($title !== '') title="{{ $title }}" @endif>
        @php $est = (string) ($data->estado ?? ''); @endphp
        @if ($est === ImportacionEstados::COSTOS_CERRADOS)
            <span class="badge badge-secondary">{{ $valor }}</span>
        @elseif ($est === ImportacionEstados::NACIONALIZADA)
            <span class="badge badge-success">{{ $valor }}</span>
        @elseif ($est === ImportacionEstados::ANULADA)
            <span class="badge badge-danger">{{ $valor }}</span>
        @elseif ($est === ImportacionEstados::BORRADOR)
            <span class="badge badge-light text-dark border">{{ $valor }}</span>
        @else
            <span class="badge badge-info">{{ $valor }}</span>
        @endif
    </td>
@else
    <td class="{{ $cls }} lw-col" @if ($title !== '') title="{{ $title }}" @endif><span class="lw-celda-texto">{{ $valor }}</span></td>
@endif
