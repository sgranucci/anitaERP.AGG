@php
    $lineas = $lineas ?? collect();
    $moneda = $moneda ?? '';
    $separator = $separator ?? '<br>';
    $modo = $modo ?? 'html';
@endphp
@if ($lineas->isEmpty())
    <span class="text-muted">Sin precios vigentes</span>
@elseif ($modo === 'tabla')
    <table class="table table-sm table-bordered mb-0 tabla-precios-vigentes-detalle">
        <thead>
            <tr>
                <th>&Iacute;tem</th>
                <th class="text-right">Precio</th>
                <th>Vigente desde</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lineas as $linea)
                <tr>
                    <td>{{ $linea->item_nombre ?? '' }}</td>
                    <td class="text-right text-nowrap">
                        @if ($linea->precio !== null && $linea->precio !== '')
                            {{ number_format((float) $linea->precio, 2, ',', '.') }}@if ($moneda !== '') {{ ' '.$moneda }}@endif
                        @endif
                    </td>
                    <td class="text-nowrap">
                        @if (!empty($linea->fecha_vigencia))
                            {{ \Carbon\Carbon::parse($linea->fecha_vigencia)->format('d/m/Y') }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    @foreach ($lineas as $idx => $linea)
        @php
            $texto = ($linea->item_nombre ?? '');
            if ($linea->precio !== null && $linea->precio !== '') {
                $texto .= ': '.number_format((float) $linea->precio, 2, ',', '.');
                if ($moneda !== '') {
                    $texto .= ' '.$moneda;
                }
            }
            if (!empty($linea->fecha_vigencia)) {
                $texto .= ' (desde '.\Carbon\Carbon::parse($linea->fecha_vigencia)->format('d/m/Y').')';
            }
        @endphp
        @if ($idx > 0){!! $separator !!}@endif{{ $texto }}
    @endforeach
@endif
