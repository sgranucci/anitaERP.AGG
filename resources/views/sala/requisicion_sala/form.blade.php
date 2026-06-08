@php
    $soloLectura = isset($visualizar) && $visualizar;
    $lineas = (isset($data) && $data && $data->requisicion_sala_articulos && $data->requisicion_sala_articulos->count())
        ? $data->requisicion_sala_articulos
        : collect([new \App\Models\Sala\RequisicionSalaArticulo()]);
@endphp
<div id="tab1" class="form1 tab-content">
    <div class="row">
        <div class="col-sm-6">
            @include('includes.form-empresa-asignada', [
                'empresa_query' => $empresa_query,
                'empresa_id' => (isset($data) && $data) ? $data->empresa_id : null,
                'solo_lectura' => $soloLectura || isset($data),
                'col_input' => 'col-lg-5',
            ])

            <div class="form-group row">
                <label for="centrocosto_id" class="col-lg-3 control-label requerido">Centro costo</label>
                <div class="col-lg-6">
                    <select name="centrocosto_id" id="centrocosto_id" class="form-control" required {{ $soloLectura ? 'disabled' : '' }}>
                        @php $ccDefault = (isset($data) && $data) ? $data->centrocosto_id : (auth()->user()->centrocosto_id ?? 1); @endphp
                        @foreach ($centrocosto_query as $cc)
                            @if ($cc->id > 0)
                                <option value="{{ $cc->id }}" {{ (int) old('centrocosto_id', $ccDefault) === (int) $cc->id ? 'selected' : '' }}>
                                    {{ $cc->codigo }} - {{ $cc->nombre }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group row align-items-center">
                <label for="codigodeposito" class="col-lg-3 control-label requerido">Depósito</label>
                <div class="col-lg-6">
                    <input type="hidden" id="deposito_id" name="deposito_id" value="{{ old('deposito_id', (isset($data) && $data) ? $data->deposito_id : '') }}">
                    <div class="d-flex align-items-center">
                        <input type="text" class="form-control codigodeposito mr-2" id="codigodeposito" style="width:6rem;" value="{{ old('codigodeposito', optional(optional($data)->depositos)->codigo ?? '') }}" {{ $soloLectura ? 'readonly' : '' }}>
                        <input type="text" class="form-control" id="nombredeposito" readonly value="{{ old('nombredeposito', optional(optional($data)->depositos)->nombre ?? '') }}">
                        @if(!$soloLectura)
                        <button type="button" class="btn-accion-tabla consultadeposito ml-2"><i class="fa fa-search text-primary"></i></button>
                        @endif
                    </div>
                </div>
            </div>

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
                        {{ $soloLectura ? 'readonly' : '' }}>
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
                    <select name="estado" id="estado" class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                        @foreach ($estado_enum as $e)
                            <option value="{{ $e['nombre'] }}" {{ old('estado', $data->estado ?? '') == $e['nombre'] ? 'selected' : '' }}>
                                {{ $e['nombre'] }}
                            </option>
                        @endforeach
                    </select>
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
    <table class="table table-sm" id="tabla-articulos-requisicion-sala">
        <thead>
            <tr>
                <th>Artículo</th>
                <th>Descripción</th>
                <th>Cantidad</th>
                <th>Fuera serv.</th>
                <th>UID</th>
                <th>Nº parte única</th>
                <th>Destino</th>
                @if(!$soloLectura)
                <th></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($lineas as $idx => $linea)
            <tr class="item-requisicion-sala-articulo">
                <td>
                    <input type="hidden" class="requisicion_sala_articulo_id" name="requisicion_sala_articulo_ids[]" value="{{ old('requisicion_sala_articulo_ids.'.$idx, $linea->id ?? '') }}">
                    <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{ old('articulo_ids.'.$idx, $linea->articulo_id ?? '') }}">
                    <input type="hidden" class="articulo_lleva_npu" value="{{ optional($linea->articulos)->numeroparte ?? '0' }}">
                    <div class="d-flex align-items-center">
                        @if(!$soloLectura)
                        <button type="button" class="btn-accion-tabla consultaarticulo mr-1"><i class="fa fa-search text-primary"></i></button>
                        @endif
                        <input type="text" class="codigoarticulo form-control form-control-sm" style="width:7rem;" value="{{ optional($linea->articulos)->sku ?? '' }}" {{ $soloLectura ? 'readonly' : '' }}>
                    </div>
                </td>
                <td><input type="text" class="descripcionarticulo form-control form-control-sm" readonly value="{{ optional($linea->articulos)->descripcion ?? '' }}"></td>
                <td><input type="number" step="0.0001" name="cantidades[]" class="form-control form-control-sm" value="{{ old('cantidades.'.$idx, $linea->cantidad ?? '1') }}" {{ $soloLectura ? 'readonly' : '' }}></td>
                <td>
                    <select name="fueradeservicios[]" class="form-control form-control-sm" {{ $soloLectura ? 'disabled' : '' }}>
                        <option value="N" {{ old('fueradeservicios.'.$idx, $linea->fueradeservicio ?? 'N') === 'N' ? 'selected' : '' }}>N</option>
                        <option value="S" {{ old('fueradeservicios.'.$idx, $linea->fueradeservicio ?? 'N') === 'S' ? 'selected' : '' }}>S</option>
                    </select>
                </td>
                <td><input type="text" name="uids[]" class="form-control form-control-sm" value="{{ old('uids.'.$idx, $linea->uid ?? '') }}" {{ $soloLectura ? 'readonly' : '' }}></td>
                <td><input type="text" name="numeropartes[]" class="form-control form-control-sm numeroparte-linea" value="{{ old('numeropartes.'.$idx, $linea->numeroparte ?? '') }}" {{ $soloLectura ? 'readonly' : '' }}></td>
                <td>
                    <select name="destinos[]" class="form-control form-control-sm" {{ $soloLectura ? 'disabled' : '' }}>
                        @foreach ($destino_enum as $d)
                            <option value="{{ $d['valor'] }}" {{ old('destinos.'.$idx, $linea->destino ?? 'S') === $d['valor'] ? 'selected' : '' }}>{{ $d['nombre'] }}</option>
                        @endforeach
                    </select>
                </td>
                @if(!$soloLectura)
                <td><button type="button" class="btn-accion-tabla eliminar_linea_sala"><i class="fa fa-times-circle text-danger"></i></button></td>
                @endif
            </tr>
            @endforeach
        </tbody>
    </table>
    @if(!$soloLectura)
    <button type="button" class="btn btn-danger btn-sm" id="agrega_renglon_sala">+ Agrega renglón</button>
    @endif
</div>
@include('sala.requisicion_sala.partials.template_linea_articulo')
