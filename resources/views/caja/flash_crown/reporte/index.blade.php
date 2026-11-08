@extends("theme.$theme.layout")
@section('titulo')
    Flash Report Crown
@endsection

@section('contenido')
@php
    $editando = $suscripcion_editar ?? null;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Flash Report Crown</h3>
                <div class="card-tools">
                    <a href="{{ route('flash_crown') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Flash diario
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('exportar_flash_reporte_crown') }}" class="form-horizontal mb-4">
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2" for="desde">Desde</label>
                        <div class="col-lg-2">
                            <input type="date" name="desde" id="desde" class="form-control" value="{{ $desde }}">
                        </div>
                        <label class="col-lg-1 control-label text-right pr-2" for="hasta">Hasta</label>
                        <div class="col-lg-2">
                            <input type="date" name="hasta" id="hasta" class="form-control" value="{{ $hasta }}">
                        </div>
                        <label class="col-lg-1 control-label text-right pr-2" for="perfil_vista">Perfil</label>
                        <div class="col-lg-2">
                            <select name="perfil_vista" id="perfil_vista" class="form-control">
                                @foreach ($perfiles_vista as $k => $v)
                                    <option value="{{ $k }}" {{ ($perfil_vista ?? 'completa') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <label class="mb-0">
                                <input type="checkbox" name="solo_validados" value="1" checked>
                                Solo validados
                            </label>
                            <button type="submit" class="btn btn-success btn-sm d-block mt-1">
                                <i class="fa fa-file-excel-o"></i> Excel
                            </button>
                        </div>
                    </div>
                </form>

                @if ($puedeAdministrar ?? false)
                    <hr>
                    <h5>{{ $editando ? 'Editar envío' : 'Nuevo envío automático' }}</h5>
                    <form method="post"
                          action="{{ $editando ? route('actualizar_suscripcion_flash_reporte_crown', $editando->id) : route('guardar_suscripcion_flash_reporte_crown') }}"
                          class="form-horizontal" data-enter-campos="1">
                        @csrf
                        @if ($editando)
                            @method('PUT')
                        @endif
                        <div class="form-group row">
                            <label class="col-lg-2 control-label text-right pr-2">Nombre</label>
                            <div class="col-lg-4">
                                <input type="text" name="nombre" class="form-control form-control-sm" required
                                       value="{{ old('nombre', $editando->nombre ?? '') }}">
                            </div>
                            <label class="col-lg-2 control-label text-right pr-2">Activo</label>
                            <div class="col-lg-2">
                                <input type="checkbox" name="activo" value="1" {{ old('activo', $editando->activo ?? true) ? 'checked' : '' }}>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-lg-2 control-label text-right pr-2">Periodicidad</label>
                            <div class="col-lg-2">
                                <select name="periodicidad" class="form-control form-control-sm">
                                    @foreach ($periodicidades as $k => $v)
                                        <option value="{{ $k }}" {{ old('periodicidad', $editando->periodicidad ?? 'diaria') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <label class="col-lg-1 control-label text-right pr-2">Hora</label>
                            <div class="col-lg-2">
                                <input type="time" name="hora" class="form-control form-control-sm" value="{{ old('hora', $editando->hora ?? '08:00') }}">
                            </div>
                            <label class="col-lg-2 control-label text-right pr-2">Solo validados</label>
                            <div class="col-lg-2">
                                <input type="checkbox" name="solo_validados" value="1" {{ old('solo_validados', $editando->solo_validados ?? true) ? 'checked' : '' }}>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-lg-2 control-label text-right pr-2">Período</label>
                            <div class="col-lg-3">
                                <select name="periodo_relativo" class="form-control form-control-sm">
                                    @foreach ($periodos_relativos as $k => $v)
                                        <option value="{{ $k }}" {{ old('periodo_relativo', $editando->periodo_relativo ?? 'mes_actual') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <label class="col-lg-2 control-label text-right pr-2">Perfil Excel</label>
                            <div class="col-lg-3">
                                <select name="perfil_vista" class="form-control form-control-sm">
                                    @foreach ($perfiles_vista as $k => $v)
                                        <option value="{{ $k }}" {{ old('perfil_vista', $editando->perfil_vista ?? 'completa') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-lg-2 control-label text-right pr-2">Mails</label>
                            <div class="col-lg-8">
                                <input type="text" name="destinatarios" class="form-control form-control-sm" required
                                       placeholder="mail1@empresa.com, mail2@empresa.com"
                                       value="{{ old('destinatarios', $editando->destinatarios ?? '') }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-lg-2 control-label text-right pr-2">Mensaje</label>
                            <div class="col-lg-8">
                                <textarea name="mensaje" class="form-control form-control-sm" rows="2">{{ old('mensaje', $editando->mensaje ?? '') }}</textarea>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-lg-10 offset-lg-2">
                                <button type="submit" class="btn btn-primary btn-sm">{{ $editando ? 'Actualizar' : 'Guardar envío' }}</button>
                                @if ($editando)
                                    <a href="{{ route('flash_reporte_crown') }}" class="btn btn-outline-secondary btn-sm">Cancelar</a>
                                @endif
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive mt-3">
                        <table class="table table-sm table-bordered">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Periodicidad</th>
                                    <th>Hora</th>
                                    <th>Perfil</th>
                                    <th>Último</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($suscripciones as $s)
                                    <tr>
                                        <td>{{ $s->nombre }} @if (! $s->activo)<span class="badge badge-secondary">off</span>@endif</td>
                                        <td>{{ $periodicidades[$s->periodicidad] ?? $s->periodicidad }}</td>
                                        <td>{{ $s->hora }}</td>
                                        <td>{{ $perfiles_vista[$s->perfil_vista ?? 'completa'] ?? $s->perfil_vista }}</td>
                                        <td>
                                            {{ $s->ultima_ejecucion?->format('d/m/Y H:i') ?? '—' }}
                                            @if ($s->ultimo_estado)
                                                <small class="d-block text-muted">{{ $s->ultimo_estado }}: {{ $s->ultimo_mensaje }}</small>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            <a href="{{ route('flash_reporte_crown', ['editar' => $s->id]) }}" class="btn btn-outline-secondary btn-sm">Editar</a>
                                            <form method="post" action="{{ route('probar_suscripcion_flash_reporte_crown', $s->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-info btn-sm" name="enviar" value="0">Probar</button>
                                                <button type="submit" class="btn btn-outline-success btn-sm" name="enviar" value="1" onclick="return confirm('¿Enviar mail real?');">Enviar</button>
                                            </form>
                                            <form method="post" action="{{ route('eliminar_suscripcion_flash_reporte_crown', $s->id) }}" class="d-inline" onsubmit="return confirm('¿Eliminar?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-muted">Sin suscripciones.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mb-0">El cron envía solo días validados (salvo que desmarques la opción). Comparación “Anterior” = mismo día del año previo.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
