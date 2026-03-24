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
        </div>        
        <div class="form-group row">
            <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
            <div class="col-lg-6">
            <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
            </div>
        </div>
        <div class="form-group row">
            <label for="cuit" class="col-lg-3 col-form-label requerido">CUIT</label>
            <div class="col-lg-6">
                <input type="text" name="cuit" id="cuit" class="form-control" value="{{old('cuit', $data->cuit ?? '')}}" required/>
            </div>
        </div>        
        <div class="form-group row">
            <label for="impuesto" class="col-lg-3 col-form-label requerido">Impuesto</label>
            <input type="text" name="impuesto" id="impuesto" class="col-lg-2 form-control" value="{{old('impuesto', $data->impuesto ?? '')}}" required/>
            <input type="text" name="descripcionimpuesto" id="descripcionimpuesto" class="col-lg-6 form-control" value="{{old('descripcionimpuesto', $data->descripcionimpuesto ?? '')}}" required/>
        </div>
        <div class="form-group row">
            <label for="regimen" class="col-lg-3 col-form-label requerido">Régimen</label>
            <input type="text" name="regimen" id="regimen" class="col-lg-2 form-control" value="{{old('regimen', $data->regimen ?? '')}}" required/>
            <input type="text" name="descripcionregimen" id="descripcionregimen" class="col-lg-6 form-control" value="{{old('descripcionregimen', $data->descripcionregimen ?? '')}}" required/>
        </div>
        <div class="form-group row">
            <label for="fecharetencion" class="col-lg-3 col-form-label requerido">Fecha de Retención</label>
            <div class="col-lg-3">
            <input type="date" name="fecharetencion" id="fecharetencion" class="form-control" value="{{old('fecharetencion', $data->fecharetencion ?? '')}}"/>
            </div>
        </div>
        <div class="form-group row">
            <label for="numerocertificado" class="col-lg-3 col-form-label requerido">Número de Certificado</label>
            <div class="col-lg-3">
            <input type="text" name="numerocertificado" id="numerocertificado" class="form-control" value="{{old('numerocertificado', $data->numerocertificado ?? '')}}"/>
            </div>
        </div>        
    </div>
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="descripcionoperacion" class="col-lg-3 col-form-label requerido">Operación</label>
            <div class="col-lg-5">
                <input type="text" name="descripcionoperacion" id="descripcionoperacion" class="form-control" value="{{old('descripcionoperacion', $data->descripcionoperacion ?? '')}}"/>
            </div>
        </div>
        <div class="form-group row">
            <label for="montoretencion" class="col-lg-3 col-form-label requerido">Monto</label>
            <div class="col-lg-3">
                <input type="text" name="montoretencion" id="montoretencion" class="form-control" value="{{old('montoretencion', $data->montoretencion ?? '')}}"/>
            </div>
        </div>
        <div class="form-group row">
            <label for="numerocomprobante" class="col-lg-3 col-form-label requerido">Número de Comprobante</label>
            <div class="col-lg-3">
                <input type="text" name="numerocomprobante" id="numerocomprobante" class="form-control" value="{{old('numerocomprobante', $data->numerocomprobante ?? '')}}"/>
            </div>
        </div>
        <div class="form-group row">
            <label for="fechacomprobante" class="col-lg-3 col-form-label requerido">Fecha de Comprobante</label>
            <div class="col-lg-3">
                <input type="date" name="fechacomprobante" id="fechacomprobante" class="form-control" value="{{old('fechacomprobante', $data->fechacomprobante ?? '')}}"/>
            </div>
        </div>
        <div class="form-group row">
            <label for="descripcioncomprobante" class="col-lg-3 col-form-label requerido">Comprobante</label>
            <div class="col-lg-5">
                 <input type="text" name="descripcioncomprobante" id="descripcioncomprobante" class="form-control" value="{{old('descripcioncomprobante', $data->descripcioncomprobante ?? '')}}"/>
            </div>
        </div>
        <div class="form-group row">
            <label for="fecharegistracion" class="col-lg-3 col-form-label requerido">Fecha de Registración</label>
            <div class="col-lg-3">
                <input type="date" name="fecharegistracion" id="fecharegistracion" class="form-control" value="{{old('fecharegistracion', $data->fecharegistracion ?? '')}}"/>
            </div>
        </div>
    </div>
</div>