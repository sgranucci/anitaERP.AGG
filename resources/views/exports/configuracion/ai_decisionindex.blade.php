<table>
    <tr>
        <td colspan="10"><strong style="font-size: 16pt;">{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="10">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (trim($subtitulo ?? '') !== '')
        <tr>
            <td colspan="10">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <td colspan="10">
            Total {{ $kpis['total'] ?? 0 }}
            · Aceptación {{ $kpis['tasa_aceptacion'] !== null ? number_format($kpis['tasa_aceptacion'] * 100, 1).'%' : '—' }}
            · Pendientes {{ $kpis['pendientes'] ?? 0 }}
            · Errores {{ $kpis['errores'] ?? 0 }}
            · Score prom. {{ $kpis['score_promedio'] !== null ? number_format($kpis['score_promedio'], 2) : '—' }}
            · Latencia prom. {{ $kpis['latencia_promedio_ms'] !== null ? number_format($kpis['latencia_promedio_ms'], 0).' ms' : '—' }}
        </td>
    </tr>
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
            <th>Entidad ID</th>
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
                <td>{{ $fila->entidad_id }}</td>
                <td>{{ $fila->model }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
