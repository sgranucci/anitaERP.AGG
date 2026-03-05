<div class="card form1">
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="empresa" class="col-lg-3 col-form-label">Empresa</label>
                <select name="empresa_id" id="empresa_id" data-placeholder="Empresa" class="col-lg-7 form-control required" data-fouc>
                    <option value="">-- Seleccionar empresa --</option>
                    @foreach($empresa_query as $key => $value)
                        @if( (int) $value->id == (int) old('empresa_id', $data->empresa_id ?? session('empresa_id')))
                            <option value="{{ $value->id }}" selected="select">{{ $value->id }} {{ $value->nombre }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->id }} {{ $value->nombre }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="form-group row">
                <label for="tipoasiento" class="col-lg-3 col-form-label">Tipo de asiento</label>
                <select name="tipoasiento_id" id="tipoasiento_id" data-placeholder="Tipo de asiento" class="col-lg-7 form-control required" data-fouc>
                    <option value="">-- Seleccionar --</option>
                    @foreach($tipoasiento_query as $key => $value)
                        @if( (int) $value->id == (int) old('tipoasiento_id', $data->tipoasiento_id ?? session('tipoasiento_id')))
                            <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="fecha" class="col-lg-3 col-form-label">Fecha</label>
                <div class="col-lg-3">
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{old('fecha', $data->fecha ?? date('Y-m-d'))}}">
                </div>
            </div>
        </div>
    </div>
    <div class="form-group row">
        <label for="observacion" class="col-lg-3 col-form-label">Observaciones</label>
        <div class="col-lg-8">
            <input type="text" name="observacion" id="observacion" class="form-control" value="{{old('observacion', $data->observacion ?? '')}}">
        </div>
    </div>
    <input type="hidden" id="numeroasiento" name="numeroasiento" value="{{ $data->numeroasiento ?? '' }}" />
    <input type="hidden" id="id" name="id" value="{{ $data->id ?? '' }}" />
    <h2 id="loading"style="display:none">Guardando asiento ...</h2>
    <h3>Cuentas</h3>
    <div class="card-body">
        <table class="table" id="cuenta-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Código</th>
                    <th style="width: 18%;">Descripción</th>
                    <th style="width: 15%;">Centro de costo</th>
                    <th style="width: 7%;">Moneda</th>
                    <th style="width: 15%;">Debe</th>
                    <th style="width: 15%;">Haber</th>
                    <th style="width: 12%;">Cotización</th>
                    <th style="width: 30%;">Detalle</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-cuenta-table">
            @if ($data->asiento_movimientos ?? '') 
                @foreach (old('cuenta', $data->asiento_movimientos->count() ? $data->asiento_movimientos : ['']) as $cuenta)
                    <tr class="item-cuenta">
                        <td>
                            <div class="form-group row" id="cuenta">
                                <input type="hidden" name="cuenta[]" class="form-control iicuenta" readonly value="{{ $loop->index+1 }}" />
                                <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="{{$cuenta->cuentacontable_id ?? ''}}" >
                                <input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="{{$cuenta->cuentacontable_id ?? ''}}" >
                                <button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuenta tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigo form-control" name="codigos[]" value="{{$cuenta->cuentacontables->codigo ?? ''}}" >
                                <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="{{$cuenta->cuentacontables->codigo ?? ''}}" >
                            </div>
                        </td>							
                        <td>
                            <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombre form-control" name="nombres[]" value="{{$cuenta->cuentacontables->nombre ?? ''}}" readonly>
                        </td>
                        <td>
                            <select name="centrocosto_ids[]" data-placeholder="Centro de costo" class="centrocosto form-control" data-fouc>
                            </select>
                            <input type="hidden" class="centrocosto_id_previo" name="centrocosto_id_previo[]" value="{{old('centrocosto_ids', $cuenta->centrocosto_id ?? '')}}" >
                        </td>
                        <td>
                            <select name="moneda_ids[]" data-placeholder="Moneda" class="moneda form-control required" required data-fouc>
                                <option value="">-- Seleccionar --</option>
                                @foreach($moneda_query as $key => $value)
                                    @if( (int) $value->id == (int) old('moneda_ids[]', $cuenta->moneda_id ?? ''))
                                        <option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
                                    @else
                                        <option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
                                    @endif
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number" name="debes[]" class="form-control debe" value="{{old('debes[]', ($cuenta->monto > 0 ? $cuenta->monto : '') ?? '')}}">
                        </td>
                        <td>
                            <input type="number" name="haberes[]" class="form-control haber" value="{{old('haberes[]', ($cuenta->monto < 0 ? abs($cuenta->monto) : '') ?? '')}}">
                        </td>
                        <td>
                            <input type="number" name="cotizaciones[]" class="form-control cotizacion" value="{{old('cotizaciones[]', $cuenta->cotizacion ?? '0')}}">
                        </td>
                        <td>
                            <input type="text" name="observaciones[]" class="form-control observacion" value="{{old('observaciones[]', $cuenta->observacion ?? '')}}">
                        </td>
                        <td>
                            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuenta tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            @endif
            </tbody>
        </table>
        @include('contable.asiento.template')
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group row">
                    <button id="agrega_renglon_cuenta" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
                </div>
            </div>
            <div class="form-group row">
                <label for="totaldebe" class="col-lg-3 col-form-label">Total debe</label>
                <input type="text" id="totaldebe" name="totaldebe" class="form-control col-lg-3" readonly value="" />
                <label for="totaldebe" class="col-lg-3 col-form-label">Total haber</label>
                <input type="text" id="totalhaber" name="totalhaber" class="form-control col-lg-3" readonly value="" />
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
@include('includes.contable.modalconsultacuentacontable')
@include('contable.asiento.copiarasientomodal')
@include('contable.asiento.revertirasientomodal')

