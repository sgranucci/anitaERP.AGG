@extends("theme.$theme.layout")
@section('titulo')
    Aperturas programadas de período contable
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Aperturas programadas</h3>
                @if ($puede_solicitar)
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modal-solicitar-apertura">
                            <i class="fa fa-unlock"></i> Solicitar apertura
                        </button>
                    </div>
                @endif
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>¿Cómo solicitar una apertura?</strong>
                    <ol class="mb-0 pl-3 small">
                        <li>Ingrese a <strong>Módulo Contable → Aperturas programadas</strong> (requiere permiso de solicitud).</li>
                        <li>Pulse <strong>Solicitar apertura</strong> y complete empresa, usuario a habilitar (puede ser usted mismo), rango de fechas del período cerrado, alcance del módulo y duración en horas o días.</li>
                        <li>Indique el motivo (mínimo 10 caracteres) y envíe. La solicitud queda en estado <em>pendiente</em>.</li>
                        <li>El encargado de contaduría (o quien tenga permiso de habilitación) recibe un <strong>correo con enlace directo</strong> para habilitar sin entrar al módulo, o puede aprobar desde esta pantalla.</li>
                        <li>Al aprobarse recibirá un correo; podrá operar solo en el alcance y fechas indicados hasta la hora de vencimiento.</li>
                        <li>Antes de vencer recibirá un recordatorio; al finalizar el plazo el período vuelve a quedar cerrado para su usuario.</li>
                    </ol>
                </div>

                <p class="text-muted small">
                    Permite habilitar temporalmente a un usuario para operar en fechas dentro del período cerrado,
                    en el módulo indicado, por un tiempo limitado en horas o días.
                </p>

                <form method="get" action="{{ route('apertura_periodo_contable') }}" class="form-inline mb-3">
                    @include('includes.listado.filtro_empresa_asignada_inline', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresa_id ?: null,
                        'permite_todas' => true,
                    ])
                    <label class="ml-2 mr-1">Estado</label>
                    <select name="estado" class="form-control form-control-sm mr-2">
                        <option value="">Todos</option>
                        @foreach (['pendiente', 'activa', 'vencida', 'revocada', 'rechazada'] as $est)
                            <option value="{{ $est }}" @selected($estado_filtro === $est)>{{ ucfirst($est) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filtrar</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-sm" id="tabla-paginada">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>ID</th>
                                <th>Empresa</th>
                                <th>Usuario habilitado</th>
                                <th>Rango fechas</th>
                                <th>Alcance</th>
                                <th>Duración</th>
                                <th>Vence</th>
                                <th>Estado</th>
                                @if ($puede_gestionar || $puede_aprobar || $puede_habilitar)
                                    <th>Acciones</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($aperturas as $apertura)
                                <tr>
                                    <td>{{ $apertura->id }}</td>
                                    <td>{{ $apertura->empresa?->nombre }}</td>
                                    <td>{{ $apertura->habilitado?->nombre }}</td>
                                    <td>
                                        {{ optional($apertura->fecha_operacion_desde)->format('d/m/Y') }}
                                        –
                                        {{ optional($apertura->fecha_operacion_hasta)->format('d/m/Y') }}
                                    </td>
                                    <td>{{ $apertura->etiquetaAlcance() }}</td>
                                    <td>{{ $apertura->duracion_cantidad }} {{ $apertura->duracion_unidad === 'dias' ? 'día(s)' : 'hora(s)' }}</td>
                                    <td>{{ optional($apertura->vence_en)->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td><span class="badge badge-secondary">{{ ucfirst($apertura->estado) }}</span></td>
                                    @if ($puede_gestionar || $puede_aprobar || $puede_habilitar)
                                        <td class="text-nowrap">
                                            @if (($puede_aprobar || $puede_habilitar) && $apertura->estado === 'pendiente')
                                                <form method="post" action="{{ route('aprobar_apertura_periodo_contable', $apertura->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success btn-sm" title="Habilitar">
                                                        <i class="fa fa-check"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if ($puede_aprobar && $apertura->estado === 'pendiente')
                                                <form method="post" action="{{ route('rechazar_apertura_periodo_contable', $apertura->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Rechazar">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if ($puede_revocar && in_array($apertura->estado, ['pendiente', 'activa'], true))
                                                <form method="post" action="{{ route('revocar_apertura_periodo_contable', $apertura->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-warning btn-sm" title="Revocar"
                                                        onclick="return confirm('¿Revocar esta apertura?');">
                                                        <i class="fa fa-ban"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ ($puede_gestionar || $puede_aprobar || $puede_habilitar || $puede_revocar) ? 9 : 8 }}" class="text-center text-muted">
                                        Sin solicitudes de apertura.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($aperturas->hasPages())
                    <div class="d-flex justify-content-between align-items-center">
                        <small>{{ $aperturas->firstItem() }}–{{ $aperturas->lastItem() }} de {{ $aperturas->total() }}</small>
                        {{ $aperturas->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if ($puede_solicitar)
<div class="modal fade" id="modal-solicitar-apertura" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="post" action="{{ route('solicitar_apertura_periodo_contable') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Solicitar apertura programada</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                @include('includes.form-empresa-asignada', [
                    'empresa_query' => $empresa_query,
                    'empresa_id' => $empresa_id ?: null,
                ])
                <div class="form-group row">
                    <label class="col-md-3 control-label requerido">Usuario a habilitar</label>
                    <div class="col-md-8">
                        <select name="usuario_habilitado_id" class="form-control" required>
                            <option value="">Seleccione…</option>
                            @foreach ($usuarios as $u)
                                <option value="{{ $u->id }}" @selected(old('usuario_habilitado_id', auth()->id()) == $u->id)>
                                    {{ $u->nombre }} ({{ $u->usuario }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-3 control-label requerido">Fechas operación</label>
                    <div class="col-md-4">
                        <input type="date" name="fecha_operacion_desde" class="form-control" required value="{{ old('fecha_operacion_desde') }}">
                    </div>
                    <div class="col-md-4">
                        <input type="date" name="fecha_operacion_hasta" class="form-control" required value="{{ old('fecha_operacion_hasta') }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-3 control-label requerido">Alcance / permiso</label>
                    <div class="col-md-8">
                        <select name="alcance" class="form-control" required>
                            @foreach ($alcances as $codigo => $etiqueta)
                                <option value="{{ $codigo }}" @selected(old('alcance') === $codigo)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-3 control-label requerido">Tiempo habilitado</label>
                    <div class="col-md-3">
                        <input type="number" name="duracion_cantidad" class="form-control" min="1" max="720" required value="{{ old('duracion_cantidad', 4) }}">
                    </div>
                    <div class="col-md-4">
                        <select name="duracion_unidad" class="form-control" required>
                            <option value="horas" @selected(old('duracion_unidad', 'horas') === 'horas')>Horas</option>
                            <option value="dias" @selected(old('duracion_unidad') === 'dias')>Días</option>
                        </select>
                    </div>
                </div>
                <div class="form-group row mb-0">
                    <label class="col-md-3 control-label requerido">Motivo</label>
                    <div class="col-md-8">
                        <textarea name="motivo" class="form-control" rows="3" required minlength="10">{{ old('motivo') }}</textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Enviar solicitud</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
