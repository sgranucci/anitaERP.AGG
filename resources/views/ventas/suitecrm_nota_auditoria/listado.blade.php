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
            size: A4 landscape;
            margin: 6mm 5mm;
        }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
            margin-bottom: 10px;
            font-size: 10px;
        }
        table.data td, table.data th {
            border: none;
            border-bottom: 0.6pt solid #b0b0b0;
            text-align: left;
            padding: 3px 2px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.data thead th {
            background-color: #85C1E9;
            font-size: 9px;
            font-weight: bold;
            color: #17202A;
            border-bottom: 1pt solid #2471A3;
        }
        .fecha-grupo {
            font-size: 11px;
            font-weight: bold;
            margin: 10px 0 3px 0;
            color: #17202A;
            border-bottom: 1px solid #85C1E9;
            padding-bottom: 2px;
        }
        .listado-header { width: 100%; margin-bottom: 6px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #666; margin-top: 2px; }
        .subtitulo-rango { font-size: 11px; font-weight: bold; color: #333; margin-top: 4px; }
        .nota-celda { white-space: pre-wrap; font-size: 10px; }
        .leyenda-tipo { font-size: 8px; color: #555; margin: 0 0 5px 0; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 22%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 40px; max-width: 120px; margin-right: 6px; margin-bottom: 2px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 56%; text-align: center;">
                <h2 style="margin: 0; font-size: 14px; font-weight: bold;">{{ $tituloReporte }}</h2>
                @if (! empty($subtitulo))
                    <div class="subtitulo-rango">{{ $subtitulo }}</div>
                @endif
            </td>
            <td style="width: 22%; text-align: right; font-size: 8px; color: #666;">
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
                <col style="width: 8%;">
                <col style="width: 12%;">
                <col style="width: 4%;">
                <col style="width: 5%;">
                <col style="width: 11%;">
                <col style="width: 60%;">
            </colgroup>
            <thead>
                <tr>
                    <th style="width: 8%;">Vendedor</th>
                    <th style="width: 12%;">Empresa</th>
                    <th style="width: 4%;">Tipo</th>
                    <th style="width: 5%;">Cód.</th>
                    <th style="width: 11%;">Asunto</th>
                    <th style="width: 60%;">Nota</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filasDia as $fila)
                    <tr>
                        <td style="width: 8%;">{{ $fila['vendedor'] ?? '' }}</td>
                        <td style="width: 12%;">{{ $fila['relacionado'] ?? '' }}</td>
                        <td style="width: 4%;">{{ $fila['tipo'] ?? '' }}</td>
                        <td style="width: 5%;">{{ $fila['codigo_anita'] ?? '' }}</td>
                        <td style="width: 11%;">{{ $fila['asunto'] ?? '' }}</td>
                        <td style="width: 60%;" class="nota-celda">{{ $fila['nota'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p>Sin notas para los filtros indicados.</p>
    @endforelse
</body>
</html>
