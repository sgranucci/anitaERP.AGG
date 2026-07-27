<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        h1 { font-size: 14px; margin: 0 0 4px 0; }
        .meta { margin-bottom: 8px; color: #444; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 3px 4px; text-align: left; }
        table.data td { border: 1px solid #cccccc; padding: 3px 4px; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
        .kpis { margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="meta">
        Generado {{ date('d/m/Y H:i') }} · {{ $subtitulo }} · {{ $totalFilas }} registro(s)
    </div>
    <div class="kpis">
        Total {{ $kpis['total'] ?? 0 }}
        · Aceptación {{ isset($kpis['tasa_aceptacion']) && $kpis['tasa_aceptacion'] !== null ? number_format($kpis['tasa_aceptacion'] * 100, 1).'%' : '—' }}
        · Pendientes {{ $kpis['pendientes'] ?? 0 }}
        · Errores {{ $kpis['errores'] ?? 0 }}
        · Score prom. {{ isset($kpis['score_promedio']) && $kpis['score_promedio'] !== null ? number_format($kpis['score_promedio'], 2) : '—' }}
        · Latencia prom. {{ isset($kpis['latencia_promedio_ms']) && $kpis['latencia_promedio_ms'] !== null ? number_format($kpis['latencia_promedio_ms'], 0).' ms' : '—' }}
    </div>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Skill</th>
                <th>Acción</th>
                <th>Score</th>
                <th>Latencia</th>
                <th>Usuario</th>
                <th>Resuelto por</th>
                <th>Entidad</th>
                <th>Modelo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    <td>{{ $fila->id }}</td>
                    <td>{{ optional($fila->created_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ \App\Support\Configuracion\AiDecisionListadoFiltros::etiquetaSkill($fila->skill) }}</td>
                    <td>{{ \App\Support\Configuracion\AiDecisionListadoFiltros::etiquetaAccion($fila->accion) }}</td>
                    <td>{{ $fila->score !== null ? number_format((float) $fila->score, 2) : '' }}</td>
                    <td>{{ $fila->latencia_ms !== null ? $fila->latencia_ms.' ms' : '' }}</td>
                    <td>{{ $fila->usuario->nombre ?? '' }}</td>
                    <td>{{ $fila->resolutor->nombre ?? '' }}</td>
                    <td>{{ $fila->entidad_id ? ($fila->entidad_tipo.' #'.$fila->entidad_id) : '' }}</td>
                    <td>{{ $fila->model ?: $fila->driver }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
