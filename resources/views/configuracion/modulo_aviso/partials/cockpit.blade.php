@php
    $c = $cockpit ?? null;
@endphp
@if ($c)
<div class="px-3 pt-3">
    <div class="border rounded p-3 mb-3" style="background:#f8fbfe;">
        <div class="d-flex flex-wrap align-items-center mb-2">
            <strong class="mr-2">Cockpit del Programador</strong>
            <a href="{{ route('historial_modulo_aviso_programado') }}" class="btn btn-outline-info btn-sm ml-auto">
                <i class="fa fa-history"></i> Historial global
            </a>
        </div>

        @php $piloto = $c['piloto_reglas'] ?? null; @endphp
        @if ($piloto)
            <div class="alert alert-{{ ! empty($piloto['motor_on']) ? 'warning' : 'secondary' }} py-2 mb-3">
                <strong>Piloto reglas WHEN/IF/THEN:</strong>
                {{ (int) ($piloto['piloto_total'] ?? 0) }} sembradas,
                {{ (int) ($piloto['piloto_activas'] ?? 0) }} activas.
                Motor {{ ! empty($piloto['motor_on']) ? 'ON' : 'OFF' }}.
                @if (empty($piloto['motor_on']))
                    <span class="text-muted">No se disparan hasta OK explícito.</span>
                @endif
                <a href="{{ $piloto['url'] }}" class="alert-link ml-1">Abrir reglas</a>
            </div>
        @endif

        @if (! empty($c['guia']) && is_array($c['guia']))
            <details class="mb-3 border rounded bg-white p-2">
                <summary class="font-weight-bold" style="cursor:pointer;">Guía operativa rápida</summary>
                <ul class="mb-0 mt-2 small pl-3">
                    @foreach ($c['guia'] as $titulo => $texto)
                        <li class="mb-1"><strong>{{ $titulo }}:</strong> {{ $texto }}</li>
                    @endforeach
                </ul>
            </details>
        @endif

        <div class="row text-center mb-3">
            <div class="col-6 col-md-2 mb-2">
                <div class="border rounded p-2 bg-white">
                    <div class="h4 mb-0">{{ (int) ($c['stats']['programables'] ?? 0) }}</div>
                    <div class="small text-muted">Programables</div>
                </div>
            </div>
            <div class="col-6 col-md-2 mb-2">
                <div class="border rounded p-2 bg-white">
                    <div class="h4 mb-0 text-success">{{ (int) ($c['stats']['habilitados'] ?? 0) }}</div>
                    <div class="small text-muted">Habilitados</div>
                </div>
            </div>
            <div class="col-6 col-md-2 mb-2">
                <div class="border rounded p-2 bg-white">
                    <div class="h4 mb-0 text-warning">{{ (int) ($c['stats']['pausados'] ?? 0) }}</div>
                    <div class="small text-muted">Pausados</div>
                </div>
            </div>
            <div class="col-6 col-md-2 mb-2">
                <div class="border rounded p-2 bg-white">
                    <div class="h4 mb-0 text-danger">{{ (int) ($c['stats']['fallos_7d'] ?? 0) }}</div>
                    <div class="small text-muted">Fallos 7d</div>
                </div>
            </div>
            <div class="col-6 col-md-2 mb-2">
                <div class="border rounded p-2 bg-white">
                    <div class="h4 mb-0">{{ (int) ($c['stats']['hermanos'] ?? 0) }}</div>
                    <div class="small text-muted">Listados / CI</div>
                </div>
            </div>
            <div class="col-6 col-md-2 mb-2">
                <div class="border rounded p-2 bg-white">
                    <div class="h4 mb-0 text-info">{{ (int) ($c['stats']['corresponden_ahora'] ?? 0) }}</div>
                    <div class="small text-muted">Corresponden ahora</div>
                </div>
            </div>
        </div>

        @php $cal = $c['calendario'] ?? null; @endphp
        @if ($cal)
        <div class="mb-3">
            <div class="d-flex flex-wrap align-items-center mb-2">
                <h6 class="mb-0 mr-2">Calendario</h6>
                <strong class="mr-2">{{ $cal['titulo'] }}</strong>
                <div class="btn-group btn-group-sm mr-2">
                    <a href="{{ $cal['url_prev'] }}" class="btn btn-outline-secondary btn-sm" title="Anterior">&laquo;</a>
                    <a href="{{ $cal['url_next'] }}" class="btn btn-outline-secondary btn-sm" title="Siguiente">&raquo;</a>
                </div>
                <div class="btn-group btn-group-sm">
                    <a href="{{ $cal['url_semana'] }}" class="btn btn-sm {{ ($cal['modo'] ?? '') === 'semana' ? 'btn-info' : 'btn-outline-info' }}">Semana</a>
                    <a href="{{ $cal['url_mes'] }}" class="btn btn-sm {{ ($cal['modo'] ?? '') === 'mes' ? 'btn-info' : 'btn-outline-info' }}">Mes</a>
                </div>
            </div>
            <div class="prog-cal-grid" style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:4px;">
                @foreach (['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $cab)
                    <div class="small text-center font-weight-bold text-muted">{{ $cab }}</div>
                @endforeach
                @foreach ($cal['dias'] as $dia)
                    <div class="border rounded p-1 bg-white"
                         style="min-height:4.5rem;{{ ! empty($dia['fuera']) ? 'opacity:0.45;' : '' }}{{ ! empty($dia['es_hoy']) ? 'outline:2px solid #2471A3;' : '' }}{{ ! empty($dia['es_feriado']) ? 'background:#fff8e6;' : '' }}">
                        <div class="small font-weight-bold {{ ! empty($dia['es_hoy']) ? 'text-primary' : '' }}">{{ $dia['label'] }}</div>
                        @forelse (array_slice($dia['items'], 0, 3) as $item)
                            <a href="{{ $item['url'] }}" class="d-block small text-truncate {{ ! empty($item['pausada']) ? 'text-warning' : 'text-primary' }}"
                               title="{{ $item['nombre'] }} {{ $item['hora'] }}">
                                {{ $item['hora'] }} {{ \Illuminate\Support\Str::limit($item['nombre'], 18) }}
                            </a>
                        @empty
                            @if (empty($dia['fuera']) && ! empty($dia['es_hoy']))
                                <span class="small text-muted">Sin tareas</span>
                            @endif
                        @endforelse
                        @if (count($dia['items']) > 3)
                            <span class="small text-muted">+{{ count($dia['items']) - 3 }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="row">
            <div class="col-lg-6 mb-3">
                <h6 class="mb-2">Próximas corridas</h6>
                @if (empty($c['proximas']))
                    <div class="border rounded bg-white p-3 text-center text-muted">
                        <i class="fa fa-calendar-o fa-2x mb-2 d-block"></i>
                        No hay próximas corridas en el horizonte.
                        <div class="small mt-1">Activá programación en una ficha o ampliá el rango de fechas.</div>
                    </div>
                @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Cuándo</th>
                                <th>Tarea</th>
                                <th>Programación</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($c['proximas'] as $row)
                                <tr @if (! empty($row['pausada'])) class="table-warning" @endif>
                                    <td class="text-nowrap">{{ $row['cuando_label'] }}</td>
                                    <td>
                                        <strong>{{ $row['nombre'] }}</strong>
                                        <br><code class="small">{{ $row['clave'] }}</code>
                                    </td>
                                    <td class="small">{{ $row['etiqueta'] }}</td>
                                    <td class="text-right text-nowrap">
                                        <a href="{{ $row['url'] }}" class="btn-accion-tabla" title="Configurar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <a href="{{ route('impacto_modulo_aviso_programado', $row['tipo_id']) }}" class="btn-accion-tabla" title="Impacto">
                                            <i class="fa fa-search"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
            <div class="col-lg-6 mb-3">
                <h6 class="mb-2">Últimos fallos</h6>
                @if (empty($c['fallos']))
                    <div class="border rounded bg-white p-3 text-center text-muted">
                        <i class="fa fa-check-circle fa-2x mb-2 d-block text-success"></i>
                        Sin fallos recientes.
                        <div class="small mt-1">Si aparece uno, llega campanita/mail (1 por clave/día) y queda en el historial.</div>
                    </div>
                @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Inicio</th>
                                <th>Clave</th>
                                <th>Detalle</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($c['fallos'] as $f)
                                <tr>
                                    <td class="text-nowrap">{{ $f['inicio'] }}</td>
                                    <td><code class="small">{{ $f['clave'] }}</code></td>
                                    <td class="small text-danger">{{ $f['detalle'] }}</td>
                                    <td class="text-right">
                                        @if (! empty($f['url']))
                                            <a href="{{ $f['url'] }}" class="btn-accion-tabla" title="Ver">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        @php $bloquesSus = $c['suscripciones_envio'] ?? []; @endphp
        @if (! empty($bloquesSus) || (function_exists('can') && can('actualizar-modulo-aviso', false)))
            <div class="d-flex flex-wrap align-items-center mb-2">
                <h6 class="mb-0 mr-2">Flash / definibles (suscripciones)</h6>
                @if (function_exists('can') && can('actualizar-modulo-aviso', false))
                    <button type="button" class="btn btn-outline-primary btn-sm ml-auto js-crear-suscripcion-programador">
                        <i class="fa fa-plus"></i> Nueva suscripción
                    </button>
                @endif
            </div>
            <p class="small text-muted mb-2">
                Alta, pausa y edición de horario/mails acá. El Programador dispara el batch cada 15′.
            </p>
            @foreach ($bloquesSus as $bloque)
                <div class="mb-3">
                    <div class="d-flex flex-wrap align-items-center mb-1">
                        <strong class="mr-2">{{ $bloque['label'] }}</strong>
                        <span class="badge badge-success">{{ (int) $bloque['activas'] }} activas</span>
                        <span class="badge badge-secondary ml-1">{{ (int) $bloque['total'] }} total</span>
                        @if (! empty($bloque['url_batch']))
                            <a href="{{ $bloque['url_batch'] }}" class="btn btn-outline-info btn-sm ml-auto">Batch</a>
                        @endif
                    </div>
                    @include('configuracion.modulo_aviso.partials.suscripciones_envio', [
                        'filas' => $bloque['filas'],
                        'titulo' => '',
                        'ayuda' => '',
                        'mostrarFamilia' => false,
                    ])
                </div>
            @endforeach
            @include('configuracion.modulo_aviso.partials.modal_editar_suscripcion')
            @include('configuracion.modulo_aviso.partials.modal_crear_suscripcion')
        @endif

        @php $jobsPlat = $c['jobs_plataforma'] ?? null; @endphp
        @if (! empty($jobsPlat['filas']))
            <details class="mb-3 border rounded bg-white p-2">
                <summary class="font-weight-bold" style="cursor:pointer;">
                    Jobs de plataforma (solo lectura) — {{ (int) ($jobsPlat['total'] ?? 0) }}
                </summary>
                <p class="small text-muted mt-2 mb-2">
                    Purges y syncs del Kernel. No se despachan desde el Programador (son de infraestructura).
                    @if (! empty($c['webhooks_allowlist']))
                        Webhooks allowlist activos: <code>{{ implode(', ', $c['webhooks_allowlist']) }}</code>.
                    @else
                        Webhooks allowlist: sin URL en .env (deshabilitado).
                    @endif
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Grupo</th>
                                <th>Comando</th>
                                <th>Descripción</th>
                                <th>Horario</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($jobsPlat['filas'] as $job)
                                <tr>
                                    <td><span class="badge badge-secondary">{{ $job['grupo'] }}</span></td>
                                    <td><code class="small">{{ $job['comando'] }}</code></td>
                                    <td class="small">{{ $job['etiqueta'] }}</td>
                                    <td class="small text-nowrap">{{ $job['horario'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif

        <h6 class="mb-2">Schedulers hermanos (despacho unificado)</h6>
        <p class="small text-muted mb-2">
            Listados workbench (08:05), CI (08:10), Flash/definibles (poll), digests y Crown
            salen por <code>avisos:despachar-programados</code>. Pausá / diario↔semanal abajo.
        </p>
        @if (empty($c['hermanos']))
            <div class="border rounded bg-white p-3 text-center text-muted mb-0">
                <i class="fa fa-envelope-o fa-2x mb-2 d-block"></i>
                No hay envíos de listado / CI cargados.
                <div class="small mt-1">Se crean desde el workbench o Consulta Inteligente al programar un envío.</div>
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Estado</th>
                        <th>Origen</th>
                        <th>Recurso</th>
                        <th>Email</th>
                        <th>Frecuencia</th>
                        <th>Último envío</th>
                        <th>Cron</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($c['hermanos'] as $h)
                        <tr @if (empty($h['activo'])) class="table-secondary" @endif>
                            <td>
                                @if (! empty($h['activo']))
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-secondary">Pausado</span>
                                @endif
                            </td>
                            <td>
                                @if ($h['origen'] === 'ci')
                                    <span class="badge badge-info">{{ $h['origen_label'] }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ $h['origen_label'] }}</span>
                                @endif
                            </td>
                            <td><code class="small">{{ $h['recurso'] }}</code></td>
                            <td class="small">{{ $h['email'] }}</td>
                            <td>{{ $h['frecuencia'] }}</td>
                            <td class="text-nowrap">{{ $h['ultimo'] }}</td>
                            <td class="small">{{ $h['cron'] }}</td>
                            <td class="text-right text-nowrap">
                                @if (function_exists('can') && can('actualizar-modulo-aviso', false))
                                    <form method="post" action="{{ route('toggle_hermano_modulo_aviso', $h['id']) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-{{ ! empty($h['activo']) ? 'warning' : 'success' }} btn-sm"
                                                title="{{ ! empty($h['activo']) ? 'Pausar' : 'Reactivar' }}">
                                            <i class="fa fa-{{ ! empty($h['activo']) ? 'pause' : 'play' }}"></i>
                                        </button>
                                    </form>
                                    <form method="post" action="{{ route('frecuencia_hermano_modulo_aviso', $h['id']) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="frecuencia"
                                               value="{{ ($h['frecuencia'] ?? '') === 'semanal' ? 'diario' : 'semanal' }}">
                                        <button type="submit" class="btn btn-outline-info btn-sm"
                                                title="Cambiar a {{ ($h['frecuencia'] ?? '') === 'semanal' ? 'diario' : 'semanal' }}">
                                            <i class="fa fa-exchange"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endif
