@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $rendiciones = $rendiciones ?? collect();
    $colspan = 12;

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
    <title>Cierre rend. vending</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 3px 4px; vertical-align: top; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        .num { text-align: right; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { border: none; vertical-align: middle; }
        .meta { font-size: 7px; color: #444; margin-top: 2px; }
    </style>
</head>
<body>
@if ($esExcel)
    {{-- Una sola tabla: logo + título + subtítulo + cabecera + datos (evita filas vacías entre tablas) --}}
    <table class="data">
        @if ($reservarFilaLogoExcel)
            <tbody>
                <tr><td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td></tr>
            </tbody>
        @endif
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">Cierre rendiciones vending (Contable)</strong></td>
            </tr>
            <tr>
                <td colspan="{{ $colspan }}"><strong>Generado {{ date('d/m/Y H:i') }} — {{ $subtitulo }}</strong></td>
            </tr>
        </tbody>
        <thead>
            <tr>
                <th>ID</th>
                <th>Ticket</th>
                <th>Fecha rend.</th>
                <th>Empresa</th>
                <th>PV</th>
                <th>M&aacute;quina</th>
                <th>Jornada</th>
                <th>Estado cierre</th>
                <th>Asiento</th>
                <th class="num">Ventas</th>
                <th class="num">Invitaciones</th>
                <th class="num">Cobrado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rendiciones as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->codigo }}</td>
                    <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
                    <td>{{ $row->empresa?->nombre }}</td>
                    <td>{{ $row->puntoventaCae?->codigo ?? $row->puntoventaCaea?->codigo ?? '—' }}</td>
                    <td>{{ $row->maquinavending?->nombre ?? '—' }}</td>
                    <td>{{ $row->maquinavendingRendicion?->fecha_jornada?->format('d/m/Y') ?? $row->fecharendicion?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $row->tieneCierreContable() ? ($row->esCierreContableLegacy() ? 'Cerrada (hist.)' : 'Cerrada') : 'Pendiente' }}</td>
                    <td>{{ $row->asiento?->numeroasiento ?? '—' }}</td>
                    <td class="num">{{ $fmtNum($row->totalfactura) }}</td>
                    <td class="num">{{ $fmtNum($row->totalinvitacion) }}</td>
                    <td class="num">{{ $fmtNum($row->totalcobrado) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" style="text-align:center;">Sin registros</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@else
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 150px; margin-right: 8px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 47%; text-align: center;">
                <h2 style="margin: 0; font-size: 13px; font-weight: bold;">Cierre rendiciones vending (Contable)</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $subtitulo }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 7px;">
                Registros: {{ is_countable($rendiciones) ? count($rendiciones) : 0 }}
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Ticket</th>
                <th>Fecha rend.</th>
                <th>Empresa</th>
                <th>PV</th>
                <th>M&aacute;quina</th>
                <th>Jornada</th>
                <th>Estado cierre</th>
                <th>Asiento</th>
                <th class="num">Ventas</th>
                <th class="num">Invitaciones</th>
                <th class="num">Cobrado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rendiciones as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->codigo }}</td>
                    <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
                    <td>{{ $row->empresa?->nombre }}</td>
                    <td>{{ $row->puntoventaCae?->codigo ?? $row->puntoventaCaea?->codigo ?? '—' }}</td>
                    <td>{{ $row->maquinavending?->nombre ?? '—' }}</td>
                    <td>{{ $row->maquinavendingRendicion?->fecha_jornada?->format('d/m/Y') ?? $row->fecharendicion?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $row->tieneCierreContable() ? ($row->esCierreContableLegacy() ? 'Cerrada (hist.)' : 'Cerrada') : 'Pendiente' }}</td>
                    <td>{{ $row->asiento?->numeroasiento ?? '—' }}</td>
                    <td class="num">{{ $fmtNum($row->totalfactura) }}</td>
                    <td class="num">{{ $fmtNum($row->totalinvitacion) }}</td>
                    <td class="num">{{ $fmtNum($row->totalcobrado) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" style="text-align:center;">Sin registros</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endif
</body>
</html>
