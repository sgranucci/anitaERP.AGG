@php
    use App\Support\Caja\IngresoEgresoListadoColumnas;
    use App\Support\Listado\ListadoGrillaConfigSupport;

    $ieMonto = $ieMonto ?? IngresoEgresoListadoColumnas::resumenFila($data);
    $cfg = $cfg ?? null;
    $alinea = is_array($cfg) ? ($cfg['alinea'] ?? 'izquierda') : match ($key) {
        'monto' => 'derecha',
        default => 'izquierda',
    };
    $cls = trim(ListadoGrillaConfigSupport::claseAlineacion($alinea).' lw-col');
    $titleTexto = IngresoEgresoListadoColumnas::valorCelda($data, $key);
@endphp
@switch($key)
    @case('id')
        <td class="{{ $cls }}" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            @if (can('editar-ingresos-egresos-caja', false) || can('listar-ingresos-egresos-caja', false))
                <a href="{{ route('editar_ingresoegreso', ['id' => $data->id, 'origen' => 'ingresoegreso'] + ($retornoListadoQuery ?? [])) }}" class="text-primary">
                    <span class="lw-celda-texto">{{ $data->id }}</span>
                </a>
            @else
                <span class="lw-celda-texto">{{ $data->id }}</span>
            @endif
        </td>
        @break
    @case('monto')
        <td class="{{ $cls }} text-nowrap" title="{{ number_format($ieMonto['monto'], 2, ',', '.') }}">
            <span class="lw-celda-texto">{{ number_format($ieMonto['monto'], 2, ',', '.') }}</span>
        </td>
        @break
    @case('movimientos')
        @php
            $lineasMov = $ieMonto['lineas'] ?? [];
            $titleMov = implode(' · ', $lineasMov);
        @endphp
        <td class="{{ $cls }} ie-movimientos-celda" @if ($titleMov !== '') title="{{ $titleMov }}" @endif>
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
