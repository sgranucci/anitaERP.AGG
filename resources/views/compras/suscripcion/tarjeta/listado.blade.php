@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    foreach ($tarjetas as $row) {
        $row->nombreempresa = $row->empresas->nombre ?? '';
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($tarjetas);
    $totalFilas = is_countable($tarjetas) ? count($tarjetas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Tarjetas corporativas</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 7px;
            font-weight: bold;
            color: #17202A;
        }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        .num { text-align: right; }
        .cen { text-align: center; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 35%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px; margin-bottom: 4px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 40%; text-align: center;">
                <h2 style="margin: 0; font-size: 18px; font-weight: bold;">Tarjetas corporativas</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                @if ($totalFilas > 0)
                    Registros: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 14%;">Etiqueta</th>
                <th style="width: 7%;">Últ. 4</th>
                <th style="width: 10%;">Emisor</th>
                <th style="width: 12%;">Empresa</th>
                <th style="width: 14%;">Área / CC</th>
                <th style="width: 12%;">Responsable</th>
                <th style="width: 8%;">Imputación</th>
                <th style="width: 7%;" class="cen">Suscrip.</th>
                <th style="width: 7%;">Estado</th>
                <th style="width: 9%;">Observación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tarjetas as $t)
                <tr>
                    <td>{{ $t->etiqueta }}</td>
                    <td>••{{ $t->ult4 }}</td>
                    <td>{{ $t->emisor ?: '—' }}</td>
                    <td>{{ optional($t->empresas)->nombre }}</td>
                    <td>
                        {{ $t->area ?: '—' }}
                        @if ($t->centrocostos)
                            <br>{{ trim(($t->centrocostos->codigo ?? '').' '.($t->centrocostos->nombre ?? '')) }}
                        @endif
                    </td>
                    <td>{{ optional($t->responsables)->nombre ?: '—' }}</td>
                    <td>{{ $t->imputable() ? 'Lista' : 'Incompleta' }}</td>
                    <td class="cen">{{ $usos[$t->id] ?? 0 }}</td>
                    <td>{{ $t->activo ? 'Activa' : 'Inactiva' }}</td>
                    <td>{{ $t->observacion ?: '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align:center;color:#667;">Sin tarjetas</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
