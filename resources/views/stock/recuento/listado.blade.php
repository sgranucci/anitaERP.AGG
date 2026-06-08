<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Listado de recuentos</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #222; }
        h2 { font-size: 14px; margin: 0 0 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #444; padding: 4px 5px; text-align: left; vertical-align: top; }
        th { background: #e8e8e8; font-weight: bold; }
        tr:nth-child(even) { background: #f5f5f5; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <h2>Recuentos de inventario</h2>
    <p style="color:#555; margin-top:0;">Generado el {{ date('d/m/Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Código</th>
                <th>Fecha</th>
                <th>Depósito</th>
                <th>Empresa</th>
                <th>Usuario</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th class="num">Líneas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($recuentos as $r)
                <tr>
                    <td>{{ $r->id }}</td>
                    <td>{{ $r->codigo }}</td>
                    <td>{{ optional($r->fecha)->format('d/m/Y') }}</td>
                    <td>{{ optional($r->deposito)->nombre }}</td>
                    <td>{{ optional($r->empresa)->nombre }}</td>
                    <td>{{ optional($r->usuario)->nombre }}</td>
                    <td>{{ $r->tipo }}</td>
                    <td>{{ \App\Models\Stock\Recuento::etiquetaEstado($r->estado) }}</td>
                    <td class="num">{{ $r->items_count ?? $r->items->count() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
