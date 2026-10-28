@php
    $formatoExcel = \App\Support\Export\ExcelFormatoNumero::normalizar(
        $excel_formato_numero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal()
    );
    // En modo auto escribe el número crudo y la máscara la pone WithColumnFormatting;
    // en ar/intl escribe el texto ya formateado.
    $fmtMonto = \App\Support\Export\ExcelFormatoNumero::formateadorMonto($formatoExcel, 2);
    $notas = $resultado['notas'] ?? [];
    $marcasNota = $resultado['notas_marcas'] ?? [];
    $gruposColumna = [];
    foreach (($resultado['columnas'] ?? []) as $colGrupo) {
        $nombreGrupo = trim((string) ($colGrupo['grupo'] ?? ''));
        if ($nombreGrupo !== '') {
            $gruposColumna[$nombreGrupo] = true;
        }
    }
    $mostrarGrupos = count($gruposColumna) > 1;
@endphp
<table>
    @if (!empty($hayFilaLogos))
        <tr>
            <td colspan="20" style="height:52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="20">
            <strong style="font-size:16px;">{{ $reporte->titulo1 ?: $reporte->nombre }}</strong>
        </td>
    </tr>
    <tr>
        <td colspan="20">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if ($reporte->titulo2)
        <tr>
            <td colspan="20">{{ $reporte->titulo2 }}</td>
        </tr>
    @endif
    @if (trim((string) ($resultado['criterios'] ?? '')) !== '')
        <tr>
            <td colspan="20">{!! nl2br(e($resultado['criterios'])) !!}</td>
        </tr>
    @endif
    <thead>
        @if ($mostrarGrupos)
            <tr>
                <th></th>
                <th></th>
                @foreach (($resultado['columnas'] ?? []) as $col)
                    <th>{{ $col['grupo'] ?? '' }}</th>
                @endforeach
            </tr>
        @endif
        <tr>
            <th>Código</th>
            <th>Concepto</th>
            @foreach (($resultado['columnas'] ?? []) as $col)
                <th>{{ $col['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach (($resultado['filas'] ?? []) as $fila)
            <tr>
                @php
                    $marca = ($fila['kind'] ?? 'rubro') === 'cuenta'
                        ? null
                        : ($marcasNota[strtoupper(trim((string) ($fila['codigo'] ?? '')))]
                            ?? ($marcasNota['#'.(int) ($fila['rubro_id'] ?? 0)] ?? null));
                @endphp
                <td>{{ $fila['codigo'] ?? '' }}</td>
                <td>{{ str_repeat('  ', (int)($fila['depth'] ?? 0)).($fila['nombre'] ?? '').($marca ? ' ('.$marca.')' : '') }}</td>
                @if ($fila['saldos'] === null)
                    @foreach (($resultado['columnas'] ?? []) as $col)
                        <td></td>
                    @endforeach
                @else
                    @foreach (($resultado['columnas'] ?? []) as $col)
                        @php $key = $col['key'] ?? ''; @endphp
                        <td>{{ $fmtMonto($fila['saldos'][$key] ?? null) }}</td>
                    @endforeach
                @endif
            </tr>
        @endforeach
        @if (!empty($notas))
            <tr><td></td><td></td>@foreach (($resultado['columnas'] ?? []) as $col)<td></td>@endforeach</tr>
            <tr><td>Notas</td><td></td>@foreach (($resultado['columnas'] ?? []) as $col)<td></td>@endforeach</tr>
            @foreach ($notas as $nota)
                <tr>
                    <td>({{ (int) $nota['marca'] }}){{ !empty($nota['codigo_linea']) ? ' '.$nota['codigo_linea'] : '' }}</td>
                    <td>{{ $nota['texto'] }}{{ (!empty($nota['vigencia_texto']) && $nota['vigencia_texto'] !== 'Siempre') ? ' ('.$nota['vigencia_texto'].')' : '' }}</td>
                    @foreach (($resultado['columnas'] ?? []) as $col)
                        <td></td>
                    @endforeach
                </tr>
            @endforeach
        @endif
    </tbody>
</table>
