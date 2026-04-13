<div class="row">
    <div class="col-sm-6">  
        <div class="form-group row">
            <label for="fechainicio" class="col-lg-3 col-form-label">Inicio</label>
            <div class="col-lg-3">
                <input type="datetime" name="fechainicio" id="fechainicio" class="form-control" value="{{old('fechainicio', $data->fechainicio ?? date('Y-m-d'))}}">
            </div>
        </div>
        <div class="form-group row">
            <label for="fechafinalizacion" class="col-lg-3 col-form-label">Finalizacion</label>
            <div class="col-lg-3">
                <input type="datetime" name="fechafinalizacion" id="fechafinalizacion" class="form-control" value="{{old('fechafinalizacion', $data->fechafinalizacion ?? date('Y-m-d'))}}">
            </div>
        </div>
        <div class="form-group row" id="articulo">
            <label for="articulo" class="col-lg-3 col-form-label">Artículo</label>
            <input type="hidden" class="col-lg-3" name="articulo_id" value="{{$data->articulo_id ?? ''}}" >
            <button type="button" title="Consulta articulos" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC">
                    <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="col-lg-2 codigoarticulo codigoarticulolocal form-control" id="codigoarticulo" name="codigoarticulo" value="{{$data->articulos->sku ?? ''}}" >
            <input type="text" class="col-lg-6 descripcionarticulo form-control" id="descripcionarticulo" name="descripcionarticulo" value="{{$data->articulos->descripcion ?? ''}}">
        </div>
        <div class="form-group row">
            <label for="lineallenado" class="col-lg-3 col-form-label">Linea de llenado</label>
            <select name="lineallenado_id" id="lineallenado_id" data-placeholder="Lineallenado" class="col-lg-7 form-control required" data-fouc required>
                @foreach($lineallenado_query as $key => $value)
                    @if( (int) $value->id == (int) old('lineallenado_id', $data->lineallenado_id ?? ''))
                        <option value="{{ $value->id }}" selected="select">{{ $value->id }} {{ $value->nombre }}</option>    
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                    @endif
                @endforeach
            </select>
        </div>   
        <div class="form-group row">
            <label for="provienebin" class="col-lg-3 col-form-label">Proviene de Bines</label>
            <select name="provienebin_id" id="provienebin_id" data-placeholder="Provienebin" class="col-lg-7 form-control required" data-fouc required>
                @foreach($provienebin_query as $key => $value)
                    @if( (int) $value->id == (int) old('provienebin_id', $data->provienebin_id ?? ''))
                        <option value="{{ $value->id }}" selected="select">{{ $value->id }} {{ $value->nombre }}</option>    
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                    @endif
                @endforeach
            </select>
        </div>   
    </div>
    <div class="col-sm-6">
    	<div class="form-group row">
			<label for="recipient-name" class="col-lg-4 col-form-label">Número de Orden de Producción</label>
			<input type="text" id="numeroordenproduccion" name="numeroordenproduccion" value="{{$data->numeroordenproduccion ?? ''}}"></input>
		</div>
    	<div class="form-group row">
			<label for="recipient-name" class="col-lg-4 col-form-label">Tipo de producto</label>
			<input type="text" id="tipoproducto" name="tipoproducto" value=""></input>
		</div>
    	<div class="form-group row">
			<label for="recipient-name" class="col-lg-4 col-form-label">Capacidad</label>
			<input type="text" id="capacidad" name="capacidad" value=""></input>
		</div>
    	<div class="form-group row">
			<label for="recipient-name" class="col-lg-4 col-form-label">Marca</label>
			<input type="text" id="marca" name="marca" value=""></input>
		</div>
    	<div class="form-group row">
			<label for="recipient-name" class="col-lg-4 col-form-label">Tipo de color</label>
			<input type="text" id="tipocolor" name="tipocolor" value=""></input>
		</div>
    	<div class="form-group row">
			<label for="recipient-name" class="col-lg-4 col-form-label">Liquido de Freno Tipo</label>
			<input type="text" id="tipoliquidofreno" name="liquidofrenotipo" value=""></input>
		</div>
    </div>
</div>  
<div class="form-group row">
    <label for="recipient-name" class="col-lg-4 col-form-label">Cantidad de envases</label>
    <input type="text" id="cantidad" name="cantidad" value="{{$data->cantidad ?? ''}}"></input>
</div> 
<div class="form-group row">
    <label for="recipient-name" class="col-lg-4 col-form-label">Lote</label>
    <input type="text" id="lote" name="lote" value="{{$data->lote ?? ''}}"></input>
</div> 
<div class="col-sm-6">
    <!-- textarea -->
    <div class="form-group" id="div_observacion">
        <label>Observaciones</label>
        <textarea id="observacion" name="observacion" class="form-control" cols="40" rows="4" placeholder="Observaciones ...">{{$data->observacion ?? ''}}</textarea>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
