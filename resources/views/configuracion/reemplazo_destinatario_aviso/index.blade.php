@extends("theme.$theme.layout")
@section('titulo')
    Reemplazo destinatario aviso
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/reemplazo_destinatario_aviso/form.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php $modoOld = old('modo', 'reemplazo'); @endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Reemplazo / restauración de destinatario en avisos</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_modulo_aviso') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-envelope-o"></i> Avisos por módulo
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">
                    Uso típico: alguien se va de vacaciones o deja el puesto.
                    El reemplazo guarda al titular original en todos los avisos donde figura como destinatario
                    (usuario); al volver usá <strong>Restaurar titular</strong>.
                    No aplica a destinatarios solo por mail (sin usuario).
                </p>
                <p class="small text-muted mb-3">
                    Acceso actual (roles): {{ implode(', ', $rolesPermitidos ?? []) }}.
                </p>

                <form action="{{ route('aplicar_reemplazo_destinatario_aviso') }}" method="POST" id="form-reemplazo-aviso"
                      class="form-horizontal" autocomplete="off"
                      data-preview-url="{{ route('previsualizar_reemplazo_destinatario_aviso') }}">
                    @csrf

                    <div class="form-group">
                        <label class="d-block">Operación</label>
                        <div class="btn-group btn-group-toggle" data-toggle="buttons">
                            <label class="btn btn-outline-secondary {{ $modoOld === 'reemplazo' ? 'active' : '' }}" id="lbl-modo-reemplazo">
                                <input type="radio" name="modo" id="modo_reemplazo" value="reemplazo"
                                       {{ $modoOld === 'reemplazo' ? 'checked' : '' }}> Reemplazar (titular → suplente)
                            </label>
                            <label class="btn btn-outline-success {{ $modoOld === 'restaurar' ? 'active' : '' }}" id="lbl-modo-restaurar">
                                <input type="radio" name="modo" id="modo_restaurar" value="restaurar"
                                       {{ $modoOld === 'restaurar' ? 'checked' : '' }}> Restaurar titular (vuelve de vacaciones / puesto)
                            </label>
                        </div>
                    </div>

                    <div id="ra-bloque-reemplazo" class="{{ $modoOld === 'restaurar' ? 'd-none' : '' }}">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Usuario origen (titular actual / quien se va)</label>
                                    <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;" id="ra-origen-campo">
                                        <input type="hidden" name="usuario_origen_id" id="usuario_origen_id" class="usuario_id_arbol"
                                               value="{{ old('usuario_origen_id') }}">
                                        <input type="text" class="usuario_codigo_arbol form-control" style="flex: 0 0 120px;"
                                               placeholder="Código" value="{{ old('usuario_origen_codigo') }}" autocomplete="off">
                                        <button type="button" class="btn-accion-tabla consultausuario tooltipsC" title="Consultar usuario"
                                                data-omitir_filtro_empresa="1">
                                            <i class="fa fa-search text-primary"></i>
                                        </button>
                                        <input type="text" class="nombreusuario form-control" name="usuario_origen_nombre"
                                               value="{{ old('usuario_origen_nombre') }}" placeholder="Nombre" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Usuario destino (suplente / nuevo destinatario)</label>
                                    <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;" id="ra-destino-campo">
                                        <input type="hidden" name="usuario_destino_id" id="usuario_destino_id" class="usuario_id_arbol"
                                               value="{{ old('usuario_destino_id') }}">
                                        <input type="text" class="usuario_codigo_arbol form-control" style="flex: 0 0 120px;"
                                               placeholder="Código" value="{{ old('usuario_destino_codigo') }}" autocomplete="off">
                                        <button type="button" class="btn-accion-tabla consultausuario tooltipsC" title="Consultar usuario"
                                                data-omitir_filtro_empresa="1">
                                            <i class="fa fa-search text-primary"></i>
                                        </button>
                                        <input type="text" class="nombreusuario form-control" name="usuario_destino_nombre"
                                               value="{{ old('usuario_destino_nombre') }}" placeholder="Nombre" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @php
                            $conFechaTopeOld = (string) old('con_fecha_tope', '') === '1';
                            $venceElOld = old('vence_el', '');
                        @endphp
                        <div class="form-group border rounded p-3 bg-light">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="con_fecha_tope" name="con_fecha_tope" value="1"
                                       {{ $conFechaTopeOld ? 'checked' : '' }}>
                                <label class="custom-control-label" for="con_fecha_tope">
                                    Con fecha tope (restauración automática)
                                </label>
                            </div>
                            <div id="ra-vence-wrap" class="{{ $conFechaTopeOld ? '' : 'd-none' }}">
                                <label for="vence_el" class="mb-1">Último día del reemplazo</label>
                                <input type="date" class="form-control" style="max-width: 220px;" name="vence_el" id="vence_el"
                                       value="{{ $venceElOld }}" min="{{ now()->toDateString() }}">
                                <small class="form-text text-muted">
                                    Ese día el suplente sigue recibiendo avisos. La restauración automática corre a las 00:05 del día siguiente.
                                </small>
                            </div>
                            <small id="ra-sin-vence-ayuda" class="form-text text-muted {{ $conFechaTopeOld ? 'd-none' : '' }}">
                                Sin tilde: el reemplazo queda activo hasta que uses <strong>Restaurar titular</strong> manualmente.
                            </small>
                        </div>
                    </div>

                    <div id="ra-bloque-restaurar" class="{{ $modoOld === 'restaurar' ? '' : 'd-none' }}">
                        <div class="form-group">
                            <label>Titular a restaurar (quien vuelve)</label>
                            <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;" id="ra-titular-campo">
                                <input type="hidden" name="usuario_titular_id" id="usuario_titular_id" class="usuario_id_arbol"
                                       value="{{ old('usuario_titular_id') }}">
                                <input type="text" class="usuario_codigo_arbol form-control" style="flex: 0 0 120px;"
                                       placeholder="Código" value="{{ old('usuario_titular_codigo') }}" autocomplete="off">
                                <button type="button" class="btn-accion-tabla consultausuario tooltipsC" title="Consultar usuario"
                                        data-omitir_filtro_empresa="1">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" class="nombreusuario form-control" name="usuario_titular_nombre"
                                       value="{{ old('usuario_titular_nombre') }}" placeholder="Nombre" readonly>
                            </div>
                            <small class="text-muted">
                                Busca destinatarios donde este usuario quedó guardado como titular original
                                (<code>usuario_orig_id</code>) y se los devuelve, quitando al suplente.
                            </small>
                        </div>
                    </div>

                    <div class="form-group" id="ra-modulos-wrap">
                        <label>Módulos de aviso a incluir</label>
                        <div class="row">
                            @foreach ($modulos as $modulo)
                                <div class="col-md-4 col-lg-3 mb-1">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input ra-modulo"
                                               id="modulo_{{ $modulo['codigo'] }}" name="modulos[]" value="{{ $modulo['codigo'] }}"
                                               {{ in_array($modulo['codigo'], old('modulos', array_column($modulos, 'codigo')), true) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="modulo_{{ $modulo['codigo'] }}">
                                            {{ $modulo['nombre'] }}
                                            <small class="text-muted d-block">{{ $modulo['cantidad_tipos'] }} tipo(s)</small>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if (($modulos ?? []) === [])
                            <p class="text-muted small mb-0">No hay tipos de aviso cargados en esta instalación.</p>
                        @endif
                    </div>

                    <div class="mb-3">
                        <button type="button" id="btn-preview-reemplazo" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-eye"></i> Previsualizar impacto
                        </button>
                        @if ($puedeEjecutar)
                            <button type="submit" id="btn-aplicar-reemplazo" class="btn btn-danger btn-sm ml-2">
                                <i class="fa fa-exchange-alt"></i> <span id="ra-btn-aplicar-texto">Aplicar reemplazo</span>
                            </button>
                        @endif
                    </div>

                    <div id="ra-preview" class="d-none border rounded p-3 bg-light">
                        <h5 class="mb-2">Previsualización</h5>
                        <div id="ra-preview-body"></div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card card-secondary">
            <div class="card-header">
                <h3 class="card-title">Últimas operaciones</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Op.</th>
                            <th>Ejecutor</th>
                            <th>Origen / suplente</th>
                            <th>Destino / titular</th>
                            <th>Vence</th>
                            <th>Cierre</th>
                            <th>Destinatarios</th>
                            <th>Omitidos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($historial as $row)
                            <tr>
                                <td>{{ $row->id }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i') }}</td>
                                <td>
                                    @if (($row->operacion ?? 'reemplazo') === 'restaurar')
                                        <span class="badge badge-success">restaurar</span>
                                    @else
                                        <span class="badge badge-secondary">reemplazo</span>
                                    @endif
                                </td>
                                <td>{{ $row->ejecutor_nombre ?? '—' }}</td>
                                <td>{{ $row->origen_nombre ?? '—' }} <small class="text-muted">({{ $row->origen_usuario }})</small></td>
                                <td>{{ $row->destino_nombre }} <small class="text-muted">({{ $row->destino_usuario }})</small></td>
                                <td>
                                    @if (($row->operacion ?? '') === 'reemplazo' && ! empty($row->vence_el))
                                        {{ \Illuminate\Support\Carbon::parse($row->vence_el)->format('d/m/Y') }}
                                    @elseif (($row->operacion ?? '') === 'reemplazo')
                                        <span class="text-muted">manual</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if (! empty($row->restaurado_at))
                                        <small>
                                            {{ \Illuminate\Support\Carbon::parse($row->restaurado_at)->format('d/m/Y H:i') }}
                                            @if (! empty($row->restaurado_modo))
                                                <span class="badge badge-light">{{ $row->restaurado_modo }}</span>
                                            @endif
                                        </small>
                                    @elseif (($row->operacion ?? '') === 'reemplazo' && ! empty($row->vence_el))
                                        <span class="badge badge-warning">pendiente</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $row->conteo_destinatarios }}</td>
                                <td>{{ $row->conteo_omitidos }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted">Sin operaciones registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@include('includes.admin.modalconsultausuario')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'ra-reemplazo-overlay',
    'tituloId' => 'ra-reemplazo-overlay-titulo',
    'subtituloId' => 'ra-reemplazo-overlay-subtitulo',
    'titulo' => 'Aplicando reemplazo…',
    'subtitulo' => 'Puede demorar según la cantidad de avisos. No cierre la página.',
])
@endsection
