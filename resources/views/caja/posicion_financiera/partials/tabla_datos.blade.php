@php
    $dias = $dias ?? [];
    $filas = $filas ?? [];
    $modo = $modo ?? 'pantalla';
    $esExcel = $modo === 'excel';
    $esPdf = $modo === 'pdf';
    $envolverTabla = $envolverTabla ?? ! $esExcel;
    $colspan = 2 + count($dias);
    $formatear = static function (float $valor) use ($esExcel): string {
        if ($esExcel) {
            return number_format($valor, 2, '.', '');
        }

        return number_format($valor, 2, ',', '.');
    };
@endphp
@if ($envolverTabla)
<table class="table table-sm table-bordered table-hover mb-0 posfin-tabla{{ $esPdf ? ' data' : '' }}" id="{{ $esPdf || $esExcel ? '' : 'tabla-paginada' }}">
@endif
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th class="posfin-concepto">Concepto</th>
            @foreach ($dias as $dia)
                <th class="text-right posfin-dia">{{ str_pad((string) $dia, 2, '0', STR_PAD_LEFT) }}</th>
            @endforeach
            <th class="text-right posfin-total-col">Total mensual</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            @php
                $tipoFila = (string) ($fila['tipo_fila'] ?? 'concepto');
            @endphp
            @if ($tipoFila === 'relleno_efe')
                @continue
            @endif
            @php
                $etiqueta = (string) ($fila['etiqueta'] ?? '');
                $porDia = $fila['por_dia'] ?? [];
                $valor = (float) ($fila['valor'] ?? 0);
                $esTitulo = $tipoFila === 'titulo';
                $esTotal = $tipoFila === 'total'
                    || str_starts_with(mb_strtolower(trim($etiqueta)), 'total')
                    || in_array(mb_strtolower(trim($etiqueta)), ['saldo inicial', 'saldo final'], true);
            @endphp
            @if ($esTitulo)
                <tr class="posfin-titulo font-weight-bold">
                    <td class="posfin-concepto" colspan="{{ $colspan }}">{{ $etiqueta }}</td>
                </tr>
            @else
                <tr @class(['font-weight-bold' => $esTotal, 'posfin-total' => $esTotal, 'table-light' => $esTotal && ! $esPdf && ! $esExcel])>
                    <td class="posfin-concepto">{{ $etiqueta }}</td>
                    @foreach ($dias as $dia)
                        @php
                            $importeDia = (float) ($porDia[$dia] ?? 0);
                        @endphp
                        <td class="text-right posfin-dia">
                            @if (abs($importeDia) >= 0.005)
                                {{ $formatear($importeDia) }}
                            @endif
                        </td>
                    @endforeach
                    <td class="text-right posfin-total-col">{{ $formatear($valor) }}</td>
                </tr>
            @endif
        @empty
            <tr>
                <td colspan="{{ $colspan }}" class="text-center text-muted py-4">Sin datos para el período.</td>
            </tr>
        @endforelse
    </tbody>
@if ($envolverTabla)
</table>
@endif
