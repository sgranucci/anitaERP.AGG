@php
    /**
     * @var array<int, array<string, mixed>> $columnas
     * @var list<int> $indices
     * @var string $formato
     * @var bool $mostrarTotal
     * @var bool $fijas  columnas fijas a la izquierda (solo pantalla)
     */
    $dh = ($formato ?? 'saldo') === 'debe_haber';
    $indices = $indices ?? array_keys($columnas ?? []);
    $mostrarTotal = ! empty($mostrarTotal);
    $fijas = ! empty($fijas);
@endphp
<tr class="columnas">
    <th class="mcc-col-cta {{ $fijas ? 'col-fija-1' : '' }}" {!! $dh ? 'rowspan="2"' : '' !!}>Nro. Cta.</th>
    <th class="mcc-col-desc {{ $fijas ? 'col-fija-2' : '' }}" {!! $dh ? 'rowspan="2"' : '' !!}>Descripci&oacute;n</th>
    @foreach ($indices as $i)
        @php $col = $columnas[$i]; @endphp
        <th class="text-center" {!! $dh ? 'colspan="2"' : '' !!}
            title="{{ $col['nombre'] }}{{ $col['tipo'] === 'ccosto' ? ' (CC '.$col['codigo'].')' : ($col['tipo'] === 'grupo' ? ' (Grupo '.$col['codigo'].')' : '') }}">
            {{ $col['nombre'] }}
            @if ($col['tipo'] !== 'sin')
                <br><small>{{ $col['tipo'] === 'grupo' ? 'Grupo ' : 'CC ' }}{{ $col['codigo'] }}</small>
            @endif
        </th>
    @endforeach
    @if ($mostrarTotal)
        <th class="text-center" {!! $dh ? 'colspan="2"' : '' !!}>Total</th>
    @endif
</tr>
@if ($dh)
    <tr class="columnas">
        @foreach ($indices as $i)
            <th class="text-center">Debe</th>
            <th class="text-center">Haber</th>
        @endforeach
        @if ($mostrarTotal)
            <th class="text-center">Debe</th>
            <th class="text-center">Haber</th>
        @endif
    </tr>
@endif
