@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Models\Sueldos\Solicitud_Prenda_Sueldos;
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $logoDefault = EmpresaLogoArchivo::dataUriDesdeNombre(config('app.empresa'));
    $total = is_countable($solicitudes) ? count($solicitudes) : 0;
    $estados = Solicitud_Prenda_Sueldos::ESTADOS;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Solicitudes de indumentaria</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data td, table.data th { border: 1px solid #cccccc; text-align: left; padding: 4px; vertical-align: top; word-wrap: break-word; }
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
                @if (! empty($logoDefault['uri']))
                    <img src="{{ $logoDefault['uri'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px; vertical-align: middle;">
                @endif
            </td>
            <td style="width: 40%; text-align: center;">
                <h2 style="margin: 0; font-size: 20px; font-weight: bold;">Solicitudes de indumentaria</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                @if ($total > 0) Solicitudes: {{ $total }} @endif
            </td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 9%;">Fecha</th>
                <th style="width: 8%;">Legajo</th>
                <th style="width: 22%;">Empleado</th>
                <th style="width: 14%;">Estado</th>
                <th style="width: 30%;">Prendas</th>
                <th style="width: 12%;">Solicitante</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($solicitudes as $s)
                <tr>
                    <td>{{ $s->id }}</td>
                    <td>{{ optional($s->fecha)->format('d/m/Y') }}</td>
                    <td>{{ optional($s->empleado)->legajo }}</td>
                    <td>{{ optional($s->empleado)->nombre }}</td>
                    <td>{{ $estados[$s->estado] ?? $s->estado }}@if($s->estado === Solicitud_Prenda_Sueldos::PENDIENTE) (Nv {{ $s->nivel_actual }})@endif</td>
                    <td>
                        @foreach ($s->articulos as $a)
                            {{ optional($a->prenda)->descripcion }} {{ optional($a->color)->nombre }} {{ optional($a->talle)->nombre }} × {{ $fmt($a->cantidad) }}@if(! $loop->last); @endif
                        @endforeach
                    </td>
                    <td>{{ optional($s->solicitante)->nombre }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
