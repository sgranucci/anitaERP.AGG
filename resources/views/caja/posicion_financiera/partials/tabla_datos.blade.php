@php
    $dias = $dias ?? [];
    $filas = $filas ?? [];
    $modo = $modo ?? 'pantalla';
    $esExcel = $modo === 'excel';
    $esPdf = $modo === 'pdf';
    $envolverTabla = $envolverTabla ?? ! $esExcel;
    $auditoriaUrl = $auditoriaUrl ?? null;
    $auditoriaQuery = $auditoriaQuery ?? [];
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
                $esInformativo = $tipoFila === 'informativo';
                // "Totalcoin…" empieza con "Total" pero no es un total: exigir
                // palabra completa (Total / Total de … / Total Gastronomia, etc.).
                $etiquetaNorm = mb_strtolower(trim($etiqueta));
                $esTotal = $tipoFila === 'total'
                    || preg_match('/^total(\s|$)/u', $etiquetaNorm) === 1
                    || in_array($etiquetaNorm, ['saldo inicial', 'saldo final'], true);
            @endphp
            @if ($esTitulo)
                <tr class="posfin-titulo font-weight-bold">
                    <td class="posfin-concepto" colspan="{{ $colspan }}">{{ $etiqueta }}</td>
                </tr>
            @else
                <tr
                    @class([
                        'font-weight-bold' => $esTotal || $esInformativo,
                        'posfin-total' => $esTotal,
                        'posfin-informativo' => $esInformativo,
                        'table-light' => $esTotal && ! $esPdf && ! $esExcel,
                        'table-warning' => $esInformativo && ! $esPdf && ! $esExcel,
                    ])
                    @if ($esInformativo)
                        style="background-color:#FFF3CD;color:#664D03;"
                    @endif
                >
                    <td class="posfin-concepto">
                        {{ $etiqueta }}
                        @if ($esInformativo)
                            <br>
                            <span class="posfin-informativo-aviso">INFORMATIVO · No interviene en ningún cálculo</span>
                        @endif
                    </td>
                    @foreach ($dias as $dia)
                        @php
                            $importeDia = (float) ($porDia[$dia] ?? 0);
                        @endphp
                        <td class="text-right posfin-dia">
                            @if (abs($importeDia) >= 0.005)
                                @if (! $esPdf && ! $esExcel && $auditoriaUrl)
                                    <a href="{{ $auditoriaUrl.'?'.http_build_query(array_merge($auditoriaQuery, [
                                        'dia' => $dia,
                                        'bloque' => (string) ($fila['bloque'] ?? ''),
                                        'etiqueta' => $etiqueta,
                                    ])) }}"
                                       class="posfin-auditoria-link"
                                       title="Ver origen y composición de este importe">{{ $formatear($importeDia) }}</a>
                                @else
                                    {{ $formatear($importeDia) }}
                                @endif
                            @endif
                        </td>
                    @endforeach
                    <td class="text-right posfin-total-col">
                        @if (! $esPdf && ! $esExcel && $auditoriaUrl && abs($valor) >= 0.005)
                            <a href="{{ $auditoriaUrl.'?'.http_build_query(array_merge($auditoriaQuery, [
                                'dia' => 0,
                                'bloque' => (string) ($fila['bloque'] ?? ''),
                                'etiqueta' => $etiqueta,
                            ])) }}"
                               class="posfin-auditoria-link posfin-auditoria-link-total"
                               title="Ver composición mensual de este importe">{{ $formatear($valor) }}</a>
                        @else
                            {{ $formatear($valor) }}
                        @endif
                    </td>
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
