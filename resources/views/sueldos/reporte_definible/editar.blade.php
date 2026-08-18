@extends("theme.$theme.layout")
@section('titulo')
    Editar listado {{ $data->codigo }}
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/concepto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/liquidacion/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/reporte_definible/asociado-consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/reporte_definible/form.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.tabs-activas-estilos')
@php
    $tabActiva = request('tab', 'diseno');
    if (! in_array($tabActiva, ['diseno', 'gobierno', 'operacion'], true)) {
        $tabActiva = request('tab') === 'columnas' ? 'diseno' : 'diseno';
    }
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Listado definible {{ $data->codigo }}</h3>
                <div class="card-tools">
                    <a href="{{ route('paridad_reporte_sueldos_definible', ['id' => $data->id]) }}" class="btn btn-outline-warning btn-sm mr-1">
                        <i class="fa fa-balance-scale"></i> Paridad
                    </a>
                    <a href="{{ route('reporte_sueldos_definible') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="tabs-activas">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link {{ $tabActiva === 'diseno' ? 'active' : '' }}" data-toggle="tab" href="#tab-diseno" role="tab">Diseño</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $tabActiva === 'gobierno' ? 'active' : '' }}" data-toggle="tab" href="#tab-gobierno" role="tab">Gobierno</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $tabActiva === 'operacion' ? 'active' : '' }}" data-toggle="tab" href="#tab-operacion" role="tab">Operación</a>
                        </li>
                    </ul>
                </div>
                <div class="tab-content pt-3">
                    <div class="tab-pane fade {{ $tabActiva === 'diseno' ? 'show active' : '' }}" id="tab-diseno" role="tabpanel">
                        @include('sueldos.reporte_definible.partials.preview_papel')

                        <div class="card card-outline card-secondary mb-0 mt-3">
                            <div class="card-header py-2">
                                <a class="text-dark" data-toggle="collapse" href="#rsd-cabecera-datos" role="button" aria-expanded="false">
                                    Datos del listado
                                </a>
                            </div>
                            <div id="rsd-cabecera-datos" class="collapse">
                            <div class="card-body py-2">
                                <form method="post" action="{{ route('actualizar_reporte_sueldos_definible', ['id' => $data->id]) }}" class="form-horizontal" id="form-cabecera-rsd">
                                    @csrf
                                    @method('PUT')
                                    <div class="form-row">
                                        <div class="form-group col-md-2">
                                            <label class="small">Código</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data->codigo }}" readonly>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label class="small requerido" for="titulo">Título</label>
                                            <input type="text" name="titulo" id="titulo" class="form-control form-control-sm" value="{{ old('titulo', $data->titulo) }}" required maxlength="80">
                                        </div>
                                        <div class="form-group col-md-2">
                                            <label class="small" for="tipo">Tipo</label>
                                            <select name="tipo" id="tipo" class="form-control form-control-sm">
                                                @foreach ($tiposListado as $k => $v)
                                                    <option value="{{ $k }}" {{ old('tipo', $data->tipo) === $k ? 'selected' : '' }}>{{ $v }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group col-md-2">
                                            <label class="small">Activo</label>
                                            <div class="custom-control custom-checkbox mt-1">
                                                <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1"
                                                       {{ old('activo', $data->activo) ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="activo">Sí</label>
                                            </div>
                                        </div>
                                        <div class="form-group col-md-2 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary btn-sm btn-block">
                                                <i class="fa fa-save"></i> Guardar
                                            </button>
                                        </div>
                                    </div>
                                    @include('sueldos.reporte_definible.partials.campo_consulta_asociado', [
                                        'codigo' => old('asociado_codigo', $data->asociado_codigo),
                                        'col_label' => 'col-lg-2 control-label text-right pr-2',
                                        'col_input' => 'col-lg-4',
                                    ])
                                    @include('includes.form-empresa-asignada', [
                                        'empresa_query' => $empresa_query,
                                        'empresa_id' => old('empresa_id', $data->empresa_id),
                                        'required' => false,
                                        'permite_vacio' => true,
                                        'opcion_vacia' => '— Todas / sin filtro —',
                                        'col_label' => 'col-lg-2 control-label text-right pr-2',
                                        'col_input' => 'col-lg-4',
                                    ])
                                    <div class="form-group row mb-0">
                                        <label for="observaciones" class="col-lg-2 control-label text-right pr-2">Observaciones</label>
                                        <div class="col-lg-8">
                                            <textarea name="observaciones" id="observaciones" class="form-control form-control-sm" rows="1">{{ old('observaciones', $data->observaciones) }}</textarea>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade {{ $tabActiva === 'gobierno' ? 'show active' : '' }}" id="tab-gobierno" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <div class="card card-outline card-info h-100">
                                    <div class="card-header py-2"><strong>Versiones</strong></div>
                                    <div class="card-body">
                                        <form method="post" action="{{ route('publicar_version_reporte_sueldos_definible', ['id' => $data->id]) }}" class="mb-3">
                                            @csrf
                                            <div class="form-inline">
                                                <input type="text" name="comentario" class="form-control form-control-sm mr-2" placeholder="Comentario versión" maxlength="255">
                                                <button type="submit" class="btn btn-outline-primary btn-sm">Publicar versión</button>
                                            </div>
                                        </form>
                                        <p class="text-muted small">Versión actual: {{ $data->version_actual }}</p>
                                        <ul class="list-group list-group-flush">
                                            @forelse ($versiones as $ver)
                                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                    <span>v{{ $ver->version }} — {{ $ver->created_at }} {{ $ver->comentario }}</span>
                                                    <form method="post" action="{{ route('restaurar_version_reporte_sueldos_definible', ['id' => $data->id, 'versionId' => $ver->id]) }}"
                                                          onsubmit="return confirm('¿Restaurar esta versión? Se publicará como versión nueva.');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Restaurar</button>
                                                    </form>
                                                </li>
                                            @empty
                                                <li class="list-group-item text-muted px-0">Sin versiones publicadas</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-3">
                                <div class="card card-outline card-info h-100">
                                    <div class="card-header py-2"><strong>ACL</strong></div>
                                    <div class="card-body">
                                        <p class="text-muted small">Sin usuarios = acceso libre (según permisos). Con usuarios = solo esos.</p>
                                        <form method="post" action="{{ route('guardar_acl_reporte_sueldos_definible', ['id' => $data->id]) }}">
                                            @csrf
                                            <select name="usuario_ids[]" class="form-control" multiple size="10">
                                                @foreach ($usuariosAcl as $u)
                                                    <option value="{{ $u->id }}" {{ in_array((int) $u->id, $aclUsuarios, true) ? 'selected' : '' }}>
                                                        {{ $u->nombre }} ({{ $u->usuario }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-primary btn-sm mt-2">Guardar ACL</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="card card-outline card-info">
                                    <div class="card-header py-2"><strong>Controles post-ejecución</strong></div>
                                    <div class="card-body">
                                        <form method="post" action="{{ route('guardar_alerta_reporte_sueldos_definible', ['id' => $data->id]) }}">
                                            @csrf
                                            <div class="form-row">
                                                <div class="form-group col-md-3">
                                                    <label>Nombre</label>
                                                    <input type="text" name="nombre" class="form-control form-control-sm" maxlength="100">
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label>Control</label>
                                                    <select name="tipo" class="form-control form-control-sm">
                                                        @foreach($tiposAlerta as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                                                    </select>
                                                </div>
                                                <div class="form-group col-md-2">
                                                    <label>Columna N°</label>
                                                    <input type="number" name="columna_nro" class="form-control form-control-sm" min="1">
                                                </div>
                                                <div class="form-group col-md-1">
                                                    <label>Oper.</label>
                                                    <select name="operador" class="form-control form-control-sm">
                                                        @foreach(['>','>=','<','<=','=','!=','entre'] as $op)<option>{{ $op }}</option>@endforeach
                                                    </select>
                                                </div>
                                                <div class="form-group col-md-1">
                                                    <label>Umbral</label>
                                                    <input type="number" step="0.0001" name="umbral" class="form-control form-control-sm" value="0">
                                                </div>
                                                <div class="form-group col-md-1">
                                                    <label>Hasta</label>
                                                    <input type="number" step="0.0001" name="umbral_hasta" class="form-control form-control-sm">
                                                </div>
                                                <div class="form-group col-md-1 d-flex align-items-end">
                                                    <div class="custom-control custom-checkbox mb-2">
                                                        <input type="checkbox" name="bloqueante" value="1" id="alerta-bloqueante" class="custom-control-input">
                                                        <label for="alerta-bloqueante" class="custom-control-label">Bloquea</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <button class="btn btn-outline-primary btn-sm" type="submit">Agregar control</button>
                                        </form>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead style="background:#85C1E9;color:#17202A;">
                                                <tr><th>Nombre</th><th>Tipo</th><th>Condición</th><th>Severidad</th><th></th></tr>
                                            </thead>
                                            <tbody>
                                            @forelse($alertas as $alerta)
                                                <tr>
                                                    <td>{{ $alerta->nombre }}</td>
                                                    <td>{{ $tiposAlerta[$alerta->tipo] ?? $alerta->tipo }}</td>
                                                    <td>C{{ $alerta->columna_nro ?: '—' }} {{ $alerta->operador }} {{ $alerta->umbral }}</td>
                                                    <td>{{ $alerta->bloqueante ? 'Bloqueante' : 'Advertencia' }}</td>
                                                    <td>
                                                        <form method="post" action="{{ route('eliminar_alerta_reporte_sueldos_definible', ['id' => $data->id, 'alertaId' => $alerta->id]) }}">
                                                            @csrf @method('DELETE')
                                                            <button class="btn-accion-tabla" type="submit" title="Eliminar"><i class="fa fa-times-circle text-danger"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="5" class="text-muted text-center">Sin controles configurados.</td></tr>
                                            @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade {{ $tabActiva === 'operacion' ? 'show active' : '' }}" id="tab-operacion" role="tabpanel">
                        <div class="mb-4">
                            <h5 class="mb-2">Ejecuciones</h5>
                            <p class="text-muted small">
                                Cada corrida conserva filtros, versión, resultado comprimido, hash y métricas.
                            </p>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead style="background:#85C1E9;color:#17202A;">
                                        <tr><th>#</th><th>Fecha</th><th>Origen</th><th>Estado</th><th>Filas</th><th>Duración</th><th>Usuario</th><th>Hash</th></tr>
                                    </thead>
                                    <tbody>
                                    @forelse($ejecuciones as $e)
                                        <tr class="{{ (int) $data->publicado_ejecucion_id === (int) $e->id ? 'table-success' : '' }}">
                                            <td>{{ $e->id }} @if((int) $data->publicado_ejecucion_id === (int) $e->id)<span class="badge badge-success">publicado</span>@endif</td>
                                            <td>{{ $e->created_at?->format('d/m/Y H:i') }}</td>
                                            <td>{{ $e->origen }}{{ $e->burst_etiqueta ? ': '.$e->burst_etiqueta : '' }}</td>
                                            <td>{{ $e->estado }} @if($e->advertencias_count)<span class="badge badge-warning">{{ $e->advertencias_count }}</span>@endif</td>
                                            <td>{{ $e->cantidad_filas }}</td>
                                            <td>{{ number_format($e->duracion_ms / 1000, 2, ',', '.') }} s</td>
                                            <td>{{ $e->usuario?->nombre ?? 'Sistema' }}</td>
                                            <td><code title="{{ $e->resultado_hash }}">{{ $e->resultado_hash ? substr($e->resultado_hash, 0, 12).'…' : '—' }}</code></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="text-center text-muted">Todavía no hay ejecuciones.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <h5 class="mb-2">Distribución</h5>
                        <p class="text-muted small">
                            Segmentación por centro de costo, lugar o agrupamiento. Cada segmento recibe solo sus filas.
                        </p>
                        @include('sueldos.reporte_definible.partials.distribucion_panel')
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@include('includes.sueldos.modalconsultaconcepto_sueldos')
@include('includes.sueldos.modalconsultaliquidacion_sueldos')
@include('includes.sueldos.modalconsultaasociado_reporte')
@endsection
