@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $rendiciones = $rendiciones ?? collect();
    $colspan = 11;

    foreach ($rendiciones as $r) {
        $r->nombreempresa = $r->empresa?->nombre ?? '';
    }
    $logosCabecera = $esExcel ? [] : EmpresaLogoArchivo::logosCabeceraDesdeColeccion($rendiciones);

    $empresasUnicas = collect($rendiciones)->map(fn ($r) => (string) ($r->empresa?->nombre ?? ''))->filter()->unique();
    $subtitulo = trim(
        ($empresasUnicas->count() === 1 ? $empresasUnicas->first().' — ' : '')
        .(is_countable($rendiciones) ? count($rendiciones) : 0).' registro(s)'
    );

    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtNum = function ($v) use ($esExcel, $formatoNumero, $autoExcelNum) {
        $n = (float) $v;
        if ($esExcel && $autoExcelNum) {
            return number_format($n, 2, '.', '');
        }
        if ($esExcel) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, 2);
        }
        return number_format($n, 2, ',', '.');
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rend. bingo caja</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 3px 5px; text-align: left; vertical-align: top; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        .num { text-align: right; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { border: none; vertical-align: middle; }
        .meta { font-size: 8px; color: #444; margin-top: 2px; }
    </style>
</head>
<body>
@if (! $esExcel)
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 160px; margin-right: 8px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 47%; text-align: center;">
                <h2 style="margin: 0; font-size: 14px; font-weight: bold;">Rendiciones bingo — caja</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $subtitulo }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                Registros: {{ is_countable($rendiciones) ? count($rendiciones) : 0 }}
            </td>
        </tr>
    </table>
@endif

<table class="data">
    @if ($esExcel)
        @if ($reservarFilaLogoExcel)
            <tbody>
                <tr><td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td></tr>
            </tbody>
        @endif
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">Rendiciones bingo — caja</strong></td>
            </tr>
            <tr>
                <td colspan="{{ $colspan }}"><strong>Generado {{ date('d/m/Y H:i') }} — {{ $subtitulo }}</strong></td>
            </tr>
        </tbody>
    @endif
    <thead>
        <tr>
            <th>ID</th>
            <th>C&oacute;digo</th>
            <th>Fecha rendici&oacute;n</th>
            <th>Empresa</th>
            <th>Jornada</th>
            <th>Turno</th>
            <th>Terminal</th>
            <th class="num">Recaudaci&oacute;n</th>
            <th class="num">Dep&oacute;sito</th>
            <th>Anita sync</th>
            <th>Usuario</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rendiciones as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ $row->codigo }}</td>
                <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
                <td>{{ $row->empresa?->nombre }}</td>
                <td>{{ $row->fecha_jornada?->format('d/m/Y') ?? $row->jornada?->fecha_jornada?->format('d/m/Y') }}</td>
                <td>{{ $row->turnoOperativo?->turno?->nombre ?? '—' }}</td>
                <td>{{ $row->turnoOperativo?->identificador_pc ?? '—' }}</td>
                <td class="num">{{ $fmtNum($row->total_cartones) }}</td>
                <td class="num">{{ $fmtNum($row->deposito ?? $row->saldo_final) }}</td>
                <td>{{ $row->anita_sincronizado_en?->format('d/m/Y H:i') ?? 'Pendiente' }}</td>
                <td>{{ $row->creousuario?->nombre ?? '—' }}</td>
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
