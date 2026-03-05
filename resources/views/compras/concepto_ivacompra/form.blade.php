<div class="row">
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
            <div class="col-lg-6">
            <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
            </div>
        </div>
        <div class="form-group row">
            <label for="nombre_ia" class="col-lg-3 col-form-label requerido">Nombre IA</label>
            <div class="col-lg-6">
                <input type="text" name="nombre_ia" id="nombre_ia" class="form-control" data-placeholder="Nombre para inteligencia artificial" value="{{old('nombre_ia', $data->nombre_ia ?? '')}}" required/>
            </div>
        </div>        
        <div class="form-group row">
            <label for="tipoconcepto" class="col-lg-3 col-form-label requerido">Tipo de concepto</label>
            <select name="tipoconcepto" class="col-lg-3 form-control" required>
                <option value="">-- Elija tipo de concepto --</option>
                @foreach ($tipoconcepto_enum as $tipoconcepto)
                    <option value="{{ $tipoconcepto['valor'] }}"
                        @if (old('tipoconcepto', $data->tipoconcepto ?? '') == $tipoconcepto['valor']) selected @endif
                        >{{ $tipoconcepto['nombre'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="tipoe" class="col-lg-3 col-form-label">Colúmna de Iva Compra</label>
            <select name="columna_ivacompra_id" id="columna_ivacompra_id" data-placeholder="Columna iva compras" class="col-lg-5 form-control required" data-fouc>
                <option value="">-- Seleccionar --</option>
                @foreach($columna_ivacompra_query as $key => $value)
                    @if( (int) $value->id == (int) old('columna_ivacompra_id', $data->columna_ivacompra_id ?? ''))
                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="retieneganancia" class="col-lg-3 col-form-label requerido">Retiene Ganancias</label>
            <select name="retieneganancia" class="col-lg-3 form-control" required>
                <option value="">-- Elija retiene iva --</option>
                @foreach ($retiene_enum as $retiene)
                    <option value="{{ $retiene['valor'] }}"
                        @if (old('retieneganancia', $data->retieneganancia ?? '') == $retiene['valor']) selected @endif
                        >{{ $retiene['nombre'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="retieneIIBB" class="col-lg-3 col-form-label requerido">Retiene IIBB</label>
            <select name="retieneIIBB" class="col-lg-3 form-control" required>
                <option value="">-- Elija retiene IIBB --</option>
                @foreach ($retiene_enum as $retiene)
                    <option value="{{ $retiene['valor'] }}"
                        @if (old('retieneIIBB', $data->retieneIIBB ?? '') == $retiene['valor']) selected @endif
                        >{{ $retiene['nombre'] }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group row">
            <label class="col-lg-3 col-form-label">Provincia</label>
            <select name="provincia_id" id="provincia_id" data-placeholder="Provincia" class="col-lg-4 form-control" data-fouc>
                <option value="">-- Seleccionar --</option>
                @foreach($provincia_query as $key => $value)
                    @if( (int) $value->id == (int) old('provincia_id', $data->provincia_id ?? ''))
                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }} Jur: {{ $value->jurisdiccion}} </option>    
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }} Jur: {{ $value->jurisdiccion}}</option>    
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label class="col-lg-3 col-form-label">Código de impuesto</label>
            <select name="impuesto_id" id="impuesto_id" data-placeholder="Impuesto" class="col-lg-3 form-control" data-fouc>
                <option value="">-- Seleccionar --</option>
                @foreach($impuesto_query as $key => $value)
                    @if( (int) $value->id == (int) old('impuesto_id', $data->impuesto_id ?? ''))
                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="cuentacontabledebe" class="col-lg-3 col-form-label">Cuenta contable Debe</label>
            <select name="cuentacontabledebe_id" id="cuentacontabledebe_id" data-placeholder="Cuenta contable para imputaciones de facturas y débitos" class="col-lg-4 form-control" data-fouc>
                <option value="">-- Seleccionar Cta. Contable --</option>
                @foreach($cuentacontable_query as $key => $value)
                    @if( (int) $value->id == (int) old('cuentacontabledebe_id', $data->cuentacontabledebe_id ?? ''))
                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="cuentacontablehaber" class="col-lg-3 col-form-label">Cuenta contable Haber</label>
            <select name="cuentacontablehaber_id" id="cuentacontablehaber_id" data-placeholder="Cuenta contable para imputaciones de notas de créditos" class="col-lg-4 form-control" data-fouc>
                <option value="">-- Seleccionar Cta. Contable --</option>
                @foreach($cuentacontable_query as $key => $value)
                    @if( (int) $value->id == (int) old('cuentacontablehaber_id', $data->cuentacontablehaber_id ?? ''))
                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                    @endif
                @endforeach
            </select>
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="formula" class="col-lg-3 col-form-label">Fórmula</label>
    <div class="col-lg-8">
    <input type="text" name="formula" id="formula" class="form-control" value="{{old('formula', $data->formula ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código Anita</label>
    <div class="col-lg-2">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" required>
    </div>
</div>
<h3>Condiciones de iva en las que es usado el concepto</h3>
<div class="card-body">
    <table class="table" id="condicioniva-table">
        <thead>
            <tr>
                <th style="width: 4%;"></th>
                <th style="width: 20%;">Condición de iva</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tbody-condicioniva-table">
        @if ($data->concepto_ivacompra_condicionivas ?? '') 
            @foreach (old('condicioniva', $data->concepto_ivacompra_condicionivas->count() ? $data->concepto_ivacompra_condicionivas : ['']) as $condicionivas)
                <tr class="item-condicioniva">
                    <td>
                        <input type="text" name="condicioniva[]" class="form-control iicondicioniva" readonly value="{{ $loop->index+1 }}" />
                    </td>
                    <td>
                        <select name="condicioniva_ids[]" id="condicioniva_ids" data-placeholder="Condición de iva" class="form-control condicioniva_id" data-fouc>
                            <option value="">-- Elija condición de iva --</option>
                            @foreach ($condicioniva_query as $condicioniva)
                                <option value="{{ $condicioniva->id }}"
                                    @if (old('condicioniva', $condicionivas->condicioniva_id ?? '') == $condicioniva->id) selected @endif
                                    >{{ $condicioniva->nombre }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_condicioniva tooltipsC">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    @include('compras.concepto_ivacompra.template')
    <div class="row">
        <div class="col-md-12">
            <button id="agrega_renglon_condicioniva" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        </div>
    </div>
</div>

