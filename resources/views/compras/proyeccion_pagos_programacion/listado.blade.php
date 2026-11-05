<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 3px; }
        table.data thead { display: table-header-group; }
        table.data thead tr.columnas > th { background: #85C1E9; color: #17202A; }
        table.data tbody tr:nth-child(even) { background: #f5f5f5; }
        .num { text-align: right; }
    </style>
</head>
<body>
<table class="data">
    <thead>
        @include('includes.reportes.pdf_thead_cabecera', [
            'titulo' => $titulo,
            'subtitulo' => $subtitulo ?? '',
            'colspan' => 14,
            'logosCabecera' => \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($filas)),
            'totalFilas' => count($filas),
        ])
        <tr class="columnas">
            <th>Empr.</th>
            <th>N.Pro.</th>
            <th>Nombre</th>
            <th>Tip</th>
            <th>Comprobante</th>
            <th>F.Vto.</th>
            <th>M.Pago</th>
            <th>Detalle</th>
            <th>Mon.</th>
            <th>Total</th>
            <th>Leyenda</th>
            <th>Concepto</th>
            <th>Urg.</th>
            <th>F.Pago</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                <td>{{ $fila['empresa'] }}</td>
                <td>{{ $fila['proveedor_codigo'] }}</td>
                <td>{{ $fila['proveedor_nombre'] }}</td>
                <td>{{ $fila['tipo'] }}</td>
                <td>{{ $fila['comprobante'] }}</td>
                <td>{{ $fila['fecha_vto'] }}</td>
                <td>{{ $fila['medio_pago'] }}</td>
                <td>{{ $fila['detalle_pago'] }}</td>
                <td>{{ $fila['moneda'] }}</td>
                <td class="num">{{ number_format((float) $fila['total_adeudado'], 2, ',', '.') }}</td>
                <td>{{ $fila['leyenda'] }}</td>
                <td>{{ $fila['detalle_concepto'] }}</td>
                <td>{{ ! empty($fila['urgente']) ? 'SI' : '' }}</td>
                <td>{{ $fila['fecha_pago'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
