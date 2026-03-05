<div class="card form1">
        <div class="form-group row">
            <label for="fecha" class="col-lg-3 col-form-label">Fecha</label>
            <div class="col-lg-3">
                <input type="date" name="fecha" id="fecha" class="form-control" value="{{old('fecha', $data->fecha ?? date('Y-m-d'))}}">
            </div>
        </div>
    <input type="hidden" id="id" name="id" value="{{ $data->id ?? '' }}" />
    <h3>Cuentas</h3>
    <div class="card-body">
        <table class="table" id="cotizacion-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Moneda</th>
                    <th style="width: 20%;">Cotización Venta</th>
                    <th style="width: 20%;">Cotización Compra</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-cotizacion-table">
            @if ($data->cotizacion_monedas ?? '') 
                @foreach (old('cotizacion', $data->cotizacion_monedas->count() ? $data->cotizacion_monedas : ['']) as $cotizacion)
                    <tr class="item-cotizacion">
                        <td>
                            <select name="moneda_ids[]" data-placeholder="Moneda" class="moneda form-control required" required data-fouc>
                                <option value="">-- Seleccionar --</option>
                                @foreach($moneda_query as $key => $value)
                                    @if( (int) $value->id == (int) old('moneda_ids[]', $cotizacion->moneda_id ?? ''))
                                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                                    @else
                                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                                    @endif
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number" name="cotizacionventas[]" class="form-control cotizacionventa" value="{{old('cotizacionventas[]', $cotizacion->cotizacionventa ?? '0')}}">
                        </td>
                        <td>
                            <input type="number" name="cotizacioncompras[]" class="form-control cotizacioncompra" value="{{old('cotizacioncompras[]', $cotizacion->cotizacioncompra ?? '0')}}">
                        </td>
                        <td>
                            <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cotizacion tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            @endif
            </tbody>
        </table>
        @include('configuracion.cotizacion.template')
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group row">
                    <button id="agrega_renglon_cotizacion" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
                </div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />

