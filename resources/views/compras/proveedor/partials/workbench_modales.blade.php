{{-- Configuración de grilla estilo SIFAB: título, ver, ancho, alinea, orden + guardar vista --}}
@php
    use App\Support\Listado\ListadoGrillaConfigSupport;
    use App\Support\Compras\ProveedorListadoColumnas;
    $layout = $grillaLayout ?? [];
    $catalogo = $catalogoColumnas ?? ProveedorListadoColumnas::catalogoActivo();
    $filtrosHidden = $filtrosQuery ?? [];
    $qbeResumen = (array) ($filtros['qbe'] ?? []);
@endphp

<div class="modal fade lw-modal" id="modal-lw-grilla" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-th"></i> Configuración de grilla</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>

            <ul class="nav nav-tabs px-3 pt-2" id="lw-grilla-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="tab-grilla-columnas" data-toggle="tab" href="#pane-grilla-columnas" role="tab">Columnas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-grilla-vista" data-toggle="tab" href="#pane-grilla-vista" role="tab">Guardar vista</a>
                </li>
            </ul>

            <div class="tab-content">
                {{-- Tab Columnas --}}
                <div class="tab-pane fade show active" id="pane-grilla-columnas" role="tabpanel">
                    <form method="post" action="{{ route('guardar_columnas_listado_proveedor') }}" id="form-lw-grilla-aplicar">
                        @csrf
                        @foreach ($filtrosHidden as $hk => $hv)
                            @if (is_array($hv))
                                @foreach ($hv as $sk => $sv)
                                    @if (is_array($sv))
                                        @foreach ($sv as $ssk => $ssv)
                                            <input type="hidden" name="{{ $hk }}[{{ $sk }}][{{ $ssk }}]" value="{{ $ssv }}">
                                        @endforeach
                                    @else
                                        <input type="hidden" name="{{ $hk }}[{{ $sk }}]" value="{{ $sv }}">
                                    @endif
                                @endforeach
                            @elseif ($hk !== 'columnas')
                                <input type="hidden" name="{{ $hk }}" value="{{ $hv }}">
                            @endif
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
                            <div class="table-responsive" style="max-height: 55vh;">
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
                                                    <button type="button" class="btn btn-xs btn-outline-secondary lw-grilla-up" title="Subir">
                                                        <i class="fa fa-arrow-up"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-xs btn-outline-secondary lw-grilla-down" title="Bajar">
                                                        <i class="fa fa-arrow-down"></i>
                                                    </button>
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

                {{-- Tab Guardar vista --}}
                <div class="tab-pane fade" id="pane-grilla-vista" role="tabpanel">
                    <form method="post" action="{{ route('guardar_vista_listado_proveedor') }}" id="form-lw-grilla-vista">
                        @csrf
                        @foreach ($filtrosHidden as $hk => $hv)
                            @if (is_array($hv))
                                @foreach ($hv as $sk => $sv)
                                    @if (is_array($sv))
                                        @foreach ($sv as $ssk => $ssv)
                                            <input type="hidden" name="{{ $hk }}[{{ $sk }}][{{ $ssk }}]" value="{{ $ssv }}">
                                        @endforeach
                                    @else
                                        <input type="hidden" name="{{ $hk }}[{{ $sk }}]" value="{{ $sv }}">
                                    @endif
                                @endforeach
                            @elseif ($hk !== 'columnas' && $hk !== 'vista_id')
                                <input type="hidden" name="{{ $hk }}" value="{{ $hv }}">
                            @endif
                        @endforeach
                        <div id="lw-grilla-vista-clone"></div>

                        <div class="modal-body">
                            <p class="small text-muted">
                                La vista guarda <strong>grilla completa</strong> (títulos, visibles, anchos, alineación, orden)
                                + <strong>filtros QBE</strong> actuales. No crea ítem de menú.
                            </p>
                            @if ($qbeResumen !== [])
                                <div class="alert alert-light border small mb-3">
                                    <strong>Filtros que se guardan:</strong>
                                    <ul class="mb-0 pl-3">
                                        @foreach ($qbeResumen as $c)
                                            @if (is_array($c))
                                                <li>
                                                    {{ $etiquetasColumnas[$c['campo'] ?? ''] ?? ($c['campo'] ?? '') }}
                                                    {{ $c['op'] ?? 'contiene' }}
                                                    «{{ $c['valor'] ?? '' }}»
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                            @else
                                <div class="alert alert-light border small mb-3">Sin filtros QBE activos (se guarda la grilla sola).</div>
                            @endif

                            <div class="form-group">
                                <label for="vista_nombre_grilla">Nombre de la vista</label>
                                <input type="text" name="nombre" id="vista_nombre_grilla" class="form-control" required maxlength="120"
                                       value="{{ $vistaActiva->nombre ?? '' }}"
                                       placeholder="Ej. Proveedores con CBU — tesorería">
                            </div>
                            @if ($vistaActiva)
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" id="vista_actualizar_grilla" name="vista_id" value="{{ $vistaActiva->id }}" checked>
                                    <label class="custom-control-label" for="vista_actualizar_grilla">
                                        Actualizar «{{ $vistaActiva->nombre }}» (desmarcar para crear otra)
                                    </label>
                                </div>
                            @endif
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="vista_default_grilla" name="es_default" value="1"
                                       @if ($vistaActiva && $vistaActiva->es_default) checked @endif>
                                <label class="custom-control-label" for="vista_default_grilla">Usar como vista por defecto al abrir</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="vista_compartida_grilla" name="compartida" value="1"
                                       @if ($vistaActiva && $vistaActiva->compartida) checked @endif>
                                <label class="custom-control-label" for="vista_compartida_grilla">Compartir con otros usuarios</label>
                            </div>
                        </div>
                        <div class="modal-footer justify-content-between">
                            @if ($vistaActiva && (int) ($vistaActiva->usuario_id ?? 0) === (int) auth()->id())
                                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-lw-eliminar-vista-ref"
                                        data-action="{{ route('eliminar_vista_listado_proveedor', $vistaActiva->id) }}">
                                    Eliminar esta vista
                                </button>
                            @else
                                <span></span>
                            @endif
                            <div>
                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                                <button type="submit" class="btn btn-primary" id="btn-lw-guardar-vista">
                                    <i class="fa fa-save"></i> Guardar vista completa
                                </button>
                            </div>
                        </div>
                    </form>
                    @if ($vistaActiva && (int) ($vistaActiva->usuario_id ?? 0) === (int) auth()->id())
                        <form method="post" action="{{ route('eliminar_vista_listado_proveedor', $vistaActiva->id) }}" id="form-lw-eliminar-vista" class="d-none"
                              onsubmit="return confirm('¿Eliminar esta vista?');">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Etiquetas por defecto de la instalación (opcional, no por vista) --}}
<div class="modal fade lw-modal" id="modal-lw-etiquetas" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <form method="post" action="{{ route('guardar_etiquetas_listado_proveedor') }}">
                @csrf
                @foreach ($filtrosHidden as $hk => $hv)
                    @if (! is_array($hv) && $hk !== 'columnas')
                        <input type="hidden" name="{{ $hk }}" value="{{ $hv }}">
                    @endif
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
