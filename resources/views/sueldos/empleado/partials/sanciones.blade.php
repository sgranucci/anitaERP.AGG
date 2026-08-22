@php
    use App\Support\Sueldos\EmpleadoSancionSupport;
    $r = $resumen ?? ['total' => 0, 'dias_suspension_anio' => 0, 'ultima' => null];
@endphp
<div id="sanciones-empleado-panel" data-empleado="{{ $empleado->id }}"
     data-url="{{ route('sanciones_empleado_sueldos', ['empleado' => $empleado->id]) }}">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
            <h5 class="mb-0"><i class="fa fa-gavel"></i> Expediente disciplinario</h5>
            @include('includes.sueldos.boton-manual')
        </div>
        <div class="small text-muted">
            {{ $r['total'] }} sanción(es) · {{ $r['dias_suspension_anio'] }} día(s) de suspensión en {{ date('Y') }}
        </div>
    </div>

    <div class="alert alert-info py-2">
        Si el tipo genera novedad, no cargue además una ausencia de suspensión (tipo 41) por los mismos días.
    </div>

    @if ($puedeEditar ?? false)
    <div class="card card-outline card-secondary mb-3">
        <div class="card-header py-2"><strong id="sancion-form-titulo">Nueva sanción</strong></div>
        <div class="card-body py-2">
            <form id="form-sancion-empleado" enctype="multipart/form-data">
                <input type="hidden" name="sancion_id" value="">
                <div class="form-row">
                    <div class="col-md-4 mb-2">
                        @include('sueldos.partials.campo_consulta_tipo_sancion', [
                            'inputName' => 'tipo_sancion_id',
                            'inputId' => 'sancion_tipo_id',
                            'tipoId' => '',
                            'required' => true,
                        ])
                    </div>
                    <div class="col-md-4 mb-2">
                        @include('sueldos.partials.campo_consulta_motivo_sancion', [
                            'inputName' => 'motivo_sancion_id',
                            'inputId' => 'sancion_motivo_id',
                            'motivoId' => '',
                            'required' => true,
                        ])
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label class="small mb-0">Fecha hecho</label>
                        <input type="date" name="fecha_hecho" class="form-control form-control-sm" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label class="small mb-0">Recepción</label>
                        <input type="date" name="fecha_recepcion" class="form-control form-control-sm">
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label class="small mb-0">Desde</label>
                        <input type="date" name="fecha_desde" class="form-control form-control-sm">
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label class="small mb-0">Hasta</label>
                        <input type="date" name="fecha_hasta" class="form-control form-control-sm">
                    </div>
                    <div class="form-group col-md-1 mb-2">
                        <label class="small mb-0">Días</label>
                        <input type="number" name="cant_dias" class="form-control form-control-sm" min="0" max="999" value="0">
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label class="small mb-0" title="Salario que no se paga por la sanción (p. ej. suspensión). No es una multa.">Importe no cobrado</label>
                        <input type="number" step="0.01" name="importe_perdida" class="form-control form-control-sm" min="0" value="0" title="Salario que no se paga por la sanción (p. ej. suspensión). No es una multa.">
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label class="small mb-0">Notificación</label>
                        <input type="date" name="fecha_notificacion" class="form-control form-control-sm">
                    </div>
                    <div class="form-group col-md-12 mb-2">
                        <label class="small mb-0">Comentario / causa</label>
                        <textarea name="comentario" class="form-control form-control-sm" rows="2" required maxlength="4000"></textarea>
                    </div>
                    <div class="form-group col-md-6 mb-2">
                        <label class="small mb-0">Descargo</label>
                        <textarea name="descargo_texto" class="form-control form-control-sm" rows="2" maxlength="4000"></textarea>
                    </div>
                    <div class="form-group col-md-6 mb-2">
                        <label class="small mb-0">Resolución</label>
                        <textarea name="resolucion_texto" class="form-control form-control-sm" rows="2" maxlength="4000"></textarea>
                    </div>
                    <div class="form-group col-md-4 mb-2">
                        <label class="small mb-0">Adjuntos</label>
                        <input type="file" name="archivos[]" class="form-control form-control-sm" multiple>
                    </div>
                    <div class="form-group col-md-8 mb-2 text-right">
                        <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btn-sancion-cancelar">Cancelar</button>
                        <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-save"></i> Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Motivo</th>
                    <th class="text-right">Días</th>
                    <th>Recepción</th>
                    <th>Estado</th>
                    <th class="text-right">Importe no cobrado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sanciones as $s)
                    @php
                        $inmed = EmpleadoSancionSupport::inmediatez(
                            optional($s->fecha_hecho)->format('Y-m-d'),
                            optional($s->fecha_notificacion)->format('Y-m-d')
                        );
                        $reinc = EmpleadoSancionSupport::contarReincidencias((int) $empleado->id, (int) $s->motivo_sancion_id, (int) $s->id);
                        $payloadSancion = [
                            'id' => $s->id,
                            'tipo_sancion_id' => $s->tipo_sancion_id,
                            'tipo_codigo' => $s->tipo->codigo ?? '',
                            'tipo_nombre' => $s->tipo->nombre ?? '',
                            'motivo_sancion_id' => $s->motivo_sancion_id,
                            'motivo_codigo' => $s->motivo->codigo ?? '',
                            'motivo_nombre' => $s->motivo->nombre ?? '',
                            'fecha_hecho' => optional($s->fecha_hecho)->format('Y-m-d'),
                            'fecha_desde' => optional($s->fecha_desde)->format('Y-m-d'),
                            'fecha_hasta' => optional($s->fecha_hasta)->format('Y-m-d'),
                            'cant_dias' => $s->cant_dias,
                            'importe_perdida' => $s->importe_perdida,
                            'fecha_notificacion' => optional($s->fecha_notificacion)->format('Y-m-d'),
                            'fecha_recepcion' => optional($s->fecha_recepcion)->format('Y-m-d'),
                            'comentario' => $s->comentario,
                            'descargo_texto' => $s->descargo_texto,
                            'resolucion_texto' => $s->resolucion_texto,
                            'estado' => $s->estado,
                        ];
                    @endphp
                    <tr data-sancion='@json($payloadSancion)'>
                        <td>{{ optional($s->fecha_hecho)->format('d/m/Y') }}</td>
                        <td>{{ $s->tipo->nombre ?? '' }}</td>
                        <td>
                            {{ $s->motivo->nombre ?? '' }}
                            @if ($reinc > 0)
                                <span class="badge badge-warning">Reincidencia {{ $reinc }}</span>
                            @endif
                            @if ($inmed['alerta'])
                                <span class="badge badge-danger">Inmediatez {{ $inmed['dias'] }}d</span>
                            @endif
                        </td>
                        <td class="text-right">{{ $s->cant_dias }}</td>
                        <td>{{ optional($s->fecha_recepcion)->format('d/m/Y') }}</td>
                        <td>{{ $s->estadoLabel() }}</td>
                        <td class="text-right">{{ number_format((float) $s->importe_perdida, 2, ',', '.') }}</td>
                        <td class="text-nowrap">
                            @if (($puedeEditar ?? false) && $s->esEditable())
                                <button type="button" class="btn-accion-tabla btn-editar-sancion" title="Editar"><i class="fa fa-edit"></i></button>
                            @endif
                            @if ($puedeImprimir ?? false)
                                <a href="{{ route('notificacion_sancion_sueldos', ['id' => $s->id]) }}" class="btn-accion-tabla" target="_blank" rel="noopener" title="Carta PDF"><i class="fa fa-file-pdf"></i></a>
                            @endif
                            @if (($puedeEditar ?? false) && $s->estado === EmpleadoSancionSupport::ESTADO_BORRADOR)
                                <button type="button" class="btn btn-outline-primary btn-xs btn-transicion-sancion" data-accion="notificar" data-id="{{ $s->id }}">Notificar</button>
                            @endif
                            @if (($puedeEditar ?? false) && in_array($s->estado, [EmpleadoSancionSupport::ESTADO_NOTIFICADA, EmpleadoSancionSupport::ESTADO_CON_DESCARGO], true))
                                <button type="button" class="btn btn-outline-secondary btn-xs btn-transicion-sancion" data-accion="descargo" data-id="{{ $s->id }}">Descargo</button>
                                <button type="button" class="btn btn-outline-success btn-xs btn-transicion-sancion" data-accion="firmar" data-id="{{ $s->id }}">Firmar</button>
                            @endif
                            @if ($puedeAnular ?? false)
                                <button type="button" class="btn btn-outline-danger btn-xs btn-transicion-sancion" data-accion="anular" data-id="{{ $s->id }}">Anular</button>
                                <button type="button" class="btn-accion-tabla text-danger btn-eliminar-sancion" data-id="{{ $s->id }}" title="Eliminar"><i class="fa fa-times-circle"></i></button>
                            @endif
                            @if ($s->archivos->isNotEmpty())
                                @foreach ($s->archivos as $arch)
                                    <a href="{{ route('descargar_archivo_sancion_sueldos', ['id' => $arch->id]) }}" class="d-block small" target="_blank" rel="noopener">{{ $arch->nombre_original }}</a>
                                @endforeach
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">Sin sanciones.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
