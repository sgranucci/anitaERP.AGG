@php
    $favoritosAnclados = $favoritosAnclados ?? [];
    $typeSeleccionado = (string) ($filtros['auditable_type'] ?? '');
    $typesAnclados = collect($favoritosAnclados)->pluck('auditable_type')->all();
    $seleccionAnclada = $typeSeleccionado !== '' && in_array($typeSeleccionado, $typesAnclados, true);
@endphp

<div class="auditoria-fav-bar mb-3" id="auditoria-fav-bar"
     data-url-anclar="{{ route('auditoria_sesion_favorito_anclar') }}"
     data-url-desanclar="{{ route('auditoria_sesion_favorito_desanclar') }}"
     data-url-listar="{{ route('auditoria_sesion_favoritos') }}">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
        <div>
            <strong><i class="fas fa-thumbtack"></i> Mis favoritos</strong>
            <span class="text-muted small ml-1">chincheta por usuario (como la barra de tareas)</span>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-gestionar-fav-auditoria"
                data-toggle="modal" data-target="#modal-auditoria-favoritos">
            <i class="fas fa-thumbtack"></i> Gestionar favoritos
        </button>
    </div>
    <div id="auditoria-fav-chips" class="auditoria-fav-chips">
        @forelse ($favoritosAnclados as $fav)
            <a href="{{ route('auditoria_sesion', array_merge($filtrosQuery, [
                    'pestana' => 'datos',
                    'auditable_type' => $fav['auditable_type'],
                    'consultar' => 1,
                ])) }}"
               class="auditoria-fav-chip {{ $typeSeleccionado === $fav['auditable_type'] ? 'is-active' : '' }}"
               data-type="{{ $fav['auditable_type'] }}"
               title="{{ $fav['modulo'] }} · {{ $fav['tabla'] }}">
                <i class="fas fa-thumbtack"></i>
                <span>{{ $fav['etiqueta'] }}</span>
            </a>
        @empty
            <span class="text-muted small" id="auditoria-fav-vacio">Todavía no tenés favoritos. Usá la chincheta o «Gestionar favoritos».</span>
        @endforelse
    </div>
</div>

<form method="get" action="{{ route('auditoria_sesion') }}" class="mb-3" id="form-auditoria-datos">
    <input type="hidden" name="consultar" value="1">
    <input type="hidden" name="pestana" value="datos">

    <div class="alert alert-light border mb-3">
        <strong>Cómo usarlo:</strong>
        elegí el <em>modelo</em> (ej. Proveedor) y buscá el registro por <em>código, nombre o fantasia</em>
        (ej. <code>475</code> o <code>sysgran</code>) — no hace falta saber el id interno.
        Marcá con la chincheta los modelos que uses seguido.
    </div>

    <div class="form-row align-items-end">
        <div class="form-group col-md-2">
            <label for="fecha_desde_datos">Desde</label>
            <input type="date" class="form-control" id="fecha_desde_datos" name="fecha_desde"
                   value="{{ $filtros['fecha_desde'] ?? '' }}">
        </div>
        <div class="form-group col-md-2">
            <label for="fecha_hasta_datos">Hasta</label>
            <input type="date" class="form-control" id="fecha_hasta_datos" name="fecha_hasta"
                   value="{{ $filtros['fecha_hasta'] ?? '' }}">
        </div>
        <div class="form-group col-md-4">
            <label for="auditable_type">Modelo / tabla</label>
            <div class="input-group">
                <select class="form-control" id="auditable_type" name="auditable_type">
                    <option value="">— Elegir (recomendado) —</option>
                    <optgroup label="Favoritos" id="optgroup-favoritos-auditoria">
                        @foreach ($catalogoDatos as $item)
                            @if (! empty($item['favorito']))
                                <option value="{{ $item['type'] }}" @selected($typeSeleccionado === $item['type'])>
                                    {{ $item['etiqueta'] }} ({{ $item['tabla'] }})
                                    @if ($item['eventos'] !== null)
                                        — {{ number_format($item['eventos'], 0, ',', '.') }}
                                    @endif
                                </option>
                            @endif
                        @endforeach
                    </optgroup>
                    @php
                        $modulosOtros = collect($catalogoDatos)->where('favorito', false)->groupBy('modulo');
                    @endphp
                    @foreach ($modulosOtros as $modulo => $items)
                        <optgroup label="{{ $modulo }}">
                            @foreach ($items as $item)
                                <option value="{{ $item['type'] }}" @selected($typeSeleccionado === $item['type'])>
                                    {{ $item['etiqueta'] }} ({{ $item['tabla'] }})
                                    @if ($item['eventos'] !== null)
                                        — {{ number_format($item['eventos'], 0, ',', '.') }}
                                    @endif
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <div class="input-group-append">
                    <button type="button"
                            class="btn btn-outline-secondary auditoria-pin-btn {{ $seleccionAnclada ? 'is-pinned' : '' }}"
                            id="btn-pin-modelo-actual"
                            title="{{ $seleccionAnclada ? 'Quitar de favoritos' : 'Anclar en favoritos' }}"
                            {{ $typeSeleccionado === '' ? 'disabled' : '' }}>
                        <i class="fas fa-thumbtack"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="form-group col-md-3">
            <label for="registro_busqueda">Buscar registro</label>
            <input type="text" class="form-control" id="registro_busqueda" name="registro_busqueda"
                   value="{{ $filtros['registro_busqueda'] ?? '' }}"
                   placeholder="código, nombre… ej. 475 o sysgran"
                   autocomplete="off"
                   data-url-buscar="{{ route('auditoria_sesion_buscar_registro') }}">
            <input type="hidden" name="auditable_id" id="auditable_id" value="{{ $filtros['auditable_id'] ?? '' }}">
            <small class="form-text text-muted">Si conocés el id interno, también sirve.</small>
        </div>
        <div class="form-group col-md-1">
            <label for="event">Evento</label>
            <select class="form-control" id="event" name="event">
                <option value="">Todos</option>
                @foreach (['created' => 'Alta', 'updated' => 'Modificación', 'deleted' => 'Baja', 'restored' => 'Restaurado'] as $val => $lab)
                    <option value="{{ $val }}" @selected(($filtros['event'] ?? '') === $val)>{{ $lab }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if (! empty($registroResuelto))
        <div class="alert alert-success py-2 d-flex flex-wrap align-items-center justify-content-between">
            <div>
                Registro:
                <strong>{{ $registroResuelto['etiqueta'] }}</strong>
                <span class="text-muted">({{ $registroResuelto['extra'] }})</span>
            </div>
            @if (! empty($registroResuelto['abm_link']['url']))
                <a class="btn btn-info btn-sm"
                   href="{{ $registroResuelto['abm_link']['url'] }}"
                   target="_blank" rel="noopener">
                    <i class="fa fa-edit"></i> {{ $registroResuelto['abm_link']['etiqueta'] }}
                </a>
            @endif
        </div>
    @endif

    @if (! empty($registroCandidatos))
        <div class="card card-outline card-warning mb-3">
            <div class="card-header py-2">
                <strong>Elegí el registro</strong>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @foreach ($registroCandidatos as $cand)
                        <a class="list-group-item list-group-item-action"
                           href="{{ route('auditoria_sesion', array_merge($filtrosQuery, [
                               'pestana' => 'datos',
                               'consultar' => 1,
                               'auditable_type' => $filtros['auditable_type'],
                               'auditable_id' => $cand['id'],
                               'registro_busqueda' => $filtros['registro_busqueda'] ?? '',
                           ])) }}">
                            <strong>{{ $cand['etiqueta'] }}</strong>
                            <span class="text-muted small">{{ $cand['extra'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
    <div class="form-row align-items-end">
        @include('configuracion.auditoria_sesion.partials.campo_filtro_usuario', [
            'label' => 'Usuario que modificó',
            'colClass' => 'col-md-4',
            'campoId' => 'usuario_id_datos',
            'campoCodigoId' => 'usuario_codigo_datos',
            'campoNombreId' => 'nombreusuario_datos',
            'usuarioIdVal' => $usuarioFiltro->id ?? ($filtros['usuario_id'] ?? ''),
            'usuarioCodigoVal' => $usuarioFiltro->usuario ?? '',
            'usuarioNombreVal' => $usuarioFiltro->nombre ?? '',
            'omitirFiltroEmpresa' => true,
        ])
        <div class="form-group col-md-3">
            <label for="campo">Campo (opcional)</label>
            <input type="text" class="form-control" id="campo" name="campo"
                   value="{{ $filtros['campo'] ?? '' }}" placeholder="ej. cuit, razon_social">
        </div>
        <div class="form-group col-md-2">
            <button type="submit" class="btn btn-primary btn-block">
                <i class="fa fa-search"></i> Consultar cambios
            </button>
        </div>
    </div>
</form>

{{-- Modal gestionar favoritos (mismo espíritu que anclar menú a barra de tareas) --}}
<div class="modal fade" id="modal-auditoria-favoritos" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-thumbtack"></i> Anclar modelos a favoritos</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Clic en la chincheta para anclar o quitar. Máximo {{ \App\Support\Configuracion\AuditoriaDatosFavoritoSupport::MAX_FAVORITOS }}.
                    Son tuyos: no afectan a otros usuarios.
                </p>
                <input type="search" class="form-control form-control-sm mb-3" id="filtro-modal-fav-auditoria"
                       placeholder="Filtrar por nombre, tabla o módulo…">
                <div id="lista-modal-fav-auditoria">
                    @foreach (collect($catalogoDatos)->groupBy('modulo') as $modulo => $items)
                        <div class="auditoria-fav-mod-block mb-3" data-modulo="{{ $modulo }}">
                            <h6 class="text-uppercase text-muted small mb-2">{{ $modulo }}</h6>
                            @foreach ($items as $item)
                                <div class="auditoria-fav-mod-row d-flex align-items-center justify-content-between py-1 border-bottom"
                                     data-type="{{ $item['type'] }}"
                                     data-search="{{ strtolower($item['etiqueta'].' '.$item['tabla'].' '.$item['modulo']) }}">
                                    <div>
                                        <strong>{{ $item['etiqueta'] }}</strong>
                                        <span class="text-muted small">({{ $item['tabla'] }})</span>
                                    </div>
                                    <button type="button"
                                            class="btn btn-sm auditoria-pin-btn {{ ! empty($item['favorito']) ? 'is-pinned' : '' }}"
                                            data-type="{{ $item['type'] }}"
                                            data-nombre="{{ $item['etiqueta'] }}"
                                            title="{{ ! empty($item['favorito']) ? 'Quitar de favoritos' : 'Anclar en favoritos' }}">
                                        <i class="fas fa-thumbtack"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@if ($avisoDatos && empty($registroCandidatos))
    <div class="alert alert-info {{ $coleccionDatos ? 'mb-3' : 'mb-0' }}">{{ $avisoDatos }}</div>
@endif

@if ($coleccionDatos === null && empty($registroCandidatos) && ! $avisoDatos)
    <div class="alert alert-light border mb-0">Completá los filtros y consultá.</div>
@elseif ($coleccionDatos !== null && $coleccionDatos->isEmpty())
    <div class="alert alert-light border mb-0">No hay cambios con esos criterios.</div>
@elseif ($coleccionDatos !== null)
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-muted small">
            Mostrando {{ $coleccionDatos->firstItem() }}–{{ $coleccionDatos->lastItem() }}
            @if (($filtros['auditable_type'] ?? '') !== '')
                de {{ number_format($coleccionDatos->total(), 0, ',', '.') }} cambios
            @else
                (sin total exacto: filtrá por modelo para el conteo completo)
            @endif
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered auditoria-tabla mb-2" id="tabla-paginada">
            <thead>
                <tr>
                    <th>Fecha/hora</th>
                    <th>Usuario</th>
                    <th>Evento</th>
                    <th>Modelo</th>
                    <th>ID</th>
                    <th>Campos tocados</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($coleccionDatos as $row)
                    @php
                        $diff = $row->diff ?? [];
                        $camposLista = [];
                        foreach ($diff as $dItem) {
                            if (count($camposLista) >= 6) {
                                break;
                            }
                            $camposLista[] = $dItem['campo'];
                        }
                        $campos = implode(', ', $camposLista);
                        if (count($diff) > 6) {
                            $campos .= '…';
                        }
                        $detalleId = 'audit-diff-'.$row->id;
                        $evCls = 'badge-tipo-navegacion';
                        if ($row->event === 'created') {
                            $evCls = 'badge-tipo-login';
                        } elseif ($row->event === 'deleted') {
                            $evCls = 'badge-tipo-logout';
                        }
                        $abmLink = $row->abm_link ?? null;
                    @endphp
                    <tr>
                        <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i:s') }}</td>
                        <td>
                            @if (! empty($row->usuario_nombre))
                                {{ $row->usuario_nombre }}
                                <span class="text-muted small">#{{ $row->user_id }}</span>
                            @elseif ($row->user_id)
                                #{{ $row->user_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $evCls }}">{{ $row->event }}</span>
                        </td>
                        <td>
                            <span title="{{ $row->auditable_type }}">{{ $row->etiqueta_tipo }}</span>
                        </td>
                        <td class="text-right">{{ $row->auditable_id }}</td>
                        <td style="max-width:260px;">{{ $campos !== '' ? $campos : '—' }}</td>
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-xs btn-outline-primary"
                                    data-toggle="collapse" data-target="#{{ $detalleId }}">
                                Ver cambios
                            </button>
                            @if (! empty($abmLink['url']))
                                <a class="btn btn-xs btn-info"
                                   href="{{ $abmLink['url'] }}"
                                   target="_blank" rel="noopener"
                                   title="Abrir ABM en modo consulta (sin menú)">
                                    <i class="fa fa-edit"></i>
                                </a>
                            @endif
                        </td>
                    </tr>
                    <tr class="collapse" id="{{ $detalleId }}">
                        <td colspan="7" class="bg-light">
                            @if ($diff === [])
                                <span class="text-muted">Sin diferencias de campos (o payload vacío).</span>
                            @else
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:22%;">Campo</th>
                                            <th style="width:39%;">Antes</th>
                                            <th style="width:39%;">Después</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($diff as $d)
                                            <tr>
                                                <td><code>{{ $d['campo'] }}</code></td>
                                                <td class="text-danger" style="word-break:break-word;">
                                                    {{ \App\Support\Configuracion\AuditoriaDatosListadoFiltros::formatearValor($d['antes']) }}
                                                </td>
                                                <td class="text-success" style="word-break:break-word;">
                                                    {{ \App\Support\Configuracion\AuditoriaDatosListadoFiltros::formatearValor($d['despues']) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                            @if (! empty($row->url))
                                <div class="small text-muted mt-2">URL: {{ $row->url }} · IP: {{ $row->ip_address ?? '—' }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div>
        {{ $coleccionDatos->links() }}
    </div>
@endif
