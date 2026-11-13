@php
    $puedeEditar = (bool) ($puedeEditar ?? false);
    $puedeEmitir = (bool) ($puedeEmitir ?? false);
    $hayFuenteWord = (bool) ($hayFuenteWord ?? false);
@endphp
<div id="contratos-panel">
    <div class="card card-outline card-info mb-3">
        <div class="card-header py-2">
            <h3 class="card-title mb-0"><i class="fa fa-file-contract"></i> Períodos de contrato</h3>
        </div>
        <div class="card-body">
            @if ($puedeEditar)
                <div class="mb-2 d-flex align-items-center justify-content-between">
                    <strong id="contrato-form-titulo">Nuevo contrato</strong>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btn-contrato-cancelar-edicion">
                        Cancelar edición
                    </button>
                </div>
                <form id="form-contrato-nuevo" class="mb-3" data-enter-campos="1">
                    <input type="hidden" name="contrato_id" id="contrato_edit_id" value="">
                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label class="small mb-0">Desde</label>
                            <input type="date" name="desde_fecha" id="contrato_desde_fecha" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-0">Hasta</label>
                            <input type="date" name="hasta_fecha" id="contrato_hasta_fecha" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-0">Modalidad</label>
                            <select name="modalidad" id="contrato_modalidad" class="form-control form-control-sm">
                                <option value="">—</option>
                                @foreach ($modalidades as $cod => $lab)
                                    <option value="{{ $cod }}" @selected((string) ($empleado->modalidad_sijp ?? '') === (string) $cod)>{{ $cod }} — {{ $lab }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-0">Motivo</label>
                            <select name="motivo" id="contrato_motivo" class="form-control form-control-sm">
                                <option value="">—</option>
                                @foreach ($motivos as $k => $lab)
                                    <option value="{{ $k }}">{{ $lab }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-0">Días</label>
                            <input type="number" name="dias" id="contrato_dias" class="form-control form-control-sm" min="0" step="1">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-0">Liquidación</label>
                            <input type="number" name="liquidacion" id="contrato_liquidacion" class="form-control form-control-sm" min="0" step="1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="small mb-0">Comentario</label>
                        <input type="text" name="comentario" id="contrato_comentario" class="form-control form-control-sm" maxlength="160"
                               placeholder="Motivo / cobertura del contrato eventual…">
                    </div>
                    <input type="hidden" name="categoria_id" id="contrato_categoria_id" value="{{ $empleado->categoria_id ?? '' }}">
                    <input type="hidden" name="centrocosto_id" id="contrato_centrocosto_id" value="{{ $empleado->centrocosto_id ?? '' }}">
                    <div class="mb-2">
                        @include('sueldos.partials.campo_consulta_empleado_sueldos', [
                            'prefix' => 'contrato_reemp',
                            'label' => 'Legajo reemplazado (si motivo = Reemplazo)',
                            'idInputName' => 'empleado_reemplazo_id',
                            'empleadoId' => '',
                            'legajo' => '',
                            'nombre' => '',
                            'required' => false,
                            'layout' => 'compact',
                        ])
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-contrato-guardar">
                        <i class="fa fa-plus"></i> <span>Agregar contrato</span>
                    </button>
                </form>
            @endif

            @if ($puedeEmitir)
                <div class="form-row align-items-end mb-2">
                    <div class="form-group col-md-5 mb-0">
                        <label class="small mb-0" for="contrato_plantilla_emision">Plantilla al previsualizar / emitir</label>
                        <select id="contrato_plantilla_emision" class="form-control form-control-sm">
                            <option value="">Automática por reglas (tipo + modalidad + sindicato + empresa)</option>
                            @foreach ($plantillas as $p)
                                <option value="{{ $p->id }}">{{ $p->codigo }} — {{ $p->tipo_label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-7 mb-0">
                        <p class="small text-muted mb-0">
                            Si la regla no matchea el sindicato/modalidad del legajo, elegí la plantilla acá.
                            La emisión genera <strong>PDF</strong>; la plantilla Word queda solo como origen.
                        </p>
                    </div>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>#</th>
                            <th>Mod.</th>
                            <th>Desde</th>
                            <th>Hasta</th>
                            <th>Días</th>
                            <th>Motivo</th>
                            <th>Categoría</th>
                            <th>Comentario</th>
                            <th>Reemplaza</th>
                            <th style="width:{{ $hayFuenteWord ? '190px' : '150px' }}"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contratos as $c)
                            <tr data-id="{{ $c->id }}"
                                data-desde="{{ optional($c->desde_fecha)->format('Y-m-d') }}"
                                data-hasta="{{ optional($c->hasta_fecha)->format('Y-m-d') }}"
                                data-modalidad="{{ $c->modalidad }}"
                                data-motivo="{{ $c->motivo }}"
                                data-dias="{{ $c->dias }}"
                                data-liquidacion="{{ $c->liquidacion }}"
                                data-comentario="{{ e($c->comentario) }}"
                                data-categoria-id="{{ $c->categoria_id }}"
                                data-centrocosto-id="{{ $c->centrocosto_id }}"
                                data-reemplazo-id="{{ $c->empleado_reemplazo_id }}"
                                data-reemplazo-legajo="{{ $c->empleadoReemplazo->legajo ?? '' }}"
                                data-reemplazo-nombre="{{ e($c->empleadoReemplazo->nombre ?? '') }}">
                                <td>{{ $c->nro_orden }}</td>
                                <td title="{{ $c->modalidad_label }}">{{ $c->modalidad }}</td>
                                <td>{{ optional($c->desde_fecha)->format('d/m/Y') }}</td>
                                <td>{{ optional($c->hasta_fecha)->format('d/m/Y') }}</td>
                                <td>{{ $c->dias }}</td>
                                <td>{{ $c->motivo_label }}</td>
                                <td>{{ $c->categoria->descripcion ?? '' }}</td>
                                <td class="small">{{ $c->comentario }}</td>
                                <td class="small">{{ $c->empleadoReemplazo->nombre ?? '' }}</td>
                                <td class="text-nowrap">
                                    @if ($puedeEditar)
                                        <button type="button" class="btn-accion-tabla btn-contrato-editar" title="Modificar este contrato">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                    @endif
                                    @if ($puedeEmitir)
                                        <button type="button" class="btn btn-outline-info btn-sm btn-preview-contrato"
                                                data-contrato-id="{{ $c->id }}"
                                                data-tipo="CONTRATO"
                                                title="Vista previa">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm btn-emitir-contrato"
                                                data-contrato-id="{{ $c->id }}"
                                                data-tipo="CONTRATO"
                                                data-formato="pdf"
                                                title="Emitir PDF">
                                            <i class="fa fa-file-pdf"></i>
                                        </button>
                                        @if ($hayFuenteWord)
                                            <button type="button" class="btn btn-outline-primary btn-sm btn-emitir-contrato"
                                                    data-contrato-id="{{ $c->id }}"
                                                    data-tipo="CONTRATO"
                                                    data-formato="docx"
                                                    title="Emitir Word (plantilla original)">
                                                <i class="fa fa-file-word"></i>
                                            </button>
                                        @endif
                                    @endif
                                    @if ($puedeEditar)
                                        <button type="button" class="btn-accion-tabla btn-contrato-borrar" title="Eliminar">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted">Sin contratos cargados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($puedeEmitir)
        <div class="card card-outline card-primary mb-3">
            <div class="card-header py-2">
                <h3 class="card-title mb-0"><i class="fa fa-file-pdf"></i> Emitir certificado</h3>
            </div>
            <div class="card-body">
                <form id="form-emitir-certificado" class="form-row align-items-end" data-enter-campos="1">
                    <div class="form-group col-md-3 mb-2">
                        <label class="small mb-0">Tipo</label>
                        <select name="tipo" id="emit_tipo" class="form-control form-control-sm" required>
                            <option value="CERT_LABORAL">{{ $tipos['CERT_LABORAL'] }}</option>
                            <option value="CERT_BAJA">{{ $tipos['CERT_BAJA'] }}</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label class="small mb-0">Fecha emisión</label>
                        <input type="date" name="fecha_emision" class="form-control form-control-sm" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label class="small mb-0">Liquidación</label>
                        <input type="number" name="liquidacion" class="form-control form-control-sm" min="0" step="1"
                               placeholder="Cert. laboral">
                    </div>
                    <div class="form-group col-md-3 mb-2">
                        <label class="small mb-0">Plantilla (opcional)</label>
                        <select name="contrato_plantilla_id" id="cert_plantilla_id" class="form-control form-control-sm">
                            <option value="">Automática por reglas</option>
                            @foreach ($plantillas as $p)
                                <option value="{{ $p->id }}">{{ $p->codigo }} — {{ $p->tipo_label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <div class="btn-group btn-group-sm d-flex flex-wrap" role="group" style="gap:2px;">
                            <button type="button" class="btn btn-outline-info" id="btn-preview-certificado" title="Vista previa">
                                <i class="fa fa-eye"></i>
                            </button>
                            <button type="submit" class="btn btn-success" data-formato="pdf" title="Emitir PDF">
                                <i class="fa fa-file-pdf"></i> PDF
                            </button>
                            @if ($hayFuenteWord)
                                <button type="button" class="btn btn-outline-primary" id="btn-emitir-certificado-word"
                                        data-formato="docx" title="Emitir Word (plantilla original)">
                                    <i class="fa fa-file-word"></i> Word
                                </button>
                            @endif
                        </div>
                    </div>
                </form>
                <p class="small text-muted mb-0">
                    Preview sin grabar. PDF y Word archivan la emisión. Word solo si la plantilla fuente es .docx.
                    Certificado de baja solo si el legajo está en baja.
                </p>
            </div>
        </div>
    @endif

    <div class="card card-outline card-secondary">
        <div class="card-header py-2">
            <h3 class="card-title mb-0">Emisiones recientes</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Plantilla</th>
                            <th>Archivo</th>
                            <th>Usuario</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($emisiones as $e)
                            <tr>
                                <td>{{ optional($e->fecha_emision)->format('d/m/Y') }}</td>
                                <td>{{ $e->tipo_label }}</td>
                                <td>{{ $e->plantilla->codigo ?? '—' }}</td>
                                <td class="small">{{ $e->archivo_nombre }}</td>
                                <td class="small">{{ $e->usuario?->nombre ?? $e->usuario?->usuario ?? '' }}</td>
                                <td>
                                    @if ($puedeEmitir)
                                        <a href="{{ route('descargar_emision_contrato_empleado_sueldos', $e->id) }}" class="btn-accion-tabla" title="Descargar">
                                            <i class="fa fa-download"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">Sin emisiones.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
