@extends("theme.$theme.layout")
@section('titulo')
SLA y escalamiento
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-10">
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title mb-0">Mis aprobaciones — SLA</h3>
            </div>
            <form method="post" action="{{ route('actualizar_user_task_sla') }}" data-enter-campos="1">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="form-group row">
                        <label for="sla_horas" class="col-lg-4 col-form-label">Horas de SLA</label>
                        <div class="col-lg-4">
                            <input type="number" min="1" max="8760" class="form-control" name="sla_horas" id="sla_horas"
                                value="{{ old('sla_horas', $config->sla_horas) }}" required>
                            <small class="form-text text-muted">Al sincronizar user_task se calcula vence_en = abierta + estas horas.</small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 col-form-label">Bandeja desde tabla</label>
                        <div class="col-lg-8">
                            <input type="hidden" name="bandeja_desde_tabla" value="0">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="bandeja_desde_tabla" id="bandeja_desde_tabla" value="1"
                                    @if(old('bandeja_desde_tabla', $config->bandeja_desde_tabla)) checked @endif>
                                <label class="form-check-label" for="bandeja_desde_tabla">
                                    Mis aprobaciones lee <code>user_task</code> (más rápido; requiere sync al día)
                                </label>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h5>SLA por fuente</h5>
                    <p class="text-muted small">
                        Vacío o igual al global = hereda las horas de arriba. Solo guardá un valor distinto cuando esa fuente necesite otro plazo.
                    </p>
                    <table class="table table-sm table-bordered mb-4">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr><th>Fuente</th><th style="width:8rem;">Horas</th></tr>
                        </thead>
                        <tbody>
                            @foreach (($fuentes ?? []) as $cod => $nom)
                                @php
                                    $valor = old('sla_fuente.'.$cod, $sla_por_fuente[$cod] ?? $config->sla_horas);
                                @endphp
                                <tr>
                                    <td>{{ $nom }} <code class="small text-muted">{{ $cod }}</code></td>
                                    <td>
                                        <input type="number" min="1" max="8760" class="form-control form-control-sm"
                                            name="sla_fuente[{{ $cod }}]" value="{{ $valor }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <hr>
                    <h5>Escaladores</h5>
                    <p class="text-muted small">Reciben copia in-app cuando vence el SLA. También podés sumar IDs en <code>USER_TASK_ESCALAMIENTO_USUARIO_IDS</code> como respaldo.</p>
                    <div class="form-group row">
                        <label for="escalador_usuario_id" class="col-lg-4 col-form-label">Agregar usuario (ID)</label>
                        <div class="col-lg-4">
                            <input type="number" min="1" class="form-control" name="escalador_usuario_id" id="escalador_usuario_id"
                                placeholder="ID usuario operativo">
                        </div>
                    </div>
                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr><th>Usuario</th><th>Activo</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($escaladores as $esc)
                                <tr>
                                    <td>
                                        #{{ $esc->usuario_id }}
                                        {{ $esc->usuarios->nombre ?? '' }}
                                        <span class="text-muted small">({{ $esc->usuarios->usuario ?? '' }})</span>
                                    </td>
                                    <td>{{ $esc->activo ? 'Sí' : 'No' }}</td>
                                    <td class="text-right">
                                        <button form="form-quitar-{{ $esc->id }}" type="submit" class="btn btn-sm btn-accion-tabla"
                                            onclick="return confirm('¿Quitar escalador?');" title="Quitar">
                                            <i class="fas fa-times-circle text-danger"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">Sin escaladores en pantalla.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                    <a href="{{ route('consultar_user_task_sla_kpi') }}" class="btn btn-outline-info btn-sm">KPI SLA</a>
                    <a href="{{ route('editar_user_task_reasignacion_masiva') }}" class="btn btn-outline-info btn-sm">Reasignación masiva</a>
                    <a href="{{ route('consultar_modulo_aviso') }}" class="btn btn-outline-info btn-sm">Avisos</a>
                </div>
            </form>
            @foreach ($escaladores as $esc)
                <form id="form-quitar-{{ $esc->id }}" method="post" action="{{ route('quitar_user_task_escalador', $esc->id) }}">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        </div>
    </div>
</div>
@endsection
