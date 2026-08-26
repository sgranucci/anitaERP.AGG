<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Legajos de compras' }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; }
        h2 { font-size: 14px; margin: 0 0 4px 0; }
        .meta { font-size: 8px; color: #444; margin-bottom: 8px; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #ccc; padding: 3px 4px; vertical-align: top; }
        table.data thead tr { background-color: #d4e6f1; }
        table.data th { font-weight: bold; }
        table.data tr:nth-child(even) { background-color: #f5f5f5; }
    </style>
</head>
<body>
    <h2>{{ $titulo ?? 'Legajos de compras' }}</h2>
    <div class="meta">Generado {{ date('d/m/Y H:i') }}@if (!empty($subtitulo)) — {{ $subtitulo }}@endif</div>
    <table class="data">
        <thead>
            <tr>
                <th>OC</th>
                <th>Fecha</th>
                <th>Empresa</th>
                <th>Proveedor</th>
                <th>Centro de costo</th>
                <th>Sector</th>
                <th>Días</th>
                <th>Paquete</th>
                <th>Decisión</th>
                <th>Firmante</th>
                <th>Fecha dec.</th>
                <th>Comentario</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $row)
                <tr>
                    <td>{{ $row['numero'] ?? '' }}</td>
                    <td>{{ $row['fecha'] ?? '' }}</td>
                    <td>{{ $row['empresa'] ?? '' }}</td>
                    <td>{{ $row['proveedor'] ?? '' }}</td>
                    <td>{{ $row['centrocosto'] ?? '' }}</td>
                    <td>{{ $row['sector'] ?? '' }}</td>
                    <td>{{ $row['dias'] ?? '' }}</td>
                    <td>{{ !empty($row['paquete_ok']) ? 'FC+COM' : ((!empty($row['tiene_factura']) ? 'FC ' : '').(!empty($row['tiene_com']) ? 'COM' : '')) }}</td>
                    <td>{{ $row['decision'] ?? '' }}</td>
                    <td>{{ $row['firmante'] ?? '' }}</td>
                    <td>{{ $row['fecha_decision'] ?? '' }}</td>
                    <td>{{ $row['comentario_decision'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
