<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Env&iacute;o {{ $venta->codigo ?? $venta->id }}</title>
    <style type="text/css">
        @page { margin: 18mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #17202A;
        }
        h1 { font-size: 18px; margin: 0 0 8px 0; }
        .aviso {
            border: 1px solid #F4D03F;
            background: #FCF3CF;
            padding: 10px 12px;
            margin: 12px 0 18px 0;
        }
        table.meta { width: 100%; border-collapse: collapse; }
        table.meta td { padding: 3px 0; vertical-align: top; }
        .lbl { width: 28%; color: #566573; }
    </style>
</head>
<body>
    <h1>Comprobante de ENV&Iacute;O (scaffold)</h1>
    <div class="aviso">
        Formato provisional. El layout definitivo se define el lunes con el modelo de Ferli.
        Hasta entonces este PDF solo identifica la venta para la secuencia de impresi&oacute;n.
    </div>
    <table class="meta">
        <tr>
            <td class="lbl">Factura</td>
            <td>{{ $venta->codigo ?? $venta->id }}</td>
        </tr>
        <tr>
            <td class="lbl">Fecha</td>
            <td>{{ optional($venta->fecha)->format('d/m/Y') ?? $venta->fecha }}</td>
        </tr>
        <tr>
            <td class="lbl">Cliente</td>
            <td>{{ $venta->nombre ?? optional($venta->clientes)->nombre }}</td>
        </tr>
        <tr>
            <td class="lbl">Domicilio</td>
            <td>{{ $venta->domicilio ?? optional($venta->clientes)->domicilio }}</td>
        </tr>
        <tr>
            <td class="lbl">Remito (nro. venta)</td>
            <td>{{ (int) ($venta->numeroremito ?? 0) > 0 ? (int) $venta->numeroremito : '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Remito ERP id</td>
            <td>{{ (int) ($venta->remito_id ?? 0) > 0 ? (int) $venta->remito_id : '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Transporte</td>
            <td>{{ optional($venta->transportes)->nombre ?? '—' }}</td>
        </tr>
    </table>
</body>
</html>
