@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect());
    $tot = $totales ?? [];
    $tituloReporte = $titulo ?? 'Informe estadístico de tickets — Área Tecnología';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 3px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 6px;
            font-weight: bold;
            color: #17202A;
        }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 7px; color: #444; margin-top: 3px; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 48px; max-width: 140px; margin-right: 6px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 50%; text-align: center;">
                <h2 style="margin: 0; font-size: 14px; font-weight: bold;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (! empty($subtitulo))
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
            </td>
            <td style="width: 22%; text-align: right; font-size: 7px;">
                {{ (int) ($tot['cantidad'] ?? 0) }} tickets
                · {{ $tot['suma_insumido_fmt'] ?? '0' }} min
            </td>
        </tr>
    </table>
    <div class="meta" style="margin-bottom: 6px;">
        Asignar prom. {{ ($tot['promedio_asignacion_fmt'] ?? '') !== '' ? $tot['promedio_asignacion_fmt'] : '—' }}
        · Resolver prom. {{ ($tot['promedio_resolucion_fmt'] ?? '') !== '' ? $tot['promedio_resolucion_fmt'] : '—' }}
    </div>
    @include('ticket.estadistica_reporte.partials.tabla_datos', [
        'filas' => $filas,
        'es_export' => true,
        'para_pdf' => true,
        'modo_tiempo' => $modo_tiempo ?? 'ticket',
        'puede_ver_ticket' => false,
    ])
</body>
</html>
