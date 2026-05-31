<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Clientes</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9px; color: #1a1a1a; }
        table.data {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; }
        h2 { margin: 0 0 8px; font-size: 16px; }
    </style>
</head>
<body>
    <h2>Listado de clientes</h2>
    <div style="font-size: 8px; margin-bottom: 8px;">Generado {{ date('d/m/Y H:i') }}</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 6%;">ID</th>
                <th style="width: 22%;">Nombre</th>
                <th style="width: 12%;">C.U.I.T.</th>
                <th style="width: 22%;">Domicilio</th>
                <th style="width: 12%;">Localidad</th>
                <th style="width: 12%;">Provincia</th>
                <th style="width: 8%;">C&oacute;d.</th>
                @if (config('app.empresa') == 'EL BIERZO')
                    <th style="width: 12%;">Reparto</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($clientes as $data)
                <tr>
                    <td>{{ $data->id }}</td>
                    <td>{{ $data->nombre }}</td>
                    <td>{{ $data->numerodocumento }}</td>
                    <td>{{ $data->domicilio }}</td>
                    <td>{{ $data->nombrelocalidad ?? '' }}</td>
                    <td>{{ $data->nombreprovincia ?? '' }}</td>
                    <td>{{ $data->codigo }}</td>
                    @if (config('app.empresa') == 'EL BIERZO')
                        <td>{{ $data->ctransporte }}-{{ $data->nombretransporte }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
