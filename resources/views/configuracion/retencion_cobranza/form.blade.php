<div class="form1">
    <div class="form-group row">
        <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
        <div class="col-lg-8">
            <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
        </div>
    </div>
    <div class="form-group row">
        <label for="tiporetencion" class="col-lg-3 col-form-label requerido">Tipo de retención</label>
        <select id="tiporetencion" name="tiporetencion" class="col-lg-3 form-control">
            <option value="">-- Elija tipo de retencion --</option>
            @foreach ($tiporetencion_enum as $value => $tiporetencion)
                <option value="{{ $tiporetencion }}"
                    @if (old('tiporetencion', $data->tiporetencion ?? '') == $tiporetencion) selected @endif
                >{{ $tiporetencion }}</option>
            @endforeach
        </select>
    </div>
    <div id="provincia" class="form-group row" style="display: none">
        <label class="col-lg-3 col-form-label">Provincia</label>
        <div class="form-group row col-lg-8">
            <input type="hidden" id="provincia_id_previa" name="provincia_id_previa" value="{{$data->provincia_id}}" >
            <input type="hidden" id="desc_provincia" name="desc_provincia" value="{{$data->provincias->nombre??''}}" >
            <input type="hidden" class="col-form-label provincia_id" id="provincia_id" name="provincia_id" value="{{$data->provincia_id}}" >
            <input type="text" class="form-control col-lg-2 codigoprovincia" id="codigoprovincia" name="codigoprovincia" value="{{$data->provincias->codigo}}" >
            <input type="text" class="form-control col-lg-4 nombreprovincia" id="nombreprovincia" name="nombreprovincia" value="{{$data->provincias->nombre??''}}" readonly>
            <button type="button" title="Consulta provinciaes" style="padding:1;" class="btn-accion-tabla consultaprovincia tooltipsC">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="hidden" name="nombreprovincia" id="nombreprovincia" class="form-control" value="">
        </div>
    </div>
</div>
@include('includes.configuracion.modalconsultaprovincia')