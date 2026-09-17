@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas ?? collect());
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 3px; }
        table.data td { border: 1px solid #cccccc; padding: 3px; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        .logos img { height: 36px; margin-right: 8px; }
    </style>
</head>
<body>
    <div class="logos">
        @foreach($logos as $logo)
            <img src="{{ $logo }}">
        @endforeach
    </div>
    <h2>Programas de pagos</h2>
    <p>Generado {{ date('d/m/Y H:i') }} · {{ count($datas) }} registros</p>
    <table class="data">
        <thead>
            <tr>
                <th>Id</th>
                <th>Título</th>
                <th>Empresa</th>
                <th>Fecha base</th>
                <th>Desde</th>
                <th>Meses</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($datas as $item)
                <tr>
                    <td>{{ $item->id }}</td>
                    <td>{{ $item->titulo }}</td>
                    <td>{{ $item->empresas->nombre ?? $item->nombreempresa ?? '' }}</td>
                    <td>{{ optional($item->fecha_base)->format('d/m/Y') }}</td>
                    <td>{{ $item->anio_mes_inicio }}</td>
                    <td>{{ $item->cantidad_meses }}</td>
                    <td>{{ $item->estado }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
