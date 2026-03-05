<div class="form-group row">
    <label for="numerodespacho" class="col-lg-3 col-form-label requerido">Nro. Despacho</label>
    <div class="col-lg-8">
    <input type="text" name="numerodespacho" class="form-control" value="{{old('nombre', $data->numerodespacho ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label requerido">País de orígen</label>
    <select name="pais_id" id="pais_id" data-placeholder="País" class="col-lg-4 form-control required" data-fouc>
        <option value="">-- Seleccionar --</option>
        @foreach($pais_query as $key => $value)
            @if( (int) $value->id == (int) old('pais_id', $data->pais_id ?? ''))
                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
            @else
                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
            @endif
        @endforeach
    </select>
</div>
<div class="form-group row">
    <label for="fechaingreso" class="col-lg-3 col-form-label requerido">Fecha Ingreso</label>
    <div class="col-lg-2">
        <input type="date" name="fechaingreso" class="form-control" value="{{substr(old('fechaingreso', $data->fechaingreso ?? date('Y-m-d')),0,10)}}" required>
    </div>
</div>