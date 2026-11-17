@extends("theme.$theme.layout")
@section('titulo')
Reglas de aviso
@endsection

@section('contenido')
<div class="container-fluid">
    @include('includes.mensaje')

    <div class="card card-info mb-3">
        <div class="card-header">
            <h3 class="card-title mb-0"><i class="fa fa-project-diagram"></i> Motor de reglas</h3>
            <div class="card-tools">
                <a href="{{ route('consultar_regla_negocio') }}" class="btn btn-outline-light btn-sm">Reglas de negocio</a>
                <a href="{{ route('consultar_modulo_aviso_evento') }}" class="btn btn-outline-light btn-sm">Eventos</a>
                <a href="{{ route('consultar_modulo_aviso') }}" class="btn btn-outline-light btn-sm">Avisos</a>
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
                    <span class="badge badge-danger ml-1">Kill-switch .env AVISO_REGLAS_HABILITADO=false</span>
                @endunless
            </p>
            <p class="text-muted small">
                Cuando está en marcha, cada <code>emit()</code> del event bus evalúa las reglas activas del evento
                (WHEN → IF → THEN). No reemplaza el árbol de aprobación: suma avisos/campanita para casos que el árbol no cubre.
            </p>
            <form method="post" action="{{ route('actualizar_aviso_regla_motor') }}" class="form-inline" data-enter-campos="1">
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
        </div>
    </div>

    <div class="card card-info">
        <div class="card-header">
            <h3 class="card-title mb-0">Reglas</h3>
            <div class="card-tools">
                @if (function_exists('can') && can('actualizar-modulo-aviso', false))
                    <a href="{{ route('crear_aviso_regla') }}" class="btn btn-outline-light btn-sm">Nueva regla</a>
                @endif
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Evento</th>
                        <th>Prioridad</th>
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
                                <code class="small">{{ $r->evento }}</code>
                                @if (!empty($catalogo[$r->evento]))
                                    <br><span class="text-muted small">{{ $catalogo[$r->evento] }}</span>
                                @endif
                            </td>
                            <td>{{ $r->prioridad }}</td>
                            <td>{{ $r->activo ? 'Sí' : 'No' }}</td>
                            <td class="text-right text-nowrap">
                                <a href="{{ route('editar_aviso_regla', $r->id) }}" class="btn btn-sm btn-accion-tabla" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="post" action="{{ route('eliminar_aviso_regla', $r->id) }}" class="d-inline"
                                    onsubmit="return confirm('¿Eliminar regla {{ $r->codigo }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-accion-tabla" title="Eliminar">
                                        <i class="fas fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted p-3">Sin reglas. Creá una cuando necesites un caso fuera del árbol.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card card-outline card-secondary mt-3">
        <div class="card-header">
            <h3 class="card-title mb-0">Últimas ejecuciones</h3>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Cuando</th>
                        <th>Regla</th>
                        <th>Evento</th>
                        <th>Entity</th>
                        <th>OK</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ optional($log->ejecutado_en)->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $log->regla->codigo ?? ('#'.$log->aviso_regla_id) }}</td>
                            <td><code class="small">{{ $log->evento }}</code></td>
                            <td>{{ $log->entity_id ?? '—' }}</td>
                            <td>{{ $log->ok ? 'Sí' : 'No' }}</td>
                            <td class="small">{{ $log->detalle }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted p-3">Sin ejecuciones aún (motor detenido o sin reglas activas).</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
