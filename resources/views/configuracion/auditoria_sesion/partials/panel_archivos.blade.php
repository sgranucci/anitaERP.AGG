<form method="get" action="{{ route('auditoria_sesion') }}" class="mb-3">
    <input type="hidden" name="consultar" value="1">
    <input type="hidden" name="pestana" value="archivos">
    <input type="hidden" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
    <input type="hidden" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
    <div class="form-row align-items-end">
        <div class="form-group col-md-6">
            <label for="archivo_log">Archivo en storage/logs</label>
            <select class="form-control" id="archivo_log" name="archivo_log">
                <option value="">— Seleccionar —</option>
                @foreach ($archivosLog as $arch)
                    <option value="{{ $arch['nombre'] }}" @selected(($filtros['archivo_log'] ?? '') === $arch['nombre'])>
                        {{ $arch['nombre'] }} — {{ $arch['humano'] }} — {{ $arch['mtime'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-2">
            <label for="lineas_log">Últimas líneas</label>
            <select class="form-control" id="lineas_log" name="lineas_log">
                @foreach ([100, 200, 500, 1000, 2000] as $n)
                    <option value="{{ $n }}" @selected((int) ($filtros['lineas_log'] ?? 200) === $n)>{{ $n }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-2">
            <button type="submit" class="btn btn-primary btn-block">
                <i class="fa fa-search"></i> Ver log
            </button>
        </div>
    </div>
</form>

@if (($archivosLog ?? []) === [])
    <div class="alert alert-warning mb-0">No se encontraron archivos <code>.log</code> en storage/logs.</div>
@else
    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered auditoria-tabla mb-0">
            <thead>
                <tr>
                    <th>Archivo</th>
                    <th class="text-right">Tamaño</th>
                    <th>Última modificación</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($archivosLog as $arch)
                    <tr class="{{ ($filtros['archivo_log'] ?? '') === $arch['nombre'] ? 'table-info' : '' }}">
                        <td>
                            <a class="text-primary"
                               href="{{ route('auditoria_sesion', array_merge($filtrosQuery, ['pestana' => 'archivos', 'archivo_log' => $arch['nombre'], 'consultar' => 1])) }}">
                                {{ $arch['nombre'] }}
                            </a>
                        </td>
                        <td class="text-right">{{ $arch['humano'] }}</td>
                        <td>{{ $arch['mtime'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($contenidoLog)
    @if (! ($contenidoLog['ok'] ?? false))
        <div class="alert alert-danger">{{ $contenidoLog['error'] ?? 'Error al leer el log.' }}</div>
    @else
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>{{ $contenidoLog['nombre'] }}</strong>
            <span class="text-muted small">
                {{ $contenidoLog['humano'] }} · mostrando {{ $contenidoLog['total_lineas_leidas'] }} líneas (cola)
            </span>
        </div>
        <div class="auditoria-log-box">@foreach ($contenidoLog['lineas'] as $linea)
{{ $linea }}
@endforeach</div>
    @endif
@endif
