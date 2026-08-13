@php
    $esExport = (bool) ($es_export ?? false);
    $paraPdf = (bool) ($para_pdf ?? $esExport);
    $puedeVer = ($puede_ver_ticket ?? false) && ! $esExport;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $colMinutos = ($modo_tiempo ?? 'ticket') === 'tecnico'
        ? 'Tiempo insumido técnico (min)'
        : 'Tiempo insumido ticket (min)';
@endphp
<table class="table table-striped table-bordered table-hover mb-0 @if($paraPdf) data @endif" id="{{ $esExport ? 'tabla-export' : 'tabla-paginada' }}">
    <thead style="background-color:#85C1E9;color:#17202A;">
        <tr>
            <th>ID</th>
            <th>Apertura</th>
            <th>Sala</th>
            <th>Sector</th>
            <th>Categor&iacute;a</th>
            <th>T&iacute;tulo</th>
            <th>Estado</th>
            <th>T&eacute;cnico</th>
            <th>Asignaci&oacute;n</th>
            <th>Tiempo hasta asignar</th>
            <th>Resoluci&oacute;n</th>
            <th>Tiempo hasta resolver</th>
            <th class="text-right">{{ $colMinutos }}</th>
            <th>Gener&oacute; usuario</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $f)
            <tr>
                <td>
                    @if ($puedeVer && (int) ($f['id'] ?? 0) > 0)
                        <a href="{{ route('edita_administracion_ticket', array_merge(['id' => $f['id']], $queryConsulta)) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $f['id'] }}
                        </a>
                    @else
                        {{ $f['id'] ?? '' }}
                    @endif
                </td>
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
                <td class="text-right">{{ $f['minutos_insumidos_fmt'] ?? '' }}</td>
                <td>{{ $f['usuario'] ?? '' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="14" class="text-center text-muted">Sin registros para los filtros indicados.</td>
            </tr>
        @endforelse
    </tbody>
</table>
