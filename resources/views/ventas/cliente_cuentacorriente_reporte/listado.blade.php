@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $coleccionLogos = collect($filas ?? [])->map(fn ($f) => ['nombreempresa' => $f['nombreempresa'] ?? '']);
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogos);
    $totalClientes = (int) (($resultado['stats']['clientes'] ?? 0));
    $totalVendedores = (int) (($resultado['stats']['vendedores'] ?? 0));
    $tituloReporte = $titulo ?? 'Cuenta corriente clientes';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; line-height: 1.35; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
            font-size: 7px;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data tbody tr.cc-rep-header { background-color: #d6eaf8; font-weight: bold; }
        table.data tbody tr.cc-rep-header-vendedor {
            background-color: #1b4f72;
            color: #ffffff;
            font-weight: bold;
        }
        table.data tbody tr.cc-rep-total {
            background-color: #f9e79f;
            font-weight: bold;
            color: #1b4f72;
        }
        table.data tbody tr.cc-rep-total-vendedor {
            background-color: #f5b041;
            font-weight: bold;
            color: #1b4f72;
        }
        table.data tbody tr.cc-rep-total td,
        table.data tbody tr.cc-rep-total-vendedor td {
            border-top: 2px solid #b7950b;
            font-size: 8px;
        }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-size: 7px; font-weight: bold; color: #17202A; }
        .text-right { text-align: right; white-space: nowrap; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 32%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 160px; margin-right: 8px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 46%; text-align: center;">
                <h2 style="margin: 0; font-size: 16px; font-weight: bold;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $subtitulo ?? '' }}</div>
            </td>
            <td style="width: 22%; text-align: right; font-size: 8px;">
                @if ($totalVendedores > 0)
                    Vendedores: {{ $totalVendedores }}<br>
                @endif
                @if ($totalClientes > 0)
                    Clientes: {{ $totalClientes }}
                @endif
            </td>
        </tr>
    </table>

    <table class="data">
        @include('ventas.cliente_cuentacorriente_reporte.partials.tabla_datos', [
            'filas' => $filas ?? [],
            'filtros' => $filtros ?? [],
            'mostrarLinks' => false,
            'para_pdf' => true,
            'puede_ver_cliente' => false,
            'puede_ver_factura' => false,
            'puede_ver_cobranza' => false,
        ])
    </table>
</body>
</html>
