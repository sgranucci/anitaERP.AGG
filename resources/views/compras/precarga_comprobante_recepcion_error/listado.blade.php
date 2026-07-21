<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        h1 { font-size: 14px; margin: 0 0 4px 0; }
        .meta { margin-bottom: 8px; color: #444; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            background: #85C1E9;
            color: #17202A;
            border: 1px solid #cccccc;
            padding: 3px 4px;
            text-align: left;
        }
        table.data td {
            border: 1px solid #cccccc;
            padding: 3px 4px;
            vertical-align: top;
        }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>Errores de recepción precarga (API / PDF+IA)</h1>
    <div class="meta">
        Generado {{ date('d/m/Y H:i') }} — {{ $datas->count() }} registro(s)
    </div>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Origen</th>
                <th>Fase</th>
                <th>Nº OC</th>
                <th>CUIT prov.</th>
                <th>HTTP</th>
                <th>Mensaje</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($datas as $data)
                <tr>
                    <td>{{ $data->id }}</td>
                    <td>{{ optional($data->created_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ \App\Support\Compras\PrecargaRecepcionErrorRegistrar::etiquetaOrigen($data->origen) }}</td>
                    <td>{{ $data->fase }}</td>
                    <td>{{ $data->numero_oc }}</td>
                    <td>{{ $data->cuit_proveedor }}</td>
                    <td>{{ $data->http_status }}</td>
                    <td>{{ $data->mensaje }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
