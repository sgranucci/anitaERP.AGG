@php
    $camposBloqueados = isset($data) && ! empty($data->id);
@endphp
<div class="card form1">
    <div id="form-errors"></div>
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="fecha" class="col-lg-3 col-form-label">Fecha</label>
                <div class="col-lg-3">
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{old('fecha', $data->fecha ?? date('Y-m-d'))}}" readonly>
                </div>
            </div>
            <div class="form-group row">
                <label for="sala" class="col-lg-3 col-form-label">Sala</label>
                @if ($camposBloqueados)
                    <input type="hidden" name="sala_id" value="{{ old('sala_id', $data->sala_id ?? '') }}">
                @endif
                <select
                    @if (! $camposBloqueados)
                        name="sala_id"
                    @endif
                    id="sala_id" data-placeholder="Sala" class="col-lg-7 form-control required" data-fouc
                    @if ($camposBloqueados)
                        disabled
                    @else
                        required
                    @endif
                >
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
                @if ($camposBloqueados)
                    <input type="hidden" name="sector_id" value="{{ old('sector_id', $data->sector_id ?? '') }}">
                @endif
                <select
                    @if (! $camposBloqueados)
                        name="sector_id"
                    @endif
                    id="sector_id" data-placeholder="Sector" class="col-lg-7 form-control required" data-fouc
                    @if ($camposBloqueados)
                        disabled
                    @else
                        required
                    @endif
                >
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
                @if ($camposBloqueados)
                    <input type="hidden" name="areadestino_id" value="{{ old('areadestino_id', $data->areadestino_id ?? '') }}">
                @endif
                <select
                    @if (! $camposBloqueados)
                        name="areadestino_id"
                    @endif
                    id="areadestino_id" data-placeholder="Area de destino del ticket" class="col-lg-7 form-control required" data-fouc
                    @if ($camposBloqueados)
                        disabled
                    @else
                        required
                    @endif
                >
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
                <input type="text" class="col-lg-2" id="categoria_ticket_id" name="categoria_ticket_id" value="{{ $data->subcategoria_tickets?->categoria_ticket_id ?? '' }}"
                    @if ($camposBloqueados)
                        readonly
                    @endif
                >
                @if (! $camposBloqueados)
                    <button type="button" title="Consulta categorías" style="padding:1;" class="btn-accion-tabla consultacategoria_ticket tooltipsC">
                            <i class="fa fa-search text-primary"></i>
                    </button>
                @endif
                <input type="text" class="col-lg-6 form-control" id="nombrecategoria_ticket" name="nombrecategoria_ticket" value="{{ $data->subcategoria_tickets?->categoria_tickets?->nombre ?? '' }}"
                    @if ($camposBloqueados)
                        readonly
                    @endif
                >
                @if ($camposBloqueados)
                    <input type="hidden" name="subcategoria_ticket_id" value="{{ $data->subcategoria_ticket_id ?? '' }}">
                @endif
            </div>
            <div class="form-group row">
                <label for="estado_ticket" class="col-lg-3 col-form-label">Estado del ticket</label>
                <input type="text" class="col-lg-3 estado_ticket form-control" id="estado_ticket" name="estado_ticket" value="{{$data->estado_ticket??''}}" readonly>
            </div>
        </div>        
    </div>
    @include('ticket.partials.estadisticas_resolucion')
    <div class="col-md-12">
        <div class="form-group">
            <label for="titulo">Título</label>
            <input type="text" name="titulo" id="titulo" class="form-control" maxlength="255" placeholder="Resumen breve del motivo del ticket" value="{{ old('titulo', $data->titulo ?? '') }}" required
                @if ($camposBloqueados)
                    readonly
                @endif
            >
        </div>
        <div class="form-group">
            <label for="comentario">Comentario</label>
            <textarea name="comentario" id="comentario" class="form-control" rows="6" placeholder="Comentario ..." required
                @if ($camposBloqueados)
                    readonly
                @endif
            >{{ old('comentario', $data->comentario ?? '') }}</textarea>
        </div>
    </div>
    @include('ticket.ticket.partials.tareas_solo_lectura')
    <input type="hidden" id="id" name="id" value="{{ $data->id ?? '' }}" />
    <input type="hidden" id="usuario_id" name="usuario_id" value="{{ $data->usuario_id ?? '' }}" />
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
