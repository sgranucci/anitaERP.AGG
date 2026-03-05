<div class="row">
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="empresa" class="col-lg-3 col-form-label">Empresa</label>
            <select name="empresa_id" id="empresa_id" data-placeholder="Empresa" class="col-lg-7 form-control required" data-fouc required>
                <option value="">-- Seleccionar empresa --</option>
                @foreach($empresa_query as $key => $value)
                    @if( (int) $value->id == (int) old('empresa_id', $data->empresa_id ?? session('empresa_id')))
                        <option value="{{ $value->id }}" selected="select">{{ $value->id }} {{ $value->nombre }}</option>    
                    @else
                        <option value="{{ $value->id }}">{{ $value->id }} {{ $value->nombre }}</option>    
                    @endif
                @endforeach
            </select>
            <input type="hidden" class="codigoempresa" id="codigoempresa" name="codigoempresa" value="{{$data->empresas->codigo ?? ''}}" readonly>
        </div>
        <div class="form-group row">
            <label for="tipotransaccion_compra" class="col-lg-3 col-form-label">Tipo de transacción</label>
            <select name="tipotransaccion_compra_id" id="tipotransaccion_compra_id" data-placeholder="Tipo de transacción" class="col-lg-3 form-control required" data-fouc required>
                <option value="">-- Seleccionar --</option>
                @foreach($tipotransaccion_compra_query as $key => $value)
                    @if( (int) $value->id == (int) old('tipotransaccion_compra_id', $data->tipotransaccion_compra_id ?? session('tipotransaccioncobranza_compra_id')))
                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                    @endif
                @endforeach
                <input type="hidden" class="tipo" id="tipo" name="tipo" value="{{$data->tipotransaccion_compras->abreviatura ?? ''}}" readonly>
            </select>
            <input type="text" name="letra" id="letra" class="col-lg-1 form-control" placeholder="Letra" aria-label="Letra" value="{{$data->letra ?? ''}}" readonly>
            <span class="input-group-text">#</span>                
            <input type="text" name="sucursal" id="sucursal" class="col-lg-1 form-control" placeholder="Sucursal" aria-label="Sucursal" value="{{$data->sucursal ?? ''}}" readonly>
            <span class="input-group-text">#</span>                
            <input type="text" name="numerocomprobante" id="numerocomprobante" class="col-lg-2 form-control" placeholder="Numero de Comprobante" aria-label="Numero de Comprobante" value="{{$data->numerocomprobante ?? ''}}" readonly>
        </div>
        <div class="form-group row" id="div-proveedor">
            <label for="proveedor" class="col-lg-3 col-form-label">Proveedor</label>
            <input type="text" class="col-lg-1 proveedor_id proveedor_id_local" id="proveedor_id" name="proveedor_id" value="{{$data->proveedor_id ?? ''}}" >
            <input type="text" class="col-lg-6 nombreproveedor" id="nombreproveedor" name="nombreproveedor" value="{{$data->proveedores->nombre ?? ''}}" readonly>
            <input type="hidden" class="codigoproveedor" id="codigoproveedor" name="codigoproveedor" value="{{$data->proveedores->codigo ?? ''}}" readonly>
            <a href="{{route('editar_proveedor', ['id' => $data->proveedor_id ?? 0])}}" style="display: flex; align-items: center;" class="btn-accion-tabla tooltipsC editarproveedor" title="Editar este registro">
                <i class="fa fa-edit"></i>
            </a>                
        </div>
    </div>
    <div class="col-sm-6">
    	<div class="form-group row">
			<label for="fechafactura" class="col-lg-4 col-form-label requerido">Fecha Comprobante</label>
			<div class="col-lg-3">
				<input type="date" name="fechafactura" id="fechafactura" class="form-control" value="{{substr(old('fechafactura', $data->fechafactura ?? date('Y-m-d')),0,10)}}" required>
			</div>
		</div>
        <div class="form-group row">
			<label for="fecharecepcionemail" class="col-lg-4 col-form-label requerido">Fecha Recepcion Email</label>
			<div class="col-lg-3">
				<input type="date" name="fecharecepcionemail" id="fecharecepcionemail" class="form-control" value="{{substr(old('fecharecepcionemail', $data->fecharecepcionemail ?? date('Y-m-d')),0,10)}}" required>
			</div>
		</div>
        <div class="form-group row">
            <label for="numeroordencompra" class="col-lg-4 col-form-label requerido">Orden de Compra</label>
            <div class="col-lg-6">
                <input type="text" name="numeroordencompra" id="numeroordencompra" class="form-control" data-placeholder="Nombre para inteligencia artificial" value="{{old('numeroordencompra', $data->numeroordencompra ?? '')}}" required/>
            </div>
        </div>        
    </div>
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="numerocae" class="col-lg-3 col-form-label requerido">CAE</label>
            <div class="col-lg-4">
                <input type="text" name="numerocae" id="numerocae" class="form-control" value="{{old('numerocae', $data->numerocae ?? '')}}" readonly/>
            </div>
			<label for="fechavencimientocaicae" class="col-lg-3 col-form-label requerido">Vencimiento</label>
			<div class="col-lg-2">
				<input type="date" name="fechavencimientocaicae" id="fechavencimientocaicae" class="form-control" value="{{substr(old('fechavencimientocaicae', $data->fechavencimientocaicae ?? date('Y-m-d')),0,10)}}" readonly>
			</div>
		</div>   
        <div class="form-group row">
			<label for="moneda" class="col-lg-3 col-form-label requerido">Moneda</label>
			<div class="col-lg-2">
				<input type="text" name="moneda" id="moneda" class="form-control" value="{{$data->monedas->nombre ?? ''}}" readonly>
			</div>
			<label for="Total" class="col-lg-2 col-form-label">Cotizacion</label>
            <div class="col-lg-3">
			    <input type="text" id="cotizacion" name="cotizacion" class="form-control" value="{{number_format($data->cotizacion ?? 0, 2)}}" readonly></input>
            </div>
		</div>           
    </div>
    <div class="col-sm-6">
        <div class="form-group row">
			<label for="rutaalmacenamiento" class="col-lg-4 col-form-label requerido">Ruta del Archivo</label>
			<div class="col-lg-6">
				<input type="text" name="rutaalmacenamiento" id="rutaalmacenamiento" class="form-control" value="{{$data->rutaalmacenamiento ?? ''}}" readonly>
			</div>
		</div>   
        <div class="form-group row">
			<label for="pararevisar" class="col-lg-4 col-form-label requerido">Para Revisar</label>
			<div class="col-lg-3">
				<input type="text" name="pararevisar" id="pararevisar" class="form-control" value="{{$data->pararevisar ?? 0 == 1 ? 'PARA REVISAR' : 'SIN ERRORES'}}" readonly>
			</div>
		</div>       
    	<div class="form-group row">
			<label for="Subtotal" class="col-lg-4 col-form-label">Subtotal</label>
            <div class="col-lg-3">
			    <input type="text" id="subtotal" name="subtotal" class="form-control" value="{{number_format($data->subtotal ?? 0, 2)}}"></input>
            </div>
		</div>
		<div class="form-group row">
			<label for="Total" class="col-lg-4 col-form-label">Total</label>
            <div class="col-lg-3">
			    <input type="text" id="total" name="total" class="form-control" value="{{number_format($data->total ?? 0, 2)}}"></input>
            </div>
		</div>              
    </div>
</div>
<h3>Conceptos</h3>
<div class="card-body">
    <table class="table" id="concepto-table">
        <thead>
            <tr>
                <th style="width: 20%;">Concepto</th>
                <th style="width: 15%;">Monto</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tbody-concepto-table">
        @if ($data->precarga_comprobante_proveedor_conceptos ?? '') 
            @foreach ($data->precarga_comprobante_proveedor_conceptos as $precarga_concepto)
                <tr class="item-concepto">
                    <td>
                        <select name="concepto_ivacompra_ids[]" data-placeholder="Concepto de iva compra" class="form-control concepto_ivacompra_id" data-fouc>
                            <option value="">-- Elija concepto de iva compra --</option>
                            @foreach ($concepto_ivacompra_query as $concepto)
                                <option value="{{ $concepto->id }}"
                                    @if (old('concepto_ivacompra_ids', $precarga_concepto->concepto_ivacompra_id ?? '') == $concepto->id) selected @endif
                                    >{{ $concepto->nombre }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" name="montos[]" class="form-control monto" value="{{ $precarga_concepto->monto }}" />
                    </td>                    
                    <td>
                        <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_concepto tooltipsC">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    @include('compras.precarga_comprobante_proveedor.template')
    <div class="row">
        <div class="col-md-12">
            <button id="agrega_renglon_concepto" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        </div>
    </div>
</div>

