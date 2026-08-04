@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas ?? []);
    $tituloReporte = $titulo ?? 'Auditoría de notas CRM';
    $agrupadas = $agrupadas ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        @page {
            margin: 8mm 6mm;
        }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
            margin-bottom: 10px;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 7.5px;
            font-weight: bold;
            color: #17202A;
        }
        .fecha-grupo {
            font-size: 11px;
            font-weight: bold;
            margin: 12px 0 4px 0;
            color: #17202A;
            border-bottom: 1px solid #85C1E9;
            padding-bottom: 2px;
        }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 7.5px; color: #444; margin-top: 3px; }
        .nota-celda { white-space: pre-wrap; font-size: 7.5px; }
        .leyenda-tipo { font-size: 7px; color: #555; margin: 0 0 6px 0; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 160px; margin-right: 8px; margin-bottom: 4px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 50%; text-align: center;">
                <h2 style="margin: 0; font-size: 15px; font-weight: bold;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (! empty($subtitulo))
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
            </td>
            <td style="width: 22%; text-align: right; font-size: 8px;">
                @if (! empty($totalFilas))
                    Registros: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>

    <p class="leyenda-tipo">Tipo: Cta = Cuenta · CP = Cliente potencial · Cont = Contacto</p>

    @forelse ($agrupadas as $fechaYmd => $filasDia)
        <div class="fecha-grupo">
            {{ ! empty($filasDia[0]['fecha_display']) ? $filasDia[0]['fecha_display'] : $fechaYmd }}
        </div>
        <table class="data">
            <colgroup>
                <col style="width: 9%;">
                <col style="width: 14%;">
                <col style="width: 4%;">
                <col style="width: 5%;">
                <col style="width: 13%;">
                <col style="width: 55%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Vendedor</th>
                    <th>Empresa</th>
                    <th>Tipo</th>
                    <th>Cód.</th>
                    <th>Asunto</th>
                    <th>Nota</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filasDia as $fila)
                    <tr>
                        <td>{{ $fila['vendedor'] ?? '' }}</td>
                        <td>{{ $fila['relacionado'] ?? '' }}</td>
                        <td>{{ $fila['tipo'] ?? '' }}</td>
                        <td>{{ $fila['codigo_anita'] ?? '' }}</td>
                        <td>{{ $fila['asunto'] ?? '' }}</td>
                        <td class="nota-celda">{{ $fila['nota'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p>Sin notas para los filtros indicados.</p>
    @endforelse
</body>
</html>
