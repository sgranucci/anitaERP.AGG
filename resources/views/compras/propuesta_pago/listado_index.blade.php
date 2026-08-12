<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #ccc; padding: 4px; }
        table.data td { border: 1px solid #ccc; padding: 3px; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
    </style>
</head>
<body>
@php use App\Support\Configuracion\EmpresaLogoArchivo; @endphp
@foreach(EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas) as $logo)
    @if (! empty($logo['uri']))
        <img src="{{ $logo['uri'] }}" height="40" style="margin-right:8px;">
    @endif
@endforeach
<h2>Propuestas de pagos</h2>
<div>Generado {{ now()->format('d/m/Y H:i') }} — {{ $datas->count() }} registros</div>
<table class="data">
    <thead>
        <tr>
            <th>ID</th><th>Fecha</th><th>Empresa</th><th>Estado</th><th>Monto</th><th>Autorizado</th><th>Detalle</th>
        </tr>
    </thead>
    <tbody>
        @foreach($datas as $fila)
            <tr>
                <td>{{ $fila->id }}</td>
                <td>{{ optional($fila->fecha)->format('d/m/Y') }}</td>
                <td>{{ $fila->empresas->nombre ?? '' }}</td>
                <td>{{ $fila->estado }}</td>
                <td style="text-align:right">{{ number_format((float)$fila->monto_total, 2, ',', '.') }}</td>
                <td style="text-align:right">{{ number_format((float)($fila->monto_autorizado ?: 0), 2, ',', '.') }}</td>
                <td>{{ $fila->detalle }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
