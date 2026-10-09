@php
    use App\Support\Caja\IngresoEgresoListadoColumnas;
    $alineado = $key === 'monto' ? 'text-right' : '';
    $ieMonto = $ieMonto ?? IngresoEgresoListadoColumnas::resumenFila($data);
@endphp
<td class="{{ $alineado }}">
    @switch($key)
        @case('id')
            @if (can('editar-ingresos-egresos-caja', false))
                <a href="{{ route('editar_ingresoegreso', ['id' => $data->id, 'origen' => 'ingresoegreso'] + ($retornoListadoQuery ?? [])) }}" class="text-primary">{{ $data->id }}</a>
            @else
                {{ $data->id }}
            @endif
            @break
        @case('monto')
            {{ number_format($ieMonto['monto'], 2, ',', '.') }}
            @break
        @case('movimientos')
            <ul class="mb-0 pl-3 small">
                @foreach ($ieMonto['lineas'] as $lineaMov)
                    <li>{{ $lineaMov }}</li>
                @endforeach
            </ul>
            @break
        @case('tipo')
            {{ IngresoEgresoListadoColumnas::valorCelda($data, 'tipo') }}
            @break
        @default
            {{ IngresoEgresoListadoColumnas::valorCelda($data, $key) }}
    @endswitch
</td>
