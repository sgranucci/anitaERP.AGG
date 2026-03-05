<div class="form-group">
    <label>
        Artículo
    </label>    
    <input  type="text" 
            name="avioa_material" 
            class="form-control" 
            value="@if(isset($avios)){{ $avios->avioa_material}}@else{{ old('avioa_material') }}@endif"/>
</div>
<div class="form-group">
    <label>
        Color
    </label>    
    <input  type="text" 
            name="avioa_color" 
            class="form-control" 
            value="@if(isset($avios)){{ $avios->avioa_color}}@else{{ old('avioa_color') }}@endif"/>
</div>
<div class="form-group">
    <label>
        16/26
    </label>    
    <input  type="text" 
            name="avioa_consumo1" 
            class="form-control" 
            value="@if(isset($avios)){{ $avios->avioa_consumo1}}@else{{ old('avioa_consumo1') }}@endif"/>
</div>
<div class="form-group">
    <label>
        27/33
    </label>    
    <input  type="text" 
            name="avioa_consumo2" 
            class="form-control" 
            value="@if(isset($avios)){{ $avios->avioa_consumo2}}@else{{ old('avioa_consumo2') }}@endif"/>
</div>
<div class="form-group">
    <label>
        34/40
    </label>    
    <input  type="text" 
            name="avioa_consumo3" 
            class="form-control" 
            value="@if(isset($avios)){{ $avios->avioa_consumo3}}@else{{ old('avioa_consumo3') }}@endif"/>
</div>
<div class="form-group">
    <label>
        41/45
    </label>    
    <input  type="text" 
            name="avioa_consumo4" 
            class="form-control" 
            value="@if(isset($avios)){{ $avios->avioa_consumo4}}@else{{ old('avioa_consumo4') }}@endif"/>
</div>
<div class="form-group">
    <label>
        Tipo avio
    </label>    
    <input  type="text" 
            name="avioa_tipo" 
            class="form-control" 
            value="@if(isset($avios)){{ $avios->avioa_tipo}}@else{{ old('avioa_tipo') }}@endif"/>
</div>
@isset($edit)
<button type="submit" class="btn btn-info" >
    Grabar
</button>
@else
<button type="submit" class="btn btn-info" >
    Crear
</button>
@endisset
