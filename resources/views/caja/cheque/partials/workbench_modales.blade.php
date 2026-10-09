{{-- Configuración de grilla estilo SIFAB: título, ver, ancho, alinea, orden + guardar vista --}}
@php
    use App\Support\Listado\ListadoGrillaConfigSupport;
    use App\Support\Caja\ChequeListadoColumnas;
    $layout = $grillaLayout ?? [];
    $catalogo = $catalogoColumnas ?? ChequeListadoColumnas::catalogoActivo();
    $filtrosHidden = $filtrosQuery ?? [];
    $qbeResumen = \App\Support\Listado\ListadoQbeSupport::paraUi($filtros['qbe'] ?? []);
@endphp

<div class="modal fade lw-modal" id="modal-lw-grilla" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable lw-modal-disenador" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa fa-th"></i> Diseñador de vista
                    @if ($vistaActiva)
                        <span class="lw-vista-contexto-badge" title="Vista abierta">{{ $vistaActiva->nombre }}</span>
                    @else
                        <span class="lw-vista-contexto-badge lw-vista-contexto-badge--std">Vista estándar</span>
                    @endif
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>

            <div class="lw-disenador-shell">
                <div class="lw-disenador-main">
                    <ul class="nav nav-tabs px-3 pt-2 lw-grilla-tabs" id="lw-grilla-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-grilla-columnas" data-toggle="tab" href="#pane-grilla-columnas" role="tab">
                                <i class="fa fa-columns"></i> Columnas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-grilla-ordenar" data-toggle="tab" href="#pane-grilla-ordenar" role="tab">
                                <i class="fa fa-sort-amount-down"></i> Ordenar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-grilla-agrupar" data-toggle="tab" href="#pane-grilla-agrupar" role="tab">
                                <i class="fa fa-object-group"></i> Agrupar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-grilla-vista" data-toggle="tab" href="#pane-grilla-vista" role="tab">
                                <i class="fa fa-bookmark"></i> Guardar vista
                            </a>
                        </li>
                    </ul>

                    <div class="lw-vista-contexto" role="status">
                        <i class="fa fa-bookmark-o" aria-hidden="true"></i>
                        <span>Vista actual</span>
                        @if ($vistaActiva)
                            <strong>{{ $vistaActiva->nombre }}</strong>
                        @else
                            <strong>Estándar</strong>
                        @endif
                    </div>

                    <div class="tab-content">
                        {{-- Tab Columnas --}}
                        <div class="tab-pane fade show active" id="pane-grilla-columnas" role="tabpanel">
                            <form method="post" action="{{ route('guardar_columnas_listado_cheque') }}" id="form-lw-grilla-aplicar">
                                @csrf
                                @foreach ($filtrosHidden as $hk => $hv)
                                    @if ($hk === 'columnas')
                                        @continue
                                    @endif
                                    @include('includes.listado.hidden_nested', [
                                        'prefix' => '',
                                        'data' => [$hk => $hv],
                                    ])
                                @endforeach
                                @if ($vistaActiva)
                                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                                    <input type="hidden" name="actualizar_vista" value="1">
                                @endif

                                <div class="modal-body p-0">
                                    <div class="px-3 pt-2 pb-1 small text-muted">
                                        Como SIFAB: título editable, visible, ancho preferido (px) y alineación.
                                        En pantalla los anchos se ajustan al ancho útil (máx. ~{{ \App\Support\Listado\ListadoGrillaConfigSupport::ANCHO_PRESUPUESTO_PANTALLA }}&nbsp;px) respetando un piso de legibilidad; si hay demasiadas columnas aparece scroll horizontal. Flechas reordenan.
                                        @if ($vistaActiva)
                                            · Al aplicar se actualiza la vista «{{ $vistaActiva->nombre }}».
                                        @endif
                                    </div>
                                    <div class="table-responsive lw-grilla-config-scroll">
                                        <table class="table table-sm table-bordered mb-0 lw-grilla-config-table" id="lw-grilla-config-table">
                                            <thead style="background:#85C1E9;color:#17202A;position:sticky;top:0;z-index:1;">
                                                <tr>
                                                    <th style="width:40px;">#</th>
                                                    <th>Campo</th>
                                                    <th style="min-width:160px;">Título</th>
                                                    <th style="width:70px;" class="text-center">Ver</th>
                                                    <th style="width:90px;">Ancho</th>
                                                    <th style="width:120px;">Alinea</th>
                                                    <th style="width:80px;" class="text-center">Orden</th>
                                                </tr>
                                            </thead>
                                            <tbody id="lw-grilla-config-tbody">
                                                @foreach ($layout as $i => $fila)
                                                    @php
                                                        $key = $fila['key'];
                                                        $meta = $catalogo[$key] ?? ['label' => $key];
                                                    @endphp
                                                    <tr data-key="{{ $key }}" class="lw-grilla-fila">
                                                        <td class="text-muted lw-grilla-orden-num">{{ $i + 1 }}</td>
                                                        <td>
                                                            <code class="small">{{ $key }}</code>
                                                            <div class="text-muted" style="font-size:0.7rem;">{{ $meta['label'] ?? '' }}</div>
                                                            <input type="hidden" name="grilla[{{ $i }}][key]" value="{{ $key }}" class="lw-grilla-key">
                                                            <input type="hidden" name="grilla[{{ $i }}][orden]" value="{{ $i }}" class="lw-grilla-orden">
                                                        </td>
                                                        <td>
                                                            <input type="text" name="grilla[{{ $i }}][titulo]" class="form-control form-control-sm"
                                                                   value="{{ $fila['titulo'] }}" maxlength="120">
                                                        </td>
                                                        <td class="text-center align-middle">
                                                            <input type="hidden" name="grilla[{{ $i }}][visible]" value="0">
                                                            <input type="checkbox" name="grilla[{{ $i }}][visible]" value="1"
                                                                   @if (! empty($fila['visible'])) checked @endif>
                                                        </td>
                                                        <td>
                                                            <input type="number" name="grilla[{{ $i }}][ancho]" class="form-control form-control-sm"
                                                                   value="{{ $fila['ancho'] }}" min="40" max="600" step="10">
                                                        </td>
                                                        <td>
                                                            <select name="grilla[{{ $i }}][alinea]" class="form-control form-control-sm">
                                                                <option value="izquierda" @if ($fila['alinea'] === 'izquierda') selected @endif>Izquierda</option>
                                                                <option value="centro" @if ($fila['alinea'] === 'centro') selected @endif>Centro</option>
                                                                <option value="derecha" @if ($fila['alinea'] === 'derecha') selected @endif>Derecha</option>
                                                            </select>
                                                        </td>
                                                        <td class="text-center text-nowrap">
                                                            <div class="lw-grilla-orden-btns">
                                                                <button type="button" class="btn btn-sm btn-outline-primary lw-grilla-up" title="Subir columna">
                                                                    <i class="fa fa-chevron-up"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-primary lw-grilla-down" title="Bajar columna">
                                                                    <i class="fa fa-chevron-down"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="px-3 py-2 small text-muted border-top">
                                        Total de campos: {{ count($layout) }}
                                        · Visibles: {{ collect($layout)->where('visible', true)->count() }}
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-check"></i> Aplicar grilla
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- Tab Ordenar — editor gráfico real (monta #lw-orden-panel) --}}
                        <div class="tab-pane fade" id="pane-grilla-ordenar" role="tabpanel">
                            <div class="modal-body lw-orden-modal-shell">
                                <p class="small text-muted mb-3">
                                    Definí la secuencia de orden con iconos <strong>Asc</strong> / <strong>Desc</strong>.
                                    La prioridad es de arriba hacia abajo. Al aplicar se refresca el listado.
                                </p>
                                <div class="lw-orden-modal-placeholder" id="lw-orden-modal-placeholder">
                                    <i class="fa fa-sort-amount-down"></i>
                                    <span>Abrí esta pestaña para cargar el editor de orden…</span>
                                </div>
                                <div id="lw-orden-modal-mount"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                                <button type="button" class="btn btn-primary" id="btn-lw-aplicar-orden-modal">
                                    <i class="fa fa-check"></i> Aplicar orden
                                </button>
                            </div>
                        </div>

                        {{-- Tab Agrupar — editor gráfico real (monta #lw-group-panel) --}}
                        <div class="tab-pane fade" id="pane-grilla-agrupar" role="tabpanel">
                            <div class="modal-body lw-orden-modal-shell">
                                <p class="small text-muted mb-3">
                                    Elegí hasta 2 niveles de agrupación. La prioridad es de arriba hacia abajo.
                                    Al aplicar se refresca el listado con cabeceras de grupo.
                                </p>
                                <div class="lw-orden-modal-placeholder" id="lw-group-modal-placeholder">
                                    <i class="fa fa-object-group"></i>
                                    <span>Abrí esta pestaña para cargar el editor de agrupación…</span>
                                </div>
                                <div id="lw-group-modal-mount"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                                <button type="button" class="btn btn-primary" id="btn-lw-aplicar-group-modal">
                                    <i class="fa fa-check"></i> Aplicar agrupación
                                </button>
                            </div>
                        </div>

                        {{-- Tab Guardar vista --}}
                        <div class="tab-pane fade" id="pane-grilla-vista" role="tabpanel">
                            <form method="post" action="{{ route('guardar_vista_listado_cheque') }}" id="form-lw-grilla-vista"
                                  data-vista-nombre="{{ $vistaActiva->nombre ?? '' }}">
                                @csrf
                                @foreach ($filtrosHidden as $hk => $hv)
                                    @if (in_array($hk, ['columnas', 'vista_id'], true))
                                        @continue
                                    @endif
                                    @include('includes.listado.hidden_nested', [
                                        'prefix' => '',
                                        'data' => [$hk => $hv],
                                    ])
                                @endforeach
                                <div id="lw-grilla-vista-clone"></div>

                                <div class="modal-body">
                                    <p class="small text-muted">
                                        La vista guarda <strong>grilla</strong> + <strong>filtros QBE</strong> (grupos / fórmulas)
                                        + <strong>orden</strong> + <strong>agrupación</strong>. Opcionalmente crea un atajo en el menú.
                                    </p>
                                    @if (\App\Support\Listado\ListadoQbeSupport::tieneCriterios($qbeResumen))
                                        <div class="alert alert-light border small mb-3">
                                            <strong>Filtros que se guardan:</strong>
                                            <ul class="mb-0 pl-3">
                                                @foreach ($qbeResumen['grupos'] as $gi => $grupo)
                                                    @if ($gi > 0)
                                                        <li class="text-muted list-unstyled" style="list-style:none;margin-left:-1.25rem;">
                                                            {{ ($qbeResumen['entre_grupos'] ?? 'and') === 'or' ? 'O' : 'Y' }}
                                                        </li>
                                                    @endif
                                                    <li>
                                                        @if (! empty($grupo['not']))
                                                            NOT
                                                        @endif
                                                        ({{ ($grupo['logic'] ?? 'and') === 'or' ? 'Alguno' : 'Todos' }})
                                                        <ul class="mb-1 pl-3">
                                                            @foreach (($grupo['criterios'] ?? []) as $c)
                                                                @if (\App\Support\Listado\ListadoQbeSupport::operadorSinValor((string) ($c['op'] ?? '')) || ($c['op'] ?? '') === 'entre' || trim((string) ($c['valor'] ?? '')) !== '' || trim((string) ($c['formula'] ?? '')) !== '')
                                                                    <li>
                                                                        @if (trim((string) ($c['formula'] ?? '')) !== '')
                                                                            <code>{{ $c['formula'] }}</code>
                                                                        @else
                                                                            {{ $etiquetasColumnas[$c['campo'] ?? ''] ?? ($c['campo'] ?? '') }}
                                                                        @endif
                                                                        {{ $c['op'] ?? 'contiene' }}
                                                                        @if (($c['op'] ?? '') === 'entre')
                                                                            «{{ $c['valor'] ?? '' }}»…«{{ $c['valor_hasta'] ?? '' }}»
                                                                        @elseif (! \App\Support\Listado\ListadoQbeSupport::operadorSinValor((string) ($c['op'] ?? '')))
                                                                            «{{ $c['valor'] ?? '' }}»
                                                                        @endif
                                                                    </li>
                                                                @endif
                                                            @endforeach
                                                        </ul>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @else
                                        <div class="alert alert-light border small mb-3">Sin filtros QBE activos (se guarda la grilla sola).</div>
                                    @endif

                                    @php
                                        $ordenResumen = \App\Support\Listado\ListadoOrdenamientoSupport::normalizar(
                                            $filtros['sort'] ?? [],
                                            \App\Support\Caja\ChequeListadoFiltros::camposOrdenables()
                                        );
                                    @endphp
                                    @if ($ordenResumen !== [])
                                        <div class="alert alert-light border small mb-3">
                                            <strong>Orden que se guarda:</strong>
                                            <ol class="mb-0 pl-3">
                                                @foreach ($ordenResumen as $oc)
                                                    <li>
                                                        {{ $etiquetasColumnas[$oc['campo']] ?? $oc['campo'] }}
                                                        ({{ $oc['dir'] === 'desc' ? 'descendente' : 'ascendente' }})
                                                    </li>
                                                @endforeach
                                            </ol>
                                        </div>
                                    @endif

                                    <div class="form-group mb-2">
                                        <label for="vista_nombre_grilla">Nombre de la vista</label>
                                        @if ($vistaActiva)
                                            <div class="lw-vista-modo mb-2" role="group" aria-label="Guardar la vista abierta o crear otra">
                                                <button type="button" class="lw-vista-modo-btn is-active" data-modo="actualizar">
                                                    <i class="fa fa-refresh"></i> Actualizar esta vista
                                                </button>
                                                <button type="button" class="lw-vista-modo-btn" data-modo="nueva">
                                                    <i class="fa fa-plus"></i> Guardar como nueva
                                                </button>
                                            </div>
                                            <input type="hidden" name="vista_id" id="lw-vista-id-guardar" value="{{ $vistaActiva->id }}">
                                            <p class="small text-muted mb-2" id="lw-vista-modo-hint">Se actualiza «{{ $vistaActiva->nombre }}». El nombre se puede corregir.</p>
                                        @endif
                                        <input type="text" name="nombre" id="vista_nombre_grilla" class="form-control" required maxlength="120"
                                               value="{{ $vistaActiva->nombre ?? '' }}"
                                               placeholder="Ej. Cheques a vencer — tesorería">
                                    </div>
                                    <div class="custom-control custom-checkbox mb-2">
                                        <input type="checkbox" class="custom-control-input" id="vista_default_grilla" name="es_default" value="1"
                                               @if ($vistaActiva && $vistaActiva->es_default) checked @endif>
                                        <label class="custom-control-label" for="vista_default_grilla">Usar como vista por defecto al abrir</label>
                                    </div>
                                    <div class="custom-control custom-checkbox mb-2">
                                        <input type="checkbox" class="custom-control-input" id="vista_compartida_grilla" name="compartida" value="1"
                                               @if ($vistaActiva && $vistaActiva->compartida) checked @endif>
                                        <label class="custom-control-label" for="vista_compartida_grilla">Compartir con otros usuarios</label>
                                    </div>
                                    @if (\App\Support\Listado\ListadoVistaMenuSupport::columnaMenuDisponible())
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="vista_menu_grilla" name="crear_en_menu" value="1"
                                                   @if ($vistaActiva && (int) ($vistaActiva->menu_id ?? 0) > 0) checked @endif>
                                            <label class="custom-control-label" for="vista_menu_grilla">
                                                Agregar atajo en el menú (junto a Cheques)
                                            </label>
                                        </div>
                                    @endif
                                </div>
                                <div class="modal-footer justify-content-between">
                                    @if ($vistaActiva && (int) ($vistaActiva->usuario_id ?? 0) === (int) auth()->id())
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="btn-lw-eliminar-vista-ref"
                                                data-action="{{ route('eliminar_vista_listado_cheque', $vistaActiva->id) }}">
                                            Eliminar esta vista
                                        </button>
                                    @else
                                        <span></span>
                                    @endif
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                                        <button type="submit" class="btn btn-primary" id="btn-lw-guardar-vista">
                                            @if ($vistaActiva)
                                                <i class="fa fa-save"></i> Actualizar vista
                                            @else
                                                <i class="fa fa-plus"></i> Crear vista
                                            @endif
                                        </button>
                                    </div>
                                </div>
                            </form>
                            @if ($vistaActiva && (int) ($vistaActiva->usuario_id ?? 0) === (int) auth()->id())
                                <form method="post" action="{{ route('eliminar_vista_listado_cheque', $vistaActiva->id) }}" id="form-lw-eliminar-vista" class="d-none"
                                      onsubmit="return confirm('¿Eliminar esta vista?');">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                @include('includes.listado.disenador_preview', [
                    'previewUrl' => route('preview_workbench_cheque'),
                ])
            </div>
        </div>
    </div>
</div>

{{-- Etiquetas por defecto de la instalación (opcional, no por vista) --}}
<div class="modal fade lw-modal" id="modal-lw-etiquetas" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <form method="post" action="{{ route('guardar_etiquetas_listado_cheque') }}">
                @csrf
                @foreach ($filtrosHidden as $hk => $hv)
                    @if ($hk === 'columnas' || is_array($hv))
                        @continue
                    @endif
                    @include('includes.listado.hidden_nested', [
                        'prefix' => '',
                        'data' => [$hk => $hv],
                    ])
                @endforeach
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-font"></i> Etiquetas por defecto (instalación)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Defaults de la instalación (ej. Transporte → Reparto). Las <strong>vistas</strong> pueden sobreescribir el título
                        en la configuración de grilla.
                    </p>
                    @foreach ($catalogo as $key => $meta)
                        <div class="form-group row mb-2 align-items-center">
                            <label class="col-sm-4 col-form-label text-right pr-2 small text-muted">{{ $meta['label'] }}</label>
                            <div class="col-sm-8">
                                <input type="text" name="etiquetas[{{ $key }}]" class="form-control form-control-sm"
                                       value="{{ ($etiquetasInstalacion[$key] ?? $meta['label']) }}"
                                       maxlength="120"
                                       placeholder="{{ $meta['label'] }}">
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar defaults</button>
                </div>
            </form>
        </div>
    </div>
</div>
