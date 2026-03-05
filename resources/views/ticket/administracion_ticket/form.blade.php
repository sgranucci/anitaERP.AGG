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
            <div class="form-group row" id="div-subcategoria_ticket">
                <label for="bienuso" class="col-lg-3 col-form-label">Bien de uso intervenido</label>
                <input type="number" class="col-lg-6 bienuso_id form-control" id="bienuso_id" name="bienuso_id" value="{{$data->bienuso_id??''}}" >
            </div>
            <div class="form-group row">
                <label for="estado_ticket" class="col-lg-3 col-form-label">Estado del ticket</label>
                <select name="estado_ticket" id="estado_ticket" data-placeholder="Estado del ticket" class="col-lg-3 form-control required" data-fouc required>
                    <option value="">-- Seleccionar --</option>
                    @foreach($estado_enum as $value)
                        @if( $value['nombre'] == old('estado', $data->estado_ticket ?? ''))
                            <option value="{{ $value['nombre'] }}" selected="select">{{ $value['nombre'] }}</option>    
                        @else
                            <option value="{{ $value['nombre'] }}">{{ $value['nombre'] }}</option>    
                        @endif
                    @endforeach
                </select>
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
    <h4>Tareas</h4>
    <table style="font-size: 12px;" class="table" id="tarea-ticket-table">
        <thead>
            <tr>
                <th style="width: 24%;">Tarea</th>
                <th style="width: 10%;">Fecha carga</th>
                <th style="width: 10%;">Fecha program.</th>
                <th style="width: 18%;">Técnico</th>
                <th style="width: 5%;">Turno</th>
                <th>Fecha finalización</th>
                <th style="width: 7%;">Minutos</th>
                <th>Estado</th>
                <th style="width: 8%;"></th>
            </tr>
        </thead>
        <tbody id="tbody-tarea-ticket-table">
        @if ($data->ticket_tareas ?? '') 
            @foreach (old('tarea', $data->ticket_tareas->count() > 0? $data->ticket_tareas : ['']) as $tarea)
                @if (isset($tarea->tarea_id))
                    <tr class="item-tarea-ticket">
                        <td>
                            <input type="hidden" name="tarea_ticket[]" class="form-control iitarea_ticket" value="{{ $loop->index+1 }}" />
                            <input type="hidden" name="ticket_tarea_ids[]" class="form-control ticket_tarea_id" value="{{ $tarea->id ?? '' }}" />
                            <div class="form-group row" id="tarea_ticket">
                                <input type="text" style="WIDTH: 40px;HEIGHT: 38px" class="tarea_ticket_id" name="tarea_ticket_ids[]" value="{{$tarea->tarea_id ?? ''}}" >
                                <input type="hidden" class="tarea_ticket_id_previa" name="tarea_ticket_id_previa[]" value="{{$tarea->tarea_id ?? ''}}" >
                                <button type="button" title="Consulta tareas" style="padding:1;" class="btn-accion-tabla consultatarea_ticket tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" style="font-size: 12px;WIDTH: 250px;HEIGHT: 38px" class="nombretarea_ticket form-control" name="nombretarea_tickets[]" value="{{$tarea->tareas->nombre ?? ''}}" >
                            </div>
                        </td>
                        <td>
                            <input type="date" name="fechacargas[]" class="form-control fechacarga" value="{{old('fechacargas', $tarea->fechacarga ?? date('Y-m-d'))}}" readonly>
                        </td>
                        <td>
                            <input type="date" name="fechaprogramaciones[]" class="form-control fechaprogramacion" value="{{old('fechaprogramaciones', $tarea->fechaprogramacion ?? '')}}" required>
                        </td>
                        <td>
                            <div class="form-group row" id="tecnico_ticket">
                                <input type="text" style="WIDTH: 40px;HEIGHT: 38px" class="tecnico_ticket_id" name="tecnico_ticket_ids[]" value="{{$tarea->tecnico_id ?? ''}}" >
                                <input type="hidden" class="tecnico_ticket_id_previa" name="tecnico_ticket_id_previa[]" value="{{$tarea->tecnico_id ?? ''}}" >
                                <button type="button" title="Consulta técnicos" style="padding:1;" class="btn-accion-tabla consultatecnico_ticket tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" style="font-size: 12px; WIDTH: 140px;HEIGHT: 38px" class="nombretecnico_ticket form-control" name="nombretecnico_tickets[]" value="{{$tarea->tecnicos->nombre ?? ''}}" >
                            </div>
                        </td>
                        <td>
                            <select name="turno_ids[]" style="font-size: 12px;" data-placeholder="Turno" class="turno_id form-control" data-fouc>
                                <option value="">--</option>
                                @foreach($turno_query as $key => $value)
                                    @if( (int) $value->id == (int) old('turno_ids[]', $tarea->turno_id ?? ''))
                                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                                    @else
                                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                                    @endif
                                @endforeach
                            </select>
                        </td>     
                        <td>
                            <input type="date" name="fechafinalizaciones[]" class="form-control fechafinalizacion requerido" value="{{old('fechafinalizaciones', $tarea->fechafinalizacion ?? '')}}" readonly>
                        </td>          
                        <td>
                            <input type="number" style="font-size: 12px;" name="tiempoinsumidos[]" class="form-control tiempoinsumido" value="{{old('tiempoinsumido', $tarea->tiempoinsumido ?? '')}}" readonly>
                        </td>   
                        <td>
                            <input type="text" name="estadotareas[]" class="form-control estadotarea" value="" readonly>
                        </td>
                        <td>
                            @if ($tarea->fechafinalizacion < "2000-01-01")
                                <button type="button" title="Finaliza tarea" class="btn-accion-tabla finalizatarea tooltipsC">
                                    <i class="text-danger">Finaliza</i>
                                </button>     
                            @endif                       
                            <button type="button" title="Abre novedades" class="btn-accion-tabla abrenovedad tooltipsC">
                                <i class="text-primary">Novedades</i>
                            </button>
                            @if ($tarea->fechafinalizacion < "2000-01-01")
                                <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_tarea_ticket tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            @endif
                            <input type="hidden" name="creousuario_ids[]" class="form-control creousuario_id" value="{{ $tarea->creousuario_id ?? ''}}" />
                        </td>
                    </tr>
                @endif
            @endforeach
        @endif
        </tbody>
    </table>
    @include('ticket.administracion_ticket.template')
    <div class="row">
        <div class="col-md-12">
            <button id="agrega_renglon_tarea_ticket" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        </div>
    </div>
    <input type="hidden" id="id" name="id" value="{{ $data->id ?? '' }}" />
    <input type="hidden" id="usuario_id" name="usuario_id" value="{{ $data->usuario_id ?? '' }}" />
    <input type="hidden" id="estado_novedad_enum" name="estado_novedad_enum" value="{{ $estado_novedad_json ?? '' }}" />
    <input type="hidden" id="permiso_usuario" name="permiso_usuario" value="{{ can('supervisor-ticket', false) }}" />
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />

@include('includes.ticket.modalconsultacategoria')
@include('includes.ticket.modalconsultatarea_ticket')
@include('includes.ticket.modalconsultatecnico_ticket')
@include('includes.ticket.modalconsultasubcategoria')
@include('includes.stock.modalconsultaarticulo')
@include('ticket.administracion_ticket.modalcargatarea_novedad')


