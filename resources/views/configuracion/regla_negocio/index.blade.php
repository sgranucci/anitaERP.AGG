@extends("theme.$theme.layout")
@section('titulo')
Reglas de negocio
@endsection

@section('contenido')
<div class="container-fluid">
    @include('includes.mensaje')

    <div class="card card-info mb-3">
        <div class="card-header">
            <h3 class="card-title mb-0"><i class="fa fa-balance-scale"></i> Motor de reglas de negocio</h3>
            <div class="card-tools">
                <a href="{{ route('consultar_aviso_regla') }}" class="btn btn-outline-light btn-sm">Reglas de aviso</a>
            </div>
        </div>
        <div class="card-body">
            <p class="mb-2">
                Estado:
                @if ($motor_vivo)
                    <span class="badge badge-success">EN MARCHA</span>
                @else
                    <span class="badge badge-secondary">DETENIDO</span>
                @endif
                @unless ($motor_entorno)
                    <span class="badge badge-danger ml-1">Kill-switch .env REGLA_NEGOCIO_HABILITADO=false</span>
                @endunless
            </p>
            <p class="text-muted small">
                Runtime <strong>síncrono</strong>:
                <code>ReglaNegocioMotorService::decidir(punto, contexto)</code>.
                Acciones tipadas (deny / set_dato / require_approval / noop / enviar_aviso).
                Retención de logs: {{ $retencion_logs_dias }} días
                (<code>php artisan regla-negocio:purgar-logs</code>).
            </p>
            @if ($puede_activar)
                <form method="post" action="{{ route('actualizar_regla_negocio_motor') }}" class="form-inline" data-enter-campos="1">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="motor_habilitado" value="0">
                    <div class="form-check mr-3">
                        <input type="checkbox" class="form-check-input" name="motor_habilitado" id="motor_habilitado" value="1"
                            @if($config->motor_habilitado) checked @endif
                            @unless($motor_entorno) disabled @endunless>
                        <label class="form-check-label" for="motor_habilitado">Activar motor</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" @unless($motor_entorno) disabled @endunless>Guardar</button>
                </form>
            @else
                <p class="text-muted small mb-0">Sin permiso <code>activar-regla-negocio</code> para encender/apagar el motor.</p>
            @endif
        </div>
    </div>

    <div class="card card-outline card-secondary mb-3">
        <div class="card-header">
            <h3 class="card-title mb-0">Cobertura de puntos</h3>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Punto</th>
                        <th>Dominio</th>
                        <th>Reglas</th>
                        <th>Activas</th>
                        <th>Cableado</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cobertura as $c)
                        <tr>
                            <td>
                                <code class="small">{{ $c['punto'] }}</code>
                                <br><span class="text-muted small">{{ $c['etiqueta'] }}</span>
                            </td>
                            <td>{{ $c['dominio'] }}</td>
                            <td>{{ $c['total'] }}</td>
                            <td>{{ $c['activas'] }}</td>
                            <td>
                                @if ($c['cableado'])
                                    <span class="badge badge-success">Sí</span>
                                    <div class="small text-muted">{{ class_basename($c['cableado_en']) }}</div>
                                @else
                                    <span class="badge badge-secondary">No</span>
                                @endif
                            </td>
                            <td><code class="small">{{ $c['estado'] }}</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card card-outline card-secondary mb-3">
        <div class="card-header">
            <h3 class="card-title mb-0">Puente con avisos (F5)</h3>
        </div>
        <div class="card-body">
            <p class="small mb-2">{{ $puente_avisos['mensaje'] }}</p>
            <div class="row">
                <div class="col-md-6">
                    <strong>BRE sync</strong>:
                    {{ $puente_avisos['bre_motor'] ? 'ON' : 'OFF' }} ·
                    {{ $puente_avisos['bre_activas'] }}/{{ $puente_avisos['bre_reglas'] }} activas
                </div>
                <div class="col-md-6">
                    <strong>Avisos async</strong>:
                    {{ $puente_avisos['aviso_motor'] ? 'ON' : 'OFF' }} ·
                    {{ $puente_avisos['aviso_activas'] }}/{{ $puente_avisos['aviso_reglas'] }} activas
                    · <a href="{{ route('consultar_aviso_regla') }}">abrir</a>
                </div>
            </div>
            <p class="text-muted small mb-0 mt-2">
                Evaluador IF compartido: <code>ReglaNegocioCondicionSupport</code>.
                Acción BRE <code>enviar_aviso</code> puede disparar un tipo de aviso tipado.
            </p>
        </div>
    </div>

    <div class="card card-info">
        <div class="card-header">
            <h3 class="card-title mb-0">Reglas</h3>
            <div class="card-tools">
                @if ($puede_disenar)
                    <a href="{{ route('crear_regla_negocio') }}" class="btn btn-outline-light btn-sm">Nueva regla</a>
                @endif
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Punto</th>
                        <th>Dominio</th>
                        <th>Prioridad</th>
                        <th>Stop</th>
                        <th>Activa</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reglas as $r)
                        <tr>
                            <td><code>{{ $r->codigo }}</code></td>
                            <td>{{ $r->nombre }}</td>
                            <td>
                                <code class="small">{{ $r->punto }}</code>
                                @if (!empty($puntos[$r->punto]['etiqueta']))
                                    <br><span class="text-muted small">{{ $puntos[$r->punto]['etiqueta'] }}</span>
                                @endif
                            </td>
                            <td>{{ $r->dominio }}</td>
                            <td>{{ $r->prioridad }}</td>
                            <td>{{ $r->stop_on_match ? 'Sí' : 'No' }}</td>
                            <td>{{ $r->activo ? 'Sí' : 'No' }}</td>
                            <td class="text-right text-nowrap">
                                <a href="{{ route('editar_regla_negocio', $r->id) }}" class="btn btn-sm btn-accion-tabla" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @if ($puede_activar)
                                    <form method="post" action="{{ route('toggle_regla_negocio_activo', $r->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-accion-tabla"
                                            title="{{ $r->activo ? 'Desactivar' : 'Activar' }}">
                                            <i class="fas {{ $r->activo ? 'fa-toggle-on text-success' : 'fa-toggle-off text-muted' }}"></i>
                                        </button>
                                    </form>
                                @endif
                                @if ($puede_disenar)
                                    <form method="post" action="{{ route('eliminar_regla_negocio', $r->id) }}" class="d-inline"
                                        onsubmit="return confirm('¿Eliminar regla {{ $r->codigo }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-accion-tabla" title="Eliminar">
                                            <i class="fas fa-times-circle text-danger"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-muted p-3">Sin reglas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card card-outline card-secondary mt-3">
        <div class="card-header">
            <h3 class="card-title mb-0">Últimas evaluaciones</h3>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Cuando</th>
                        <th>Regla</th>
                        <th>Punto</th>
                        <th>OK</th>
                        <th>Permitido</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ optional($log->ejecutado_en)->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $log->regla->codigo ?? ('#'.$log->regla_negocio_id) }}</td>
                            <td><code class="small">{{ $log->punto }}</code></td>
                            <td>{{ $log->ok ? 'Sí' : 'No' }}</td>
                            <td>{{ $log->permitido ? 'Sí' : 'No' }}</td>
                            <td class="small">{{ $log->detalle }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted p-3">Sin evaluaciones aún.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
