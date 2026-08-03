<form method="get" action="{{ route('auditoria_sesion') }}" class="mb-3">
    <input type="hidden" name="consultar" value="1">
    <input type="hidden" name="pestana" value="navegacion">
    <div class="form-row align-items-end">
        <div class="form-group col-md-2">
            <label for="fecha_desde">Desde</label>
            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde"
                   value="{{ $filtros['fecha_desde'] ?? '' }}">
        </div>
        <div class="form-group col-md-2">
            <label for="fecha_hasta">Hasta</label>
            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                   value="{{ $filtros['fecha_hasta'] ?? '' }}">
        </div>
        @include('configuracion.auditoria_sesion.partials.campo_filtro_usuario', [
            'label' => 'Usuario',
            'colClass' => 'col-md-3',
            'campoId' => 'usuario_id',
            'campoCodigoId' => 'usuario_codigo',
            'campoNombreId' => 'nombreusuario',
            'usuarioIdVal' => $usuarioFiltro->id ?? ($filtros['usuario_id'] ?? ''),
            'usuarioCodigoVal' => $usuarioFiltro->usuario ?? '',
            'usuarioNombreVal' => $usuarioFiltro->nombre ?? '',
            'omitirFiltroEmpresa' => true,
        ])
        <div class="form-group col-md-2">
            <label for="tipo">Tipo</label>
            <select class="form-control" id="tipo" name="tipo">
                <option value="">Todos</option>
                <option value="navegacion" @selected(($filtros['tipo'] ?? '') === 'navegacion')>Navegación</option>
                <option value="login" @selected(($filtros['tipo'] ?? '') === 'login')>Login</option>
                <option value="logout" @selected(($filtros['tipo'] ?? '') === 'logout')>Logout</option>
            </select>
        </div>
        <div class="form-group col-md-1">
            <label for="metodo">Método</label>
            <select class="form-control" id="metodo" name="metodo">
                <option value="">Todos</option>
                @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $m)
                    <option value="{{ $m }}" @selected(($filtros['metodo'] ?? '') === $m)>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-2">
            <label for="ruta">Ruta / nombre</label>
            <input type="text" class="form-control" id="ruta" name="ruta"
                   value="{{ $filtros['ruta'] ?? '' }}" placeholder="ej. ordencompra">
        </div>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">
        <i class="fa fa-search"></i> Consultar
    </button>
</form>

@if (! $habilitada)
    <div class="alert alert-warning">
        La bitácora está <strong>desconectada</strong> (<code>BITACORA_ACCESO_HABILITADO=false</code>).
        Podés consultar histórico ya grabado, pero no se registran nuevos eventos.
    </div>
@endif

@if ($coleccion === null)
    <div class="alert alert-info mb-0">
        La tabla de bitácora aún no está disponible. Ejecutá la migración correspondiente.
    </div>
@elseif ($coleccion->total() === 0)
    <div class="alert alert-light border mb-0">
        No hay eventos con los filtros elegidos.
    </div>
@else
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-muted small">
            Mostrando {{ $coleccion->firstItem() }}–{{ $coleccion->lastItem() }}
            de {{ number_format($coleccion->total(), 0, ',', '.') }} eventos
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered auditoria-tabla mb-2" id="tabla-paginada">
            <thead>
                <tr>
                    <th>Fecha/hora</th>
                    <th>Usuario</th>
                    <th>Tipo</th>
                    <th>Método</th>
                    <th>Ruta</th>
                    <th>Nombre ruta</th>
                    <th class="text-right">HTTP</th>
                    <th class="text-right">ms</th>
                    <th class="text-right">Memoria</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($coleccion as $row)
                    <tr>
                        <td class="text-nowrap">{{ optional($row->created_at)->format('d/m/Y H:i:s') }}</td>
                        <td>
                            {{ $row->usuario_nombre ?: '—' }}
                            @if ($row->usuario_id)
                                <span class="text-muted small">#{{ $row->usuario_id }}</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $tipoCls = 'badge-tipo-navegacion';
                                if ($row->tipo === 'login') {
                                    $tipoCls = 'badge-tipo-login';
                                } elseif ($row->tipo === 'logout') {
                                    $tipoCls = 'badge-tipo-logout';
                                }
                            @endphp
                            <span class="badge {{ $tipoCls }}">{{ $row->tipo }}</span>
                        </td>
                        <td>{{ $row->metodo }}</td>
                        <td style="max-width:220px;word-break:break-all;">{{ $row->ruta }}</td>
                        <td>{{ $row->nombre_ruta ?: '—' }}</td>
                        <td class="text-right">{{ $row->status }}</td>
                        <td class="text-right">{{ $row->duracion_ms !== null ? number_format($row->duracion_ms, 0, ',', '.') : '—' }}</td>
                        <td class="text-right">
                            @if ($row->memoria_pico_kb !== null)
                                {{ number_format($row->memoria_pico_kb / 1024, 1, ',', '.') }} MB
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row->ip ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div>
        {{ $coleccion->links() }}
    </div>
@endif
