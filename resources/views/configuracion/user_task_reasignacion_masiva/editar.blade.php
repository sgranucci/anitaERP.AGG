@extends("theme.$theme.layout")
@section('titulo')
Reasignación masiva de tareas
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-10">
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title mb-0">Reasignar user_task pendientes</h3>
            </div>
            <form method="post" action="{{ route('ejecutar_user_task_reasignacion_masiva') }}" data-enter-campos="1"
                onsubmit="return confirm('¿Reasignar las tareas pendientes según estos criterios?');">
                @csrf
                <div class="card-body">
                    <p class="text-muted">
                        Mueve pendientes del índice <code>user_task</code> al usuario destino.
                        Podés tomar el origen por <strong>usuario</strong> y/o por <strong>rol</strong> (todos los operativos con ese rol).
                        Las acciones de dominio (aprobar) siguen en cada módulo; esto reasigna la bandeja/índice.
                    </p>
                    <div class="form-group row">
                        <label class="col-lg-4 col-form-label requerido" for="destino_usuario_id">Usuario destino (ID)</label>
                        <div class="col-lg-4">
                            <input type="number" min="1" class="form-control" name="destino_usuario_id" id="destino_usuario_id"
                                value="{{ old('destino_usuario_id') }}" required>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 col-form-label" for="desde_usuario_id">Usuario origen (ID)</label>
                        <div class="col-lg-4">
                            <input type="number" min="1" class="form-control" name="desde_usuario_id" id="desde_usuario_id"
                                value="{{ old('desde_usuario_id') }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 col-form-label" for="desde_rol_id">Rol origen</label>
                        <div class="col-lg-6">
                            <select name="desde_rol_id" id="desde_rol_id" class="form-control">
                                <option value="">— ninguno —</option>
                                @foreach ($roles as $rol)
                                    <option value="{{ $rol->id }}" @if((int)old('desde_rol_id')===$rol->id) selected @endif>
                                        {{ $rol->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 col-form-label" for="fuente">Fuente (opcional)</label>
                        <div class="col-lg-6">
                            <select name="fuente" id="fuente" class="form-control">
                                <option value="">Todas</option>
                                @foreach ($fuentes as $cod => $nom)
                                    <option value="{{ $cod }}" @if(old('fuente')===$cod) selected @endif>{{ $nom }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 col-form-label" for="limite">Límite</label>
                        <div class="col-lg-3">
                            <input type="number" min="1" max="2000" class="form-control" name="limite" id="limite"
                                value="{{ old('limite', 500) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 col-form-label" for="motivo">Motivo</label>
                        <div class="col-lg-8">
                            <input type="text" class="form-control" name="motivo" id="motivo" maxlength="255"
                                value="{{ old('motivo') }}"
                                placeholder="Ej. licencia / cambio de rol — se avisa al destino">
                            <small class="form-text text-muted">Queda en el meta de la tarea y en el aviso in-app al usuario destino.</small>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-sm">Reasignar</button>
                    <a href="{{ route('consultar_user_task_sla_kpi') }}" class="btn btn-outline-info btn-sm">KPI SLA</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
