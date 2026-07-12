<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h2 { font-size: 13px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 4px; }
        table.data th { background: #85C1E9; }
    </style>
</head>
<body>
    <h2>Cierre parcial turno bingo #{{ $d['numero_parcial'] }}</h2>
    <p>{{ $d['empresa'] }} · {{ $d['turno'] }} · Jornada {{ $d['fecha_jornada'] }}</p>
    <p>PC {{ $d['identificador_pc'] }} · {{ $d['usuario_habilitado'] }}</p>
    <table class="data">
        <tr><th>Total rendición turno</th><td>${{ number_format($d['total_rendicion_turno'], 2, ',', '.') }}</td></tr>
    </table>
    <p style="font-size:8px;">Generado {{ $d['generado'] }}</p>
</body>
</html>
