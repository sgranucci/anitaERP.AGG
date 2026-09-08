<div id="form-errors"></div>
<div class="row">
    <div class="col-sm-6">
        @include('includes.form-empresa-asignada', [
            'empresa_query' => $empresa_query,
            'empresa_id' => $data->empresa_id ?? null,
            'mostrar_id' => true,
            'col_label' => 'col-lg-3 text-right pr-2',
            'col_input' => 'col-lg-7',
        ])
        <div class="form-group row">
            <label for="presupuesto_id" class="col-lg-3 col-form-label text-right">Presupuesto</label>
            <select name="presupuesto_id" id="presupuesto_id" data-placeholder="Presupuesto" class="col-lg-7 form-control required" data-fouc required>
                @foreach($presupuesto_query as $key => $value)
                    @if( (int) $value->id == (int) old('presupuesto_id', $data->presupuesto_id ?? ''))
                        <option value="{{ $value->id }}" selected="select">{{ $value->id }} {{ $value->nombre }}</option>
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="presupuesto_escenario_id" class="col-lg-3 col-form-label text-right">Escenario</label>
            <select name="presupuesto_escenario_id" id="presupuesto_escenario_id" data-placeholder="Escenario" class="col-lg-7 form-control required" data-fouc required>
            </select>
        </div>
        <div class="form-group row">
            <label for="centrocosto_id" class="col-lg-3 col-form-label text-right">Centro de Costo</label>
            <select name="centrocosto_id" id="centrocosto_id" data-placeholder="Centro de Costo" class="col-lg-5 form-control required" data-fouc required>
                @foreach($centrocosto_query as $key => $value)
                    @if( (int) $value->id == (int) old('centrocosto_id', $data->centrocosto_id ?? ''))
                        <option value="{{ $value->id }}" selected="select">{{ $value->codigo }}-{{ $value->nombre }}</option>
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>
                    @endif
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="codigoproveedor" class="col-lg-3 col-form-label text-right">Proveedor</label>
            <input type="hidden" class="col-lg-2 proveedor_id form-control" name="proveedor_id" id="proveedor_id" value="{{$data->proveedor_id ?? ''}}" >
            <input type="text" class="col-lg-1 proveedor form-control" name="codigoproveedor" id="codigoproveedor" value="{{$data->proveedores->codigo ?? ''}}">
            <button type="button" title="Consulta proveedores" style="padding:1;" class="btn-accion-tabla consultaproveedor tooltipsC">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" name="nombreproveedor" id="nombreproveedor" class="col-lg-7 form-control nombreproveedor" value="{{$data->proveedores->nombre ?? ''}}">
        </div>
        <div class="form-group row">
            <label for="codigoarticulo" class="col-lg-3 col-form-label text-right">Artículo</label>
            <input type="hidden" class="articulo_id" name="articulo_id" value="{{$data->articulo_id ?? ''}}" >
            <input type="text" class="col-lg-2 form-control" id="codigoarticulo" name="codigoarticulo" value="{{$data->articulos->sku ?? ''}}" >
            <button type="button" title="Consulta articulos" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC">
                    <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="nombrearticulo col-lg-6 form-control" id="nombrearticulo" name="nombrearticulo" value="{{$data->articulos->descripcion ?? ''}}" readonly>
        </div>
        <div class="form-group row" id="cuenta">
            <label for="codigocuentacontable" class="col-lg-3 col-form-label text-right">Cuenta Contable</label>
            <input type="hidden" class="cuentacontable_id" id="cuentacontable_id" name="cuentacontable_id" value="{{$data->cuentacontable_id ?? ''}}" >
            <input type="text" class="codigocuentacontable col-lg-2 form-control" id="codigocuentacontable" name="codigocuentacontable" value="{{$data->cuentacontables->codigo ?? ''}}" >
            <button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuentacontable tooltipsC">
                    <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="nombrecuentacontable col-lg-6 form-control" id="nombrecuentacontable" name="nombrecuentacontable" value="{{$data->cuentacontables->nombre ?? ''}}" >
        </div>
        <div class="form-group row">
            <label for="codigo" class="col-lg-3 col-form-label text-right">Código de Partida</label>
            <input type="text" name="codigo" id="codigo" class="col-lg-2 form-control requerido" placeholder="Codigo de Partida" aria-label="Codigo de Partida" value="{{$data->codigo ?? ''}}" required>
            <label for="estado" class="col-lg-3 col-form-label text-right">Estado</label>
            <input type="text" name="estado" id="estado" class="col-lg-3 form-control" value="{{old('estado', $data->estado ?? 'ACTIVA')}}" readonly>
        </div>
        <div class="form-group row">
            <label for="montototal" class="col-lg-3 col-form-label text-right">Monto Total</label>
            <select name="moneda_id" id="moneda_id" data-placeholder="Moneda" class="col-lg-2 form-control required" data-fouc>
                @foreach($moneda_query as $key => $value)
                    @if( (int) $value->id == (int) old('moneda_id', $data->moneda_id ?? ''))
                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>
                    @endif
                @endforeach
            </select>
            <span class="input-group-text">#</span>
            <input type="text" name="montototal" id="montototal" class="col-lg-3 form-control" placeholder="Monto total" aria-label="Monto total" value="" readonly>
        </div>
    </div>
</div>
<div class="col-md-12">
    <div class="form-group row">
        <label for="detalle" class="col-lg-1 col-form-label text-right">Detalle</label>
        <div class="col-lg-10">
            <input type="text" name="detalle" id="detalle" class="form-control" value="{{old('detalle', $data->detalle ?? '')}}">
        </div>
    </div>
</div>
<div class="col-md-12">
    <div class="card card-outline card-info mb-3">
        <div class="card-header py-2">
            <h3 class="card-title mb-0"><i class="fa fa-calendar"></i> Montos por período</h3>
        </div>
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-2" id="partidagasto-monto-table">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th style="width: 25%;">Período</th>
                            <th style="width: 30%;">Monto</th>
                            <th style="width: 90px;" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-partidagasto-monto-table" class="container-partidagasto">
                        @if ($data->partidagasto_montos ?? '')
                            @foreach (old('cuota', $data->partidagasto_montos->count() ? $data->partidagasto_montos : ['']) as $partida)
                                @if (isset($partida->periodo))
                                <tr>
                                    <td>
                                        <input type="hidden" name="items[]" class="form-control item" readonly value="{{ $loop->index+1 }}" />
                                        <input type="hidden" name="partidagasto_monto_ids[]" class="form-control partidagasto_monto_id" readonly value="{{ $partida->id }}" />
                                        <input type="hidden" name="creousuario_ids[]" class="creousuario_id_monto" value="{{ $partida->creousuario_id }}" />
                                        <input type="month" name="periodos[]" min="2010/01" placeholder="Formato: AAAA-MM" class="form-control periodo" value="{{$partida->periodo}}">
                                    </td>
                                    <td>
                                        <input type="text" name="montos[]" class="form-control monto" value="{{$partida->monto}}">
                                    </td>
                                    <td class="text-center align-middle">
                                        <button type="button" class="btn-accion-tabla eliminar_partidagasto_monto tooltipsC" title="Elimina esta línea">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endif
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
            @include('presupuesto.partidagasto.template')
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <button type="button" id="agrega_renglon_partidagasto_monto" class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-plus"></i> Agrega renglón
                    </button>
                </div>
                <div class="col-sm-6">
                    <div class="form-group row mb-0 justify-content-end">
                        <label for="totalpartida" class="col-lg-4 col-form-label text-right">Total partida</label>
                        <input type="text" id="totalpartida" class="col-lg-4 form-control totalpartida" value="" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="partidagasto_id" name="partidagasto_id" value="{{ $data->id ?? '' }}" />
<input type="hidden" id="creousuario_id" name="creousuario_id" value="{{ $data->creousuario_id ?? '' }}" />
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
