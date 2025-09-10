<div class="card form2" style="display: none">
    <h3>Artículos</h3>
    <div class="card-body">
        <table class="table" id="ticket-articulo-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Artículo</th>
                    <th>Descripción</th>
                    <th>Cantidad</th=>
                    <th>Requisición</th=>
                    <th>Recepción</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-ticket-articulo-table" class="container-articulo">
            @if ($data->ticket_articulos ?? '') 
                @foreach (old('articulo', $data->ticket_articulos->count() ? $data->ticket_articulos : ['']) as $articulo)
                        @if (isset($articulo->articulo_id))
                        <tr class="ticket-articulo">
                            <td>
                                <div class="form-group row" id="articulo">
                                    <input type="hidden" name="articulo[]" class="form-control iiarticulo" readonly value="{{ $loop->index+1 }}" />
                                    <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{$articulo->articulo_id ?? ''}}" >
                                    <input type="hidden" class="articulo_id_previa" name="articulo_id_previa[]" value="{{$articulo->articulo_id ?? ''}}" >
                                    <button type="button" title="Consulta articulos" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC">
                                            <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" style="WIDTH: 150px;HEIGHT: 38px" class="codigoarticulo form-control" name="codigoarticulos[]" value="{{$articulo->articulos->sku ?? ''}}" >
                                    <input type="hidden" class="codigo_previo_articulo" name="codigo_previo_articulos[]" value="{{$articulo->articulos->codigo ?? ''}}" >
                                </div>
                            </td>							
                            <td>
                                <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="descripcionarticulo form-control" name="descripcionarticulos[]" value="{{$articulo->articulos->descripcion ?? ''}}" readonly>
                            </td>
                            <td>
                                <input type="text" name="cantidades[]" class="form-control cantidad" value="{{old('cantidades[]', $articulo->cantidad ?? '')}}">
                            </td>
                            <td>
                                <input type="text" name="requisicion_ids[]" class="form-control requisicion_id" value="{{old('requisicion_ids[]', $articulo->requisicion_id ?? '')}}">
                            </td>
                                                    <td>
                                <input type="text" name="recepcion_ids[]" class="form-control recepcion_id" value="{{old('recepcion_ids[]', $articulo->recepcion_id ?? '')}}">
                            </td>
                            <td>
                                <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_ticket_articulo tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                                <input type="hidden" name="creousuarioarticulo_ids[]" class="form-control creousuarioarticulo_id" value="{{ $articulo->creousuario_id ?? ''}}" />
                            </td>
                        </tr>
                    @endif
                @endforeach
            @endif
            </tbody>
        </table>
        @include('ticket.administracion_ticket.template2')
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group row">
                    <button id="agrega_renglon_ticket_articulo" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
                </div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />

