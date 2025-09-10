<div class="row">
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="nombre" class="col-lg-4 col-form-label requerido">Nombre</label>
            <div class="col-lg-6">
                <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
            </div>
        </div>
        <div class="form-group row">
            <label for="Area de destino" class="col-lg-4 col-form-label">Area de destino</label>
            <select name="areadestino_id" id="areadestino_id" data-placeholder="Area de destino" class="col-lg-5 form-control" required data-fouc>
                <option value="">-- Seleccionar area de destino --</option>
                @foreach($areadestino_query as $key => $value)
                    @if( (int) $value->id == (int) old('areadestino_id', $data->areadestino_id ?? session('areadestino_id')))
                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                    @endif
                @endforeach
            </select>
        </div>
    </div>
</div>
<h4>Subcategorías</h4>
<div class="card-body">
    <table class="table" id="subcategoria-ticket-table">
        <thead>
            <tr>
                <th style="width: 6%;"></th>
                <th style="width: 40%;">Nombre</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tbody-subcategoria-ticket-table">
        @if ($data->subcategoria_tickets ?? '') 
            @foreach (old('subcategoria', $data->subcategoria_tickets->count() ? $data->subcategoria_tickets : ['']) as $subcategorias)
                <tr class="item-subcategoria-ticket">
                    <td>
                        <input type="text" name="subcategoria[]" class="form-control iisubcategoria" readonly value="{{ $loop->index+1 }}" />
                    </td>
                    <td>
                        <input type="text" class="nombre_subcategoria form-control" name="nombre_subcategorias[]" value="{{$subcategorias->nombre ?? ''}}">
                    </td>
                    <td>
                        <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_subcategoria tooltipsC">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    @include('ticket.categoria_ticket.template')
    <div class="row">
        <div class="col-md-12">
            <button id="agrega_renglon_subcategoria" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        </div>
    </div>
</div>

