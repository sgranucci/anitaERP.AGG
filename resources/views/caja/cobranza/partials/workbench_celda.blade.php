@php
    use App\Support\Caja\CobranzaListadoColumnas;
    use App\Support\Listado\ListadoGrillaConfigSupport;

    $cfg = $cfg ?? null;
    $alinea = is_array($cfg) ? ($cfg['alinea'] ?? 'izquierda') : match ($key) {
        'monto', 'prom_dias' => 'derecha',
        default => 'izquierda',
    };
    $cls = trim(ListadoGrillaConfigSupport::claseAlineacion($alinea).' lw-col');
    $titleTexto = $key === 'movimientos' ? '' : CobranzaListadoColumnas::valorCelda($data, $key);
@endphp
@switch($key)
    @case('id')
        <td class="{{ $cls }}" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            @if (can('editar-cobranza', false))
                <a href="{{ route('editar_cobranza', ['id' => $data->id, 'origen' => 'cobranza'] + ($retornoListadoQuery ?? [])) }}" class="text-primary">
                    <span class="lw-celda-texto">{{ $data->id }}</span>
                </a>
            @else
                <span class="lw-celda-texto">{{ $data->id }}</span>
            @endif
        </td>
        @break
    @case('fecha')
    @case('monto')
        <td class="{{ $cls }} text-nowrap" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            <span class="lw-celda-texto">{{ $titleTexto }}</span>
        </td>
        @break
    @case('prom_dias')
        <td class="{{ $cls }} text-nowrap" title="{{ $titleTexto }}">
            <span class="lw-celda-texto">{{ $titleTexto }}</span>
        </td>
        @break
    @case('movimientos')
        @php
            $lineasMov = CobranzaListadoColumnas::lineasMovimientos($data);
            $titleMov = implode(' · ', $lineasMov);
        @endphp
        <td class="{{ $cls }}" @if ($titleMov !== '') title="{{ $titleMov }}" @endif>
            @if ($lineasMov !== [])
                <ul class="mb-0 pl-3 small">
                    @foreach ($lineasMov as $lineaMov)
                        <li title="{{ $lineaMov }}">{{ $lineaMov }}</li>
                    @endforeach
                </ul>
            @endif
        </td>
        @break
    @default
        <td class="{{ $cls }}" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            <span class="lw-celda-texto">{{ $titleTexto }}</span>
        </td>
@endswitch
