@php
    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $partidagasto = $partidagasto ?? collect();
    $colspan = 14;

    $empresasUnicas = collect($partidagasto)->map(fn ($p) => (string) ($p->nombreempresa ?? ''))->filter()->unique();
    $subtitulo = trim(
        ($empresasUnicas->count() === 1 ? $empresasUnicas->first().' — ' : '')
        .(is_countable($partidagasto) ? count($partidagasto) : 0).' registro(s)'
    );

    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    // Columna "Monto Total": número real (sumable + adaptable por PC en modo auto).
    $fmtMonto = function ($v) use ($formatoNumero, $autoExcelNum) {
        $n = (float) $v;
        return $autoExcelNum
            ? number_format($n, 2, '.', '')
            : \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, 2);
    };
    // Montos dentro de la lista "Apertura": texto descriptivo.
    $fmtTexto = function ($v) use ($formatoNumero) {
        return \App\Support\Export\ExcelFormatoNumero::formatearTexto((float) $v, $formatoNumero, 2);
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Partidas de gasto</title>
</head>
<body>
<table class="data">
    @if ($reservarFilaLogoExcel)
        <tbody>
            <tr><td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td></tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">Partidas de gasto</strong></td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}"><strong>Generado {{ date('d/m/Y H:i') }} — {{ $subtitulo }}</strong></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>ID</th>
            <th>Empresa</th>
            <th>Presupuesto</th>
            <th>Escenario</th>
            <th>Centro de Costo</th>
            <th>Partida</th>
            <th>Detalle</th>
            <th>Articulo</th>
            <th>Proveedor</th>
            <th>Cuenta Contable</th>
            <th>Moneda</th>
            <th class="num">Monto Total</th>
            <th>Estado</th>
            <th>Apertura</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($partidagasto as $data)
            @php
                $montoTotal = 0;
                foreach ($data->partidagasto_montos as $partida) {
                    $montoTotal += $partida->monto;
                }
            @endphp
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ $data->nombreempresa ?? '' }}</td>
                <td>{{ $data->nombrepresupuesto ?? '' }}</td>
                <td>{{ $data->nombreescenario ?? '' }}</td>
                <td>{{ trim(($data->codigocentrocosto ?? '').' '.($data->nombrecentrocosto ?? '')) }}</td>
                <td>{{ $data->codigopartida ?? '' }}</td>
                <td>{{ $data->detalle ?? '' }}</td>
                <td>{{ $data->descripcionarticulo ?? '' }}</td>
                <td>{{ $data->nombreproveedor ?? '' }}</td>
                <td>{{ $data->codigocuentacontable }}-{{ $data->nombrecuentacontable ?? '' }}</td>
                <td>{{ $data->abreviaturamoneda }}</td>
                <td class="num">{{ $fmtMonto($montoTotal) }}</td>
                <td>{{ $data->estado }}</td>
                <td>
                    <ul>
                        @foreach ($data->partidagasto_montos as $partida)
                            <li>{{ $partida->periodo }} {{ $fmtTexto($partida->monto) }}</li>
                        @endforeach
                    </ul>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $colspan }}" style="text-align:center;">Sin registros</td>
            </tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
