@php
    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $capex = $capex ?? collect();
    $colspan = 10;

    $empresasUnicas = collect($capex)->map(fn ($c) => (string) ($c->nombreempresa ?? ''))->filter()->unique();
    $subtitulo = trim(
        ($empresasUnicas->count() === 1 ? $empresasUnicas->first().' — ' : '')
        .(is_countable($capex) ? count($capex) : 0).' registro(s)'
    );

    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $fmtNum = function ($v) use ($formatoNumero) {
        return \App\Support\Export\ExcelFormatoNumero::formatearTexto((float) $v, $formatoNumero, 2);
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Capex</title>
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
            <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">Capex</strong></td>
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
            <th>Centro de Costo</th>
            <th>Nombre</th>
            <th>Detalle</th>
            <th>Codigo de Proyecto</th>
            <th>Nro. de Proyecto</th>
            <th>Estado</th>
            <th>Partidas</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($capex as $data)
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ $data->nombreempresa ?? '' }}</td>
                <td>{{ $data->nombrepresupuesto ?? '' }}</td>
                <td>{{ trim(($data->codigocentrocosto ?? '').' '.($data->nombrecentrocosto ?? '')) }}</td>
                <td>{{ $data->nombre ?? '' }}</td>
                <td>{{ $data->detalle ?? '' }}</td>
                <td>{{ $data->codigoproyecto }}</td>
                <td>{{ $data->codigo }}</td>
                <td>{{ $data->estado }}</td>
                <td>
                    <ul>
                        @foreach ($data->capex_partidas as $partida)
                            @php
                                $montoTotal = 0;
                                foreach ($partida->capex_partida_montos as $monto) {
                                    $montoTotal += $monto->monto;
                                }
                            @endphp
                            <li>Nro.{{ $partida->codigo }} {{ $partida->nombre }} {{ $partida->monedas->abreviatura ?? '' }} {{ $fmtNum($montoTotal) }}</li>
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
