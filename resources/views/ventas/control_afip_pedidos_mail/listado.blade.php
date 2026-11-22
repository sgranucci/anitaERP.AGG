@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $filas = $filas ?? [];
    $informe = $informe ?? [];
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect());
    $totalFilas = is_countable($filas) ? count($filas) : 0;
    $titulo = 'Clientes con problemas de AFIP';
    $desde = isset($informe['fecha_desde']) ? \Carbon\Carbon::parse($informe['fecha_desde'])->format('d/m/Y') : '';
    $hasta = isset($informe['fecha_hasta']) ? \Carbon\Carbon::parse($informe['fecha_hasta'])->format('d/m/Y') : '';
    $control = isset($informe['fecha_control']) ? \Carbon\Carbon::parse($informe['fecha_control'])->format('d/m/Y') : '';
    $subtitulo = 'Control '.$control.' · Pedidos entrega '.$desde.' → '.$hasta
        .' · Problemas: '.$totalFilas
        .' · Fuente: ERP+Anita + padrón ARCA';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; line-height: 1.3; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data thead { display: table-header-group; }
        table.data td, table.data th {
            border: 1px solid #cccccc; text-align: left; padding: 3px 4px; vertical-align: top; word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr.columnas > th { background-color: #85C1E9; font-size: 8px; font-weight: bold; color: #17202A; }
    </style>
</head>
<body>
    <table class="data">
        <thead>
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
                'colspan' => 8,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => $totalFilas,
            ])
            <tr class="columnas">
                <th style="width:8%;">Código</th>
                <th style="width:22%;">Cliente</th>
                <th style="width:12%;">CUIT</th>
                <th style="width:12%;">Cond. IVA</th>
                <th style="width:16%;">Vendedor</th>
                <th style="width:10%;">Últ. FAC</th>
                <th style="width:8%;">Fuente</th>
                <th style="width:12%;">Motivo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    <td>{{ $fila['codigo'] ?? '' }}</td>
                    <td>{{ $fila['nombre'] ?? '' }}</td>
                    <td>{{ $fila['cuit'] ?? '' }}</td>
                    <td>{{ $fila['condicioniva'] ?? '' }}</td>
                    <td>{{ trim(($fila['vendedor_codigo'] ?? '').' '.($fila['vendedor_nombre'] ?? '')) }}</td>
                    <td>{{ isset($fila['ultima_fac']) ? \Carbon\Carbon::parse($fila['ultima_fac'])->format('d/m/Y') : '' }}</td>
                    <td>{{ $fila['fuente'] ?? '' }}</td>
                    <td>{{ $fila['motivo'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">Sin clientes con problemas de AFIP en el período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if (! ($informe['anita_ok'] ?? true))
        <p style="margin-top:8px;color:#c0392b;">Lectura Anita: {{ $informe['anita_error'] ?? '' }}</p>
    @endif
</body>
</html>
