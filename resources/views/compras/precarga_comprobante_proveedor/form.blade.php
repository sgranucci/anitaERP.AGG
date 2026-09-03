<div class="row">
    <div class="col-sm-6">
        @include('includes.form-empresa-asignada', [
            'empresa_query' => $empresa_query,
            'empresa_id' => $data->empresa_id ?? session('empresa_id'),
            'mostrar_id' => true,
            'col_label' => 'col-lg-3',
            'col_input' => 'col-lg-7',
        ])
        <input type="hidden" class="codigoempresa" id="codigoempresa" name="codigoempresa" value="{{ $data->empresas->codigo ?? '' }}" readonly>
        <div class="form-group row">
            <label for="tipotransaccion_compra" class="col-lg-3 col-form-label">Tipo de transacción</label>
            <select name="tipotransaccion_compra_id" id="tipotransaccion_compra_id" data-placeholder="Tipo de transacción" class="col-lg-3 form-control required" data-fouc required>
                <option value="">-- Seleccionar --</option>
                @foreach($tipotransaccion_compra_query as $key => $value)
                    @if( (int) $value->id == (int) old('tipotransaccion_compra_id', $data->tipotransaccion_compra_id ?? session('tipotransaccioncobranza_compra_id')))
                        <option value="{{ $value->id }}" data-abreviatura="{{ $value->abreviatura }}" selected="select">{{ $value->nombre }}</option>    
                    @else
                        <option value="{{ $value->id }}" data-abreviatura="{{ $value->abreviatura }}">{{ $value->nombre }}</option>    
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
        @include('includes.compras.campo_proveedor_consulta', [
            'proveedor_id' => ($data ?? null)?->proveedor_id,
            'codigo_proveedor' => ($data ?? null)?->proveedores?->codigo,
            'nombre_proveedor' => ($data ?? null)?->proveedores?->nombre,
            'requerido' => true,
        ])
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
            <label for="tipo_autorizacion" class="col-lg-3 col-form-label">Tipo autorización</label>
            <div class="col-lg-3">
                @php
                    $tipoAutPre = old('tipo_autorizacion', $data->tipo_autorizacion ?? (filled(old('numerocae', $data->numerocae ?? '')) ? 'CAE' : ''));
                @endphp
                <select name="tipo_autorizacion" id="tipo_autorizacion" class="form-control">
                    <option value="">—</option>
                    @foreach (\App\Support\Compras\ComprobanteProveedorTipoAutorizacion::todos() as $tipoOpt)
                        <option value="{{ $tipoOpt }}" @selected($tipoAutPre === $tipoOpt)>{{ $tipoOpt }}</option>
                    @endforeach
                </select>
            </div>
            <label for="numerocae" class="col-lg-2 col-form-label requerido">Nº CAE/CAEA</label>
            <div class="col-lg-4">
                <input type="text" name="numerocae" id="numerocae" class="form-control" value="{{old('numerocae', $data->numerocae ?? '')}}" readonly/>
            </div>
		</div>
        <div class="form-group row">
			<label for="fechavencimiento" class="col-lg-3 col-form-label">Vencimiento</label>
			<div class="col-lg-3">
				<input type="date" name="fechavencimiento" id="fechavencimiento" class="form-control"
                       value="{{substr(old('fechavencimiento', optional($data->fechavencimiento ?? null)->format('Y-m-d') ?? ($data->fechavencimiento ?? '')),0,10)}}" readonly>
			</div>
			<label for="fechavencimientocaicae" class="col-lg-2 col-form-label requerido">Vto. CAE</label>
			<div class="col-lg-3">
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
			    <input type="text" id="cotizacion" name="cotizacion" class="form-control" value="{{ number_format((float) ($data->cotizacion ?? 0), 4, ',', '.') }}" readonly>
            </div>
		</div>           
    </div>
    <div class="col-sm-6">
        <div class="form-group row">
			<label for="rutaalmacenamiento" class="col-lg-4 col-form-label requerido">Ruta del Archivo</label>
			<div class="col-lg-6">
				<input type="text" name="rutaalmacenamiento" id="rutaalmacenamiento" class="form-control" value="{{$data->rutaalmacenamiento ?? ''}}" readonly>
			</div>
            <div class="col-lg-2 d-flex align-items-center">
                @include('compras.precarga_comprobante_proveedor.partials.boton_ver_factura_pdf', [
                    'precargaId' => $data->id ?? null,
                    'rutaalmacenamiento' => $data->rutaalmacenamiento ?? null,
                ])
            </div>
		</div>   
        <div class="form-group row">
			<label for="pararevisar" class="col-lg-4 col-form-label requerido">Para Revisar</label>
			<div class="col-lg-3">
				<input type="text" name="pararevisar" id="pararevisar" class="form-control" value="{{$data->pararevisar ?? 0 == 1 ? 'PARA REVISAR' : 'SIN ERRORES'}}" readonly>
			</div>
		</div>
        @if (filled($data->marca_error ?? null))
        <div class="form-group row">
            <label class="col-lg-4 col-form-label">Marca de error</label>
            <div class="col-lg-8">
                <span class="badge badge-danger">{{ \App\Support\Compras\ComprobanteProveedorCotizacionIngresoSupport::etiquetaMarca($data->marca_error) ?: $data->marca_error }}</span>
                @if (filled($data->aviso_error ?? null))
                    <p class="text-danger mb-0 mt-1">{{ $data->aviso_error }}</p>
                @endif
            </div>
        </div>
        @endif       
    	<div class="form-group row">
			<label for="Subtotal" class="col-lg-4 col-form-label">Subtotal</label>
            <div class="col-lg-3">
			    <input type="text" id="subtotal" name="subtotal" class="form-control" value="{{ number_format((float) ($data->subtotal ?? 0), 2, ',', '.') }}">
            </div>
		</div>
		<div class="form-group row">
			<label for="Total" class="col-lg-4 col-form-label">Total</label>
            <div class="col-lg-3">
			    <input type="text" id="total" name="total" class="form-control" value="{{ number_format((float) ($data->total ?? 0), 2, ',', '.') }}">
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
                                    data-codigo-anita="{{ $concepto->codigo }}"
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

