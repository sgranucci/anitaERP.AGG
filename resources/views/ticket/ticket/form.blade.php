<div class="card form1">
    <div id="form-errors"></div>
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="fecha" class="col-lg-3 col-form-label">Fecha</label>
                <div class="col-lg-3">
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{old('fecha', $data->fecha ?? date('Y-m-d'))}}">
                </div>
            </div>
            <div class="form-group row">
                <label for="sala" class="col-lg-3 col-form-label">Sala</label>
                <select name="sala_id" id="sala_id" data-placeholder="Sala" class="col-lg-7 form-control required" data-fouc required>
                    <option value="">-- Seleccionar sala --</option>
                    @foreach($sala_query as $key => $value)
                        @if( (int) $value->id == (int) old('sala_id', $data->sala_id ?? ''))
                            <option value="{{ $value->id }}" selected="select">{{ $value->id }} {{ $value->nombre }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="form-group row">
                <label for="sector" class="col-lg-3 col-form-label">Sector</label>
                <select name="sector_id" id="sector_id" data-placeholder="Sector" class="col-lg-7 form-control required" data-fouc required>
                    <option value="">-- Seleccionar sector --</option>
                    @foreach($sector_query as $key => $value)
                        @if( (int) $value->id == (int) old('sector_id', $data->sector_id ?? ''))
                            <option value="{{ $value->id }}" selected="select">{{ $value->id }} {{ $value->nombre }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="form-group row">
                <label for="areadestino" class="col-lg-3 col-form-label">Area de destino</label>
                <select name="areadestino_id" id="areadestino_id" data-placeholder="Area de destino del ticket" class="col-lg-7 form-control required" data-fouc required>
                    <option value="">-- Seleccionar --</option>
                    @foreach($areadestino_query as $key => $value)
                        @if( (int) $value->id == (int) old('areadestino_id', $data->areadestino_id ?? ''))
                            <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row" id="div-categoria_ticket">
                <label for="categoria_ticket" class="col-lg-3 col-form-label">Categoría</label>
                <input type="text" class="col-lg-2" id="categoria_ticket_id" name="categoria_ticket_id" value="{{$data->subcategoria_tickets->categoria_ticket_id??''}}" >
                <button type="button" title="Consulta categorías" style="padding:1;" class="btn-accion-tabla consultacategoria_ticket tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="col-lg-6 form-control" id="nombrecategoria_ticket" name="nombrecategoria_ticket" value="{{$data->subcategoria_tickets->categoria_tickets->nombre??''}}" >
            </div>
            <div class="form-group row" id="div-subcategoria_ticket">
                <label for="subcategoria_ticket" class="col-lg-3 col-form-label">Subcategoría</label>
                <input type="text" class="col-lg-2" id="subcategoria_ticket_id" name="subcategoria_ticket_id" value="{{$data->subcategoria_ticket_id??''}}" >
                <button type="button" title="Consulta subcategorías" style="padding:1;" class="btn-accion-tabla consultasubcategoria_ticket tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="col-lg-6 nombresubcategoria_ticket form-control" id="nombresubcategoria_ticket" name="nombresubcategoria_ticket" value="{{$data->subcategoria_tickets->nombre??''}}" >
            </div>
        </div>        
    </div>
    <div class="col-md-6">
        <!-- textarea -->
        <div class="form-group">
            <label>Detalle</label>
            <textarea name="detalle" class="form-control" rows="3" placeholder="Detalle ...">{{old('detalle', $data->detalle ?? '')}}</textarea>
        </div>
    </div>
    <input type="hidden" id="id" name="id" value="{{ $data->id ?? '' }}" />
    <input type="hidden" id="usuario_id" name="usuario_id" value="{{ $data->usuario_id ?? '' }}" />
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
@include('includes.ticket.modalconsultacategoria')
@include('includes.ticket.modalconsultasubcategoria')


