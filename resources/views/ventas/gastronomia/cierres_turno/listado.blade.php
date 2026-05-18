@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Listado cierres de turno gastronomía</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data td, table.data th { border: 1px solid #ccc; padding: 4px; vertical-align: top; word-wrap: break-word; }
        table.data tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #d4e6f1; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .num { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 35%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px;">
                @endforeach
            </td>
            <td style="width: 65%; text-align: center;">
                <h2 style="margin:0;font-size:18px;">Cierres de turno gastronomía</h2>
                <div style="font-size:8px;color:#444;">
                    @if (!empty($filtros['fecha_desde'])) Desde {{ $filtros['fecha_desde'] }} @endif
                    @if (!empty($filtros['fecha_hasta'])) — Hasta {{ $filtros['fecha_hasta'] }} @endif
                    @if (!empty($filtros['identificador_pc'])) · PC: {{ $filtros['identificador_pc'] }} @endif
                    · Generado {{ date('d/m/Y H:i') }}
                </div>
            </td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Fecha / hora</th>
                <th>Referencia</th>
                <th>Empresa</th>
                <th>PC</th>
                <th>Turno</th>
                <th>Jornada</th>
                <th>Usuario</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $f)
            <tr>
                <td>{{ $f->tipo_etiqueta }}</td>
                <td>{{ $f->fecha_hora }}</td>
                <td>{{ $f->referencia }}</td>
                <td>{{ $f->nombreempresa }}</td>
                <td>{{ $f->identificador_pc }}</td>
                <td>{{ $f->turno_nombre }}</td>
                <td>{{ $f->fecha_jornada }}</td>
                <td>{{ $f->usuario }}</td>
                <td class="num">${{ number_format((float) $f->total, 2, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="9">Sin registros para los filtros indicados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
