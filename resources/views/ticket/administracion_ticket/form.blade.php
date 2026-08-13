<div class="form1">
    <div id="form-errors"></div>
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="fecha" class="col-lg-3 control-label text-right pr-2">Fecha</label>
                <div class="col-lg-3">
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{old('fecha', $data->fecha ?? date('Y-m-d'))}}">
                </div>
            </div>
            @php
                $esAltaTicket = ! isset($data) || empty($data->id);
                $usuarioIdVal = old(
                    'usuario_id',
                    $esAltaTicket
                        ? (Auth::user()->id ?? '')
                        : ($data->usuario_id ?? '')
                );
                $nombreUsuarioGenero = old(
                    'nombre_usuario_genero',
                    $esAltaTicket
                        ? (Auth::user()->nombre ?? Auth::user()->usuario ?? '')
                        : ($data->usuarios->nombre ?? $data->usuarios->usuario ?? '')
                );
            @endphp
            <div class="form-group row">
                <label for="nombre_usuario_genero" class="col-lg-3 control-label text-right pr-2">Gener&oacute; usuario</label>
                <div class="col-lg-7">
                    @if ($esAltaTicket)
                        <div class="d-flex flex-nowrap align-items-center tm-usuario-campo" style="gap: 4px;">
                            <input type="hidden" name="usuario_id" id="usuario_id" class="usuario_id" value="{{ $usuarioIdVal }}">
                            <input type="text"
                                   id="usuario_codigo"
                                   class="usuario_codigo_arbol form-control"
                                   style="flex: 0 0 90px; width: 90px;"
                                   value="{{ $usuarioIdVal }}"
                                   placeholder="ID"
                                   title="Usuario para quien se abre el ticket. Enter valida; F1 consulta"
                                   autocomplete="off"
                                   inputmode="numeric">
                            <button type="button"
                                    title="Consulta usuarios (F1)"
                                    class="btn-accion-tabla consultausuario tooltipsC"
                                    data-ptrusuario_id="#usuario_id"
                                    data-ptrnombre="#nombre_usuario_genero"
                                    data-ptrusuario_codigo="#usuario_codigo"
                                    data-omitir_filtro_empresa="1">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            <input type="text"
                                   id="nombre_usuario_genero"
                                   class="nombreusuario form-control"
                                   value="{{ $nombreUsuarioGenero }}"
                                   placeholder="Nombre del usuario"
                                   readonly>
                        </div>
                        <small class="form-text text-muted">Si el usuario no carg&oacute; el ticket, buscalo y asignalo ac&aacute;.</small>
                    @else
                        <input type="text"
                               id="nombre_usuario_genero"
                               class="form-control"
                               value="{{ $nombreUsuarioGenero }}"
                               readonly>
                    @endif
                </div>
            </div>
            <div class="form-group row">
                <label for="sala" class="col-lg-3 control-label text-right pr-2">Sala</label>
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
                <label for="sector" class="col-lg-3 control-label text-right pr-2">Sector</label>
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
                <label for="areadestino" class="col-lg-3 control-label text-right pr-2">&Aacute;rea de destino</label>
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
                <label for="categoria_ticket" class="col-lg-3 control-label text-right pr-2">Categor&iacute;a</label>
                <input type="text" class="col-lg-2" id="categoria_ticket_id" name="categoria_ticket_id" value="{{ $data->subcategoria_tickets?->categoria_ticket_id ?? '' }}" >
                <button type="button" title="Consulta categorías" style="padding:1;" class="btn-accion-tabla consultacategoria_ticket tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="col-lg-6 form-control" id="nombrecategoria_ticket" name="nombrecategoria_ticket" value="{{ $data->subcategoria_tickets?->categoria_tickets?->nombre ?? '' }}" >
            </div>
            <div class="form-group row" id="div-subcategoria_ticket">
                <label for="subcategoria_ticket" class="col-lg-3 control-label text-right pr-2">Subcategor&iacute;a</label>
                <input type="text" class="col-lg-2" id="subcategoria_ticket_id" name="subcategoria_ticket_id" value="{{ $data->subcategoria_ticket_id ?? '' }}" >
                <button type="button" title="Consulta subcategorías" style="padding:1;" class="btn-accion-tabla consultasubcategoria_ticket tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="col-lg-6 nombresubcategoria_ticket form-control" id="nombresubcategoria_ticket" name="nombresubcategoria_ticket" value="{{ $data->subcategoria_tickets?->nombre ?? '' }}" >
            </div>
            <div class="form-group row" id="div-subcategoria_ticket">
                <label for="bienuso" class="col-lg-3 control-label text-right pr-2">Bien de uso intervenido</label>
                <input type="number" class="col-lg-6 bienuso_id form-control" id="bienuso_id" name="bienuso_id" value="{{$data->bienuso_id??''}}" >
            </div>
            <div class="form-group row">
                <label for="estado_ticket" class="col-lg-3 control-label text-right pr-2">Estado del ticket</label>
                <select name="estado_ticket" id="estado_ticket" data-placeholder="Estado del ticket" class="col-lg-3 form-control required" data-fouc required>
                    <option value="">-- Seleccionar --</option>
                    @foreach($estado_enum as $value)
                        @if( $value['nombre'] == old('estado_ticket', $data->estado_ticket ?? ''))
                            <option value="{{ $value['nombre'] }}" selected="select">{{ $value['nombre'] }}</option>    
                        @else
                            <option value="{{ $value['nombre'] }}">{{ $value['nombre'] }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>            
        </div>        
    </div>
    @include('ticket.partials.estadisticas_resolucion')
    <div class="col-md-12">
        <div class="form-group">
            <label for="titulo" class="control-label">T&iacute;tulo</label>
            <input type="text" name="titulo" id="titulo" class="form-control" maxlength="255" placeholder="Resumen breve del motivo del ticket" value="{{ old('titulo', $data->titulo ?? '') }}" required>
        </div>
        <div class="form-group">
            <label for="comentario">Comentario</label>
            <textarea name="comentario" id="comentario" class="form-control" rows="6" placeholder="Comentario ..." required>{{ old('comentario', $data->comentario ?? '') }}</textarea>
        </div>
    </div>
    <div class="card card-outline card-info">
        <div class="card-header py-2">
            <strong><i class="fa fa-tasks"></i> Tareas</strong>
        </div>
        <div class="card-body p-2">
    <div class="table-responsive">
    <table style="font-size: 12px;" class="table table-sm table-bordered" id="tarea-ticket-table">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th style="width: 24%;">Tarea</th>
                <th style="width: 10%;">Fecha carga</th>
                <th style="width: 10%;">Fecha program.</th>
                <th style="width: 18%;">T&eacute;cnico</th>
                <th style="width: 5%;">Turno</th>
                <th>Fecha finalizaci&oacute;n</th>
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
                            <input type="text" style="font-size: 12px;" name="tiempoinsumidos[]" class="form-control tiempoinsumido" value="{{old('tiempoinsumido', $tarea->tiempoinsumido ?? '')}}" readonly>
                        </td>   
                        <td>
                            @php
                                $estadoActual = $tarea->estadoVisual();
                            @endphp
                            <select name="estadotareas[]"
                                    class="form-control estadotarea"
                                    style="font-size: 12px;"
                                    data-estado-previo="{{ $estadoActual }}">
                                <option value="">-- Seleccionar Estado --</option>
                                @foreach ($estado_novedad_enum as $estadoOpt)
                                    <option value="{{ $estadoOpt['nombre'] }}"
                                        @if ($estadoOpt['nombre'] === $estadoActual)
                                            selected
                                        @endif
                                    >{{ $estadoOpt['nombre'] }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            @php
                                $tareaFinalizada = ! empty($tarea->fechafinalizacion) && $tarea->fechafinalizacion >= '2000-01-01';
                            @endphp
                            @if (! $tareaFinalizada)
                                <button type="button" title="Finaliza tarea" class="btn-accion-tabla finalizatarea tooltipsC">
                                    <i class="text-danger">Finaliza</i>
                                </button>
                                <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_tarea_ticket tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            @endif
                            <input type="hidden" name="creousuario_ids[]" class="form-control creousuario_id" value="{{ $tarea->creousuario_id ?? ''}}" />
                        </td>
                    </tr>
                    @include('ticket.administracion_ticket.partials.comentarios_tarea_inline', ['tarea' => $tarea])
                @endif
            @endforeach
        @endif
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="text-right font-weight-bold">Tiempo insumido del ticket</td>
                <td class="font-weight-bold text-right" id="tiempo-insumido-total-tareas"></td>
                <td colspan="2" class="text-muted small">suma de minutos</td>
            </tr>
        </tfoot>
    </table>
    </div>
    @include('ticket.administracion_ticket.template')
    <div class="row">
        <div class="col-md-12">
            <button type="button" id="agrega_renglon_tarea_ticket" class="btn btn-outline-primary btn-sm">
                <i class="fa fa-plus"></i> Agrega rengl&oacute;n
            </button>
        </div>
    </div>
        </div>
    </div>
    <input type="hidden" id="id" name="id" value="{{ $data->id ?? '' }}" />
    <input type="hidden" id="usuario_id" name="usuario_id" value="{{ $data->usuario_id ?? '' }}" />
    <input type="hidden" id="permiso_usuario" name="permiso_usuario" value="{{ can('supervisor-ticket', false) }}" />
    <input type="hidden" id="url_cambia_estado_tarea" value="{{ url('ticket/cambiar_estado_tarea') }}" />
    @if (! empty($data->id))
        <input type="hidden" id="url_guarda_comentario_tarea_admin" value="{{ url('ticket/ticket/'.$data->id.'/tarea') }}" />
        @include('ticket.partials.comentario_enviando_overlay', [
            'titulo' => 'Enviando comentario y notificando al usuario…',
            'subtitulo' => 'Por favor espere. Se está guardando el comentario y enviando el correo al usuario que generó el ticket.',
        ])
    @endif
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />

@include('includes.ticket.modalconsultacategoria')
@include('includes.ticket.modalconsultatarea_ticket')
@include('includes.ticket.modalconsultatecnico_ticket')
@include('includes.ticket.modalconsultasubcategoria')
@include('includes.stock.modalconsultaarticulo')


