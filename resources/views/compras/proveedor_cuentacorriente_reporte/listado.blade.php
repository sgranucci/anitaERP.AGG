@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $coleccionLogos = collect($filas ?? [])->map(fn ($f) => ['nombreempresa' => $f['nombreempresa'] ?? '']);
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogos);
    $totalProveedores = (int) (($resultado['stats']['proveedores'] ?? 0));
    $tituloReporte = $titulo ?? 'Cuenta corriente proveedores';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        @page { margin: 5mm 6mm 6mm 6mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 8px;
            color: #1a1a1a;
            line-height: 1.15;
            margin: 0;
            padding: 0;
        }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 2px 3px;
            vertical-align: middle;
            font-size: 8px;
            overflow: hidden;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data tbody tr.cc-rep-header { background-color: #d6eaf8; font-weight: bold; }
        table.data tbody tr.cc-rep-header-empresa {
            background-color: #1b4f72;
            color: #ffffff;
            font-weight: bold;
        }
        table.data tbody tr.cc-rep-total {
            background-color: #f9e79f;
            font-weight: bold;
            color: #1b4f72;
        }
        table.data tbody tr.cc-rep-total td {
            border-top: 2px solid #b7950b;
            font-size: 8px;
        }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-size: 7.5px; font-weight: bold; color: #17202A; }
        .text-right { text-align: right; white-space: nowrap; }
        .col-nowrap { white-space: nowrap; }
        .col-texto { white-space: nowrap; overflow: hidden; }
        .listado-header {
            width: 100%;
            margin: 0 0 4px 0;
            border-bottom: 1px solid #333;
            padding-bottom: 2px;
        }
        .listado-header td { vertical-align: top; border: none; padding: 0; }
        .meta { font-size: 8px; color: #444; margin-top: 1px; line-height: 1.25; }
        h2.titulo-reporte { margin: 0; padding: 0; font-size: 13px; font-weight: bold; line-height: 1.15; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 34px; max-width: 130px; margin-right: 6px; vertical-align: top;">
                @endforeach
            </td>
            <td style="width: 50%; text-align: center;">
                <h2 class="titulo-reporte">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $subtitulo ?? '' }}</div>
            </td>
            <td style="width: 22%; text-align: right; font-size: 8px;">
                @if ($totalProveedores > 0)
                    Proveedores: {{ $totalProveedores }}
                @endif
            </td>
        </tr>
    </table>

    <table class="data">
        @include('compras.proveedor_cuentacorriente_reporte.partials.tabla_datos', [
            'filas' => $filas ?? [],
            'filtros' => $filtros ?? [],
            'mostrarLinks' => false,
            'para_pdf' => true,
            'puede_ver_proveedor' => false,
            'puede_ver_comprobante' => false,
            'puede_ver_pago' => false,
        ])
    </table>
</body>
</html>
