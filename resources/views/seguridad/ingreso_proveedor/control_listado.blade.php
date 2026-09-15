@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Seguridad\IngresoProveedorEstados;
    $tickets = collect($filas ?? [])->map(function ($p) {
        return (object) ['nombreempresa' => $p['empresa'] ?? ''];
    });
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($tickets);
    $totalFilas = is_countable($filas) ? count($filas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Control de ingreso</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; text-align: left; padding: 3px; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-size: 7px; font-weight: bold; color: #17202A; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 35%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px; margin-bottom: 4px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 40%; text-align: center;">
                <strong style="font-size: 14px;">Control de ingreso — Movimientos del día</strong>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $totalFilas }} registro(s)</div>
            </td>
            <td style="width: 25%;"></td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th>Ticket</th>
                <th>Empresa</th>
                <th>DNI</th>
                <th>Persona</th>
                <th>Proveedor</th>
                <th>Motivo</th>
                <th>Punto</th>
                <th>Estado</th>
                <th>Entró</th>
                <th>Salió</th>
                <th>Min</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $p)
                <tr>
                    <td>{{ $p['ticket_id'] ?? '' }}</td>
                    <td>{{ $p['empresa'] ?? '' }}</td>
                    <td>{{ $p['documento'] ?? '' }}</td>
                    <td>{{ $p['nombre'] ?? '' }}</td>
                    <td>{{ $p['proveedor'] ?? '' }}</td>
                    <td>{{ $p['motivo'] ?? '' }}</td>
                    <td>{{ $p['punto'] ?? '' }}</td>
                    <td>{{ $p['estado'] ?? '' }}</td>
                    <td>{{ $p['hora_ingreso'] ?? '' }}</td>
                    <td>{{ $p['hora_egreso'] ?? '' }}</td>
                    <td>{{ $p['minutos_en_planta'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
