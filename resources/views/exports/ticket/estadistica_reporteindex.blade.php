@php
    $colspan = 14;
    $tot = $totales ?? [];
    $colMinutos = ($modo_tiempo ?? 'ticket') === 'tecnico'
        ? 'Tiempo insumido técnico (min)'
        : 'Tiempo insumido ticket (min)';
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}">
                <strong style="font-size: 16pt;">{{ $titulo ?? 'Informe estadístico de tickets' }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        @if (! empty($subtitulo))
            <tr>
                <td colspan="{{ $colspan }}">{{ $subtitulo }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="{{ $colspan }}">
                {{ (int) ($tot['cantidad'] ?? 0) }} tickets
                · Insumido {{ $tot['suma_insumido_fmt'] ?? '0' }} min (prom. {{ $tot['promedio_insumido_fmt'] ?? '0' }})
                · Asignar prom. {{ ($tot['promedio_asignacion_fmt'] ?? '') !== '' ? $tot['promedio_asignacion_fmt'] : '—' }}
                · Resolver prom. {{ ($tot['promedio_resolucion_fmt'] ?? '') !== '' ? $tot['promedio_resolucion_fmt'] : '—' }}
            </td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>ID</th>
            <th>Apertura</th>
            <th>Sala</th>
            <th>Sector</th>
            <th>Categoría</th>
            <th>Título</th>
            <th>Estado</th>
            <th>Técnico</th>
            <th>Asignación</th>
            <th>Tiempo hasta asignar</th>
            <th>Resolución</th>
            <th>Tiempo hasta resolver</th>
            <th>{{ $colMinutos }}</th>
            <th>Generó usuario</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $f)
            <tr>
                <td>{{ $f['id'] ?? '' }}</td>
                <td>{{ $f['apertura'] ?? '' }}</td>
                <td>{{ $f['sala'] ?? '' }}</td>
                <td>{{ $f['sector'] ?? '' }}</td>
                <td>{{ $f['categoria'] ?? '' }}</td>
                <td>{{ $f['titulo'] ?? '' }}</td>
                <td>{{ $f['estado'] ?? '' }}</td>
                <td>{{ $f['tecnicos'] ?? '' }}</td>
                <td>{{ $f['asignacion'] ?? '' }}</td>
                <td>{{ $f['minutos_asignacion_fmt'] ?? '' }}</td>
                <td>{{ $f['resolucion'] ?? '' }}</td>
                <td>{{ $f['minutos_resolucion_fmt'] ?? '' }}</td>
                <td>{{ $f['minutos_insumidos_fmt'] ?? '' }}</td>
                <td>{{ $f['usuario'] ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
