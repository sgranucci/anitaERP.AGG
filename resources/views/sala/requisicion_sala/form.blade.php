@php
    $soloLectura = ! empty($soloLectura) || (! empty($visualizar));
    $bloqueoEstructural = ! empty($bloqueoEstructural);
    $modoEdicionMenor = ! empty($modoEdicionMenor);
    $campoBloqueado = $soloLectura || $bloqueoEstructural;
    $lineasTmBloqueadas = array_flip($lineas_articulo_bloqueadas_por_tm ?? []);
    $lineasCumplimientoBloqueadas = array_flip($lineas_articulo_bloqueadas_por_cumplimiento ?? []);
    $lineas = (isset($data) && $data && $data->requisicion_sala_articulos && $data->requisicion_sala_articulos->count())
        ? $data->requisicion_sala_articulos
        : collect([new \App\Models\Sala\RequisicionSalaArticulo()]);
    $depositoId = old('deposito_id', (isset($data) && $data) ? $data->deposito_id : '');
    $depositoModel = null;
    if ((int) $depositoId > 0) {
        $depositoModel = (isset($data) && $data && (int) ($data->deposito_id ?? 0) === (int) $depositoId)
            ? $data->depositos
            : \App\Models\Stock\Depmae::find((int) $depositoId);
    }
@endphp
<div id="tab1" class="form1">
    <div class="row">
        <div class="col-sm-6">
            @include('includes.form-empresa-asignada', [
                'empresa_query' => $empresa_query,
                'empresa_id' => (isset($data) && $data) ? $data->empresa_id : null,
                'solo_lectura' => $campoBloqueado || isset($data),
                'col_input' => 'col-lg-5',
            ])

            <div class="form-group row">
                <label for="centrocosto_id" class="col-lg-3 control-label requerido">Centro costo</label>
                <div class="col-lg-6">
                    <select name="centrocosto_id" id="centrocosto_id" class="form-control" required {{ $campoBloqueado ? 'disabled' : '' }}>
                        @php $ccDefault = (isset($data) && $data) ? $data->centrocosto_id : (auth()->user()->centrocosto_id ?? 1); @endphp
                        @foreach ($centrocosto_query as $cc)
                            @if ($cc->id > 0)
                                <option value="{{ $cc->id }}" {{ (int) old('centrocosto_id', $ccDefault) === (int) $cc->id ? 'selected' : '' }}>
                                    {{ $cc->codigo }} - {{ $cc->nombre }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                    @if($campoBloqueado)
                        <input type="hidden" name="centrocosto_id" value="{{ old('centrocosto_id', $ccDefault) }}">
                    @endif
                </div>
            </div>

            @include('stock.partials.campo_consulta_deposito', [
                'prefix' => 'requisicion_sala',
                'layout' => 'form_row',
                'label' => 'Depósito',
                'inputName' => 'deposito_id',
                'inputId' => 'deposito_id',
                'depositoId' => $depositoId,
                'codigo' => old('deposito_codigo', optional($depositoModel)->codigo ?? ''),
                'descripcion' => old('deposito_descripcion', optional($depositoModel)->nombre ?? ''),
                'solo_lectura' => $campoBloqueado,
                'col_label' => 'col-lg-3 control-label',
                'col_input' => 'col-lg-6',
            ])

            <div class="form-group row">
                <label for="zona_sala_id" class="col-lg-3 control-label">Zona de sala</label>
                <div class="col-lg-6">
                    <select name="zona_sala_id" id="zona_sala_id" class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                        <option value="">—</option>
                        @foreach ($zona_sala_query as $z)
                            <option value="{{ $z->id }}" {{ (int) old('zona_sala_id', (isset($data) && $data) ? $data->zona_sala_id : '') === (int) $z->id ? 'selected' : '' }}>
                                {{ $z->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group row">
                <label for="prioridad_sala_id" class="col-lg-3 control-label">Prioridad</label>
                <div class="col-lg-6">
                    <select name="prioridad_sala_id" id="prioridad_sala_id" class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                        <option value="">—</option>
                        @foreach ($prioridad_sala_query as $p)
                            <option value="{{ $p->id }}" {{ (int) old('prioridad_sala_id', (isset($data) && $data) ? $data->prioridad_sala_id : '') === (int) $p->id ? 'selected' : '' }}>
                                {{ $p->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="fecha" class="col-lg-3 control-label requerido">Fecha</label>
                <div class="col-lg-4">
                    <input type="date" name="fecha" id="fecha" class="form-control" required
                        value="{{ old('fecha', (isset($data) && $data && $data->fecha) ? substr($data->fecha, 0, 10) : date('Y-m-d')) }}"
                        {{ $campoBloqueado ? 'readonly' : '' }}>
                </div>
            </div>
            <div class="form-group row">
                <label for="fecha_entrega" class="col-lg-3 control-label requerido">Fecha entrega</label>
                <div class="col-lg-4">
                    <input type="date" name="fecha_entrega" id="fecha_entrega" class="form-control" required
                        value="{{ old('fecha_entrega', (isset($data) && $data && $data->fecha_entrega) ? substr($data->fecha_entrega, 0, 10) : date('Y-m-d')) }}"
                        {{ $soloLectura ? 'readonly' : '' }}>
                </div>
            </div>
            <div class="form-group row">
                <label for="numerorequisicion" class="col-lg-3 control-label">Requisición</label>
                <div class="col-lg-3">
                    <input type="text" id="numerorequisicion" class="form-control" readonly
                        value="{{ old('numerorequisicion', (isset($data) && $data) ? $data->numerorequisicion : '') }}">
                </div>
            </div>
            @if(isset($data))
            <div class="form-group row">
                <label for="estado" class="col-lg-3 control-label">Estado</label>
                <div class="col-lg-5">
                    <select name="estado" id="estado" class="form-control" {{ $campoBloqueado ? 'disabled' : '' }}>
                        @foreach ($estado_enum as $e)
                            <option value="{{ $e['nombre'] }}" {{ old('estado', $data->estado ?? '') == $e['nombre'] ? 'selected' : '' }}>
                                {{ $e['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                    @if($campoBloqueado)
                        <input type="hidden" name="estado" value="{{ old('estado', $data->estado ?? '') }}">
                    @endif
                </div>
            </div>
            @endif
            <div class="form-group row">
                <label for="comentario" class="col-lg-3 control-label">Comentario</label>
                <div class="col-lg-8">
                    <input type="text" name="comentario" id="comentario" class="form-control"
                        value="{{ old('comentario', (isset($data) && $data) ? $data->comentario : '') }}"
                        {{ $soloLectura ? 'readonly' : '' }}>
                </div>
            </div>
        </div>
    </div>
    <div class="form-group row">
        <label for="detalle" class="col-lg-2 col-form-label">Detalle</label>
        <div class="col-lg-8">
            <textarea name="detalle" id="detalle" rows="2" class="form-control" {{ $soloLectura ? 'readonly' : '' }}>{{ old('detalle', (isset($data) && $data) ? $data->detalle : '') }}</textarea>
        </div>
    </div>
    <hr>
    <h5>Artículos</h5>
    @php
        $rsColspanLeyenda = ($soloLectura) ? 8 : 9;
    @endphp
    <table class="table table-sm table-bordered" id="tabla-articulos-requisicion-sala">
        <thead class="thead-light">
            <tr>
                <th style="min-width: 9rem;">Artículo</th>
                <th class="col-desc-celda">Descripción</th>
                <th>Cantidad</th>
                <th>Fuera serv.</th>
                <th>UID</th>
                <th>Nº parte única</th>
                <th>Destino</th>
                <th class="text-center" style="width: 2.75rem;" title="Leyenda de la línea">
                    <i class="fa fa-comment-o" aria-hidden="true"></i>
                    <span class="sr-only">Leyenda</span>
                </th>
                @if(!$soloLectura)
                <th></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($lineas as $idx => $linea)
            @php
                $lineaIdInt = (int) ($linea->id ?? 0);
                $lineaBloqueadaTm = ! $soloLectura && isset($lineasTmBloqueadas[$lineaIdInt]);
                $lineaBloqueadaCumplimiento = ! $soloLectura && isset($lineasCumplimientoBloqueadas[$lineaIdInt]);
                $soloLecturaArticulo = $soloLectura || $lineaBloqueadaTm || $lineaBloqueadaCumplimiento;
                $leyendaLinea = old('detalle_articulos.'.$idx, $linea->detalle ?? '');
                $tieneLeyenda = trim((string) $leyendaLinea) !== '';
            @endphp
            <tr class="item-requisicion-sala-articulo @if($lineaBloqueadaTm || $lineaBloqueadaCumplimiento) linea-articulo-bloqueada-tm @endif">
                <td class="align-middle">
                    <input type="hidden" class="requisicion_sala_articulo_id" name="requisicion_sala_articulo_ids[]" value="{{ old('requisicion_sala_articulo_ids.'.$idx, $linea->id ?? '') }}">
                    <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{ old('articulo_ids.'.$idx, $linea->articulo_id ?? '') }}">
                    <input type="hidden" class="articulo_lleva_npu" value="{{ optional($linea->articulos)->numeroparte ?? '0' }}">
                    @include('sala.requisicion_sala.partials.celda_articulo_linea', [
                        'sku' => optional($linea->articulos)->sku ?? '',
                        'soloLectura' => $soloLecturaArticulo,
                    ])
                    @if($lineaBloqueadaTm)
                        <small class="text-muted d-block mt-1" title="Incluido en transferencia al laboratorio">Art. bloqueado (TM)</small>
                    @elseif($lineaBloqueadaCumplimiento)
                        <small class="text-muted d-block mt-1" title="Tiene cumplimientos activos">Art. bloqueado (cumplimiento)</small>
                    @endif
                </td>
                <td class="col-desc-celda align-middle">
                    <input type="text" class="descripcionarticulo form-control form-control-sm" readonly value="{{ optional($linea->articulos)->descripcion ?? '' }}" title="{{ optional($linea->articulos)->descripcion ?? '' }}">
                    <small class="rs-leyenda-resumen text-info d-block text-truncate mt-1 {{ $tieneLeyenda ? '' : 'd-none' }}" title="{{ $leyendaLinea }}">
                        <i class="fa fa-comment-o" aria-hidden="true"></i> <span class="rs-leyenda-resumen-texto">{{ $leyendaLinea }}</span>
                    </small>
                </td>
                <td class="align-middle"><input type="number" step="0.0001" name="cantidades[]" class="form-control form-control-sm cantidad-linea" value="{{ old('cantidades.'.$idx, $linea->cantidad ?? '1') }}" {{ $campoBloqueado ? 'readonly' : '' }}></td>
                <td class="align-middle">
                    <select name="fueradeservicios[]" class="form-control form-control-sm fueradeservicio-linea" {{ $campoBloqueado ? 'disabled' : '' }}>
                        <option value="N" {{ old('fueradeservicios.'.$idx, $linea->fueradeservicio ?? 'N') === 'N' ? 'selected' : '' }}>N</option>
                        <option value="S" {{ old('fueradeservicios.'.$idx, $linea->fueradeservicio ?? 'N') === 'S' ? 'selected' : '' }}>S</option>
                    </select>
                    @if($campoBloqueado)
                        <input type="hidden" name="fueradeservicios[]" value="{{ old('fueradeservicios.'.$idx, $linea->fueradeservicio ?? 'N') }}">
                    @endif
                </td>
                <td class="align-middle">
                    <input type="text" name="uids[]" class="form-control form-control-sm uid-linea" value="{{ old('uids.'.$idx, $linea->uid ?? '') }}" maxlength="50" placeholder="Obligatorio si F/S = S" {{ $soloLectura ? 'readonly' : '' }}>
                </td>
                <td class="align-middle"><input type="text" name="numeropartes[]" class="form-control form-control-sm numeroparte-linea" value="{{ old('numeropartes.'.$idx, $linea->numeroparte ?? '') }}" {{ $soloLectura ? 'readonly' : '' }}></td>
                <td class="align-middle">
                    <select name="destinos[]" class="form-control form-control-sm" {{ $campoBloqueado ? 'disabled' : '' }}>
                        @foreach ($destino_enum as $d)
                            <option value="{{ $d['valor'] }}" {{ old('destinos.'.$idx, $linea->destino ?? 'S') === $d['valor'] ? 'selected' : '' }}>{{ $d['nombre'] }}</option>
                        @endforeach
                    </select>
                    @if($campoBloqueado)
                        <input type="hidden" name="destinos[]" value="{{ old('destinos.'.$idx, $linea->destino ?? 'S') }}">
                    @endif
                </td>
                <td class="align-middle text-center p-1">
                    <button type="button"
                        class="btn btn-sm rs-toggle-leyenda {{ $tieneLeyenda ? 'btn-info has-leyenda' : 'btn-outline-secondary' }}"
                        title="{{ $tieneLeyenda ? 'Ver / editar leyenda' : 'Agregar leyenda' }}"
                        aria-expanded="false"
                        aria-label="Leyenda de la línea">
                        <i class="fa {{ $tieneLeyenda ? 'fa-comment' : 'fa-comment-o' }}" aria-hidden="true"></i>
                    </button>
                </td>
                @if(!$soloLectura && !$bloqueoEstructural)
                <td class="align-middle text-center">
                    @if($lineaBloqueadaTm)
                        <span class="text-muted" title="No se puede eliminar: incluido en transferencia al laboratorio"><i class="fa fa-lock"></i></span>
                    @else
                        <button type="button" class="btn-accion-tabla eliminar_linea_sala"><i class="fa fa-times-circle text-danger"></i></button>
                    @endif
                </td>
                @elseif(!$soloLectura && $bloqueoEstructural)
                <td class="align-middle text-center">
                    <span class="text-muted" title="Edición menor: no se pueden agregar ni eliminar líneas"><i class="fa fa-lock"></i></span>
                </td>
                @endif
            </tr>
            <tr class="item-requisicion-sala-leyenda d-none">
                <td colspan="{{ $rsColspanLeyenda }}" class="rs-leyenda-celda px-2 py-2">
                    <div class="rs-leyenda-panel">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="mb-0 small font-weight-bold text-secondary">
                                <i class="fa fa-comment-o mr-1" aria-hidden="true"></i> Leyenda de la línea
                            </label>
                            <span class="rs-leyenda-preview text-muted small text-truncate ml-2 {{ $tieneLeyenda ? '' : 'd-none' }}" title="{{ $leyendaLinea }}">{{ $leyendaLinea }}</span>
                        </div>
                        <textarea name="detalle_articulos[]"
                            class="form-control form-control-sm rs-leyenda-linea"
                            rows="2"
                            maxlength="2000"
                            placeholder="Observaciones, detalle o nota específica de este ítem…"
                            {{ $soloLectura ? 'readonly' : '' }}>{{ $leyendaLinea }}</textarea>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if(!empty($tiene_transferencia_laboratorio) && !$soloLectura)
    <div id="aviso-nuevos-articulos-sin-tm" class="alert alert-warning small d-none mt-2 mb-0" role="alert">
        <strong>Artículos nuevos sin transferir:</strong>
        hay renglones agregados que <strong>no están incluidos</strong> en la transferencia al laboratorio ya generada.
        Debe registrar una <strong>transferencia de mercadería aparte</strong> (Stock → Transferencia de mercadería) para esos ítems.
    </div>
    @endif
    @if(!$soloLectura && !$bloqueoEstructural)
    <button type="button" class="btn btn-danger btn-sm" id="agrega_renglon_sala">+ Agrega renglón</button>
    <small id="aviso-uid-fuera-servicio" class="text-danger d-none ml-2">Complete el UID de los ítems fuera de servicio antes de agregar otro renglón.</small>
    @endif
</div>
<style>
    #tabla-articulos-requisicion-sala td.align-middle { vertical-align: middle !important; }
    #tabla-articulos-requisicion-sala .celda-articulo-ms .codigoarticulo { min-width: 5rem; max-width: 8rem; }
    #tabla-articulos-requisicion-sala .col-desc-celda .descripcionarticulo { min-width: 0; width: 100%; }
    #tabla-articulos-requisicion-sala tr.item-requisicion-sala-leyenda td.rs-leyenda-celda {
        border-top: none !important;
        background: #f7fbfe;
    }
    #tabla-articulos-requisicion-sala .rs-leyenda-panel {
        border-left: 3px solid #85C1E9;
        padding-left: 0.65rem;
    }
    #tabla-articulos-requisicion-sala .rs-toggle-leyenda {
        padding: 0.15rem 0.4rem;
        line-height: 1.2;
        position: relative;
    }
    #tabla-articulos-requisicion-sala .rs-toggle-leyenda.has-leyenda::after {
        content: '';
        position: absolute;
        top: 2px;
        right: 2px;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #1a5276;
    }
    #tabla-articulos-requisicion-sala .rs-leyenda-preview {
        max-width: 55%;
        font-style: italic;
    }
    #tabla-articulos-requisicion-sala .rs-leyenda-resumen {
        font-size: 0.75rem;
        line-height: 1.2;
        max-width: 100%;
        cursor: pointer;
    }
    #tabla-articulos-requisicion-sala textarea.rs-leyenda-linea {
        resize: vertical;
        min-height: 2.4rem;
    }
</style>
@include('sala.requisicion_sala.partials.template_linea_articulo')
