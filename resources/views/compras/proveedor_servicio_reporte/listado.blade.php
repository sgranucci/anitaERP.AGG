@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $coleccionLogos = collect($filas ?? [])
        ->map(fn ($f) => (object) ['nombreempresa' => $f['nombreempresa'] ?? ''])
        ->filter(fn ($o) => trim((string) $o->nombreempresa) !== '');
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogos);
    $totalFilas = count($filas ?? []);
    $subtituloPdf = trim(
        ($subtitulo ?? '')
        .(! empty($totales)
            ? (($subtitulo ?? '') !== '' ? ' · ' : '')
                .'Proveedores: '.(int) ($totales['proveedores'] ?? 0)
                .' · Clientes / medidores: '.(int) ($totales['renglones'] ?? 0)
            : '')
    );
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo ?? 'Proveedores con clientes de servicios' }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; line-height: 1.25; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data thead { display: table-header-group; }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 2px 3px;
            vertical-align: top;
            word-wrap: break-word;
            font-size: 8px;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr.columnas > th { background-color: #85C1E9; font-weight: bold; color: #17202A; }
    </style>
</head>
<body>
    <table class="data">
        @include('compras.proveedor_servicio_reporte.partials.tabla_datos', [
            'filas' => $filas ?? [],
            'para_pdf' => true,
            'puede_ver_proveedor' => false,
            'titulo' => $titulo ?? 'Proveedores con clientes de servicios',
            'subtitulo' => $subtituloPdf,
            'logosCabecera' => $logosCabecera,
            'totalFilas' => $totalFilas,
        ])
    </table>
</body>
</html>
