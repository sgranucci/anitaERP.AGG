<div class="row">
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="nombre" class="col-lg-4 col-form-label requerido">Nombre</label>
            <div class="col-lg-6">
                <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
            </div>
        </div>
        <div class="form-group row">
            <label for="tipoarbol" class="col-lg-4 col-form-label requerido">Tipo de Arbol</label>
            <select id="tipoarbol" name="tipoarbol" class="col-lg-4 form-control" required>
                <option value="">-- Elija tipo de árbol --</option>
                @foreach($tipoarbol_enum as $tipoarbol)
                    @if ($tipoarbol['nombre'] == old('tipoarbol',$data->tipoarbol??''))
                        <option value="{{ $tipoarbol['nombre'] }}" selected>{{ $tipoarbol['nombre'] }}</option>    
                    @else
                        <option value="{{ $tipoarbol['nombre'] }}">{{ $tipoarbol['nombre'] }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="Empresa" class="col-lg-4 col-form-label">Empresa</label>
            <select name="empresa_id" id="empresa_id" data-placeholder="Empresa" class="col-lg-5 form-control" required data-fouc>
                @foreach($empresa_query as $key => $value)
                    @if( (int) $value->id == (int) old('empresa_id', $data->empresa_id ?? session('empresa_id')))
                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="recordatorio" class="col-lg-4 col-form-label requerido">Recordatorio</label>
            <select id="recordatorio" name="recordatorio" data-placeholder="Si envia mail recordatorio" class="col-lg-4 form-control" required>
                <option value="">-- Elija recordatorio --</option>
                @foreach($recordatorio_enum as $recordatorio)
                    @if ($recordatorio['valor'] == old('recordatorio',$data->recordatorio??''))
                        <option value="{{ $recordatorio['valor'] }}" selected>{{ $recordatorio['nombre'] }}</option>    
                    @else
                        <option value="{{ $recordatorio['valor'] }}">{{ $recordatorio['nombre'] }}</option>
                    @endif
                @endforeach
            </select>
        </div>    
        <div class="form-group row div-diasinrespuesta" style="display: none">
            <label for="diasinrespuesta" class="col-lg-4 col-form-label requerido">Días sin respuesta</label>
            <div class="col-lg-2">
                <input type="number" name="diasinrespuesta" id="diasinrespuesta" class="form-control" value="{{old('diasinrespuesta', $data->diasinrespuesta ?? '0')}}"/>
            </div>
        </div>    
        <div class="form-group row div-diavencimientorecordatorio" style="display: none">
            <label for="diavencimientorecordatorio" class="col-lg-4 col-form-label requerido">Días vto. recordatorio</label>
            <div class="col-lg-2">
                <input type="number" name="diavencimientorecordatorio" id="diavencimientorecordatorio" class="form-control" value="{{old('diavencimientorecordatorio', $data->diavencimientorecordatorio ?? '0')}}"/>
            </div>
        </div>     
        <div class="form-group row">
            <label for="estado" class="col-lg-4 col-form-label requerido">Estado</label>
            <select id="estado" name="estado" data-placeholder="Estado del árbol" class="col-lg-4 form-control" required>
                <option value="">-- Elija estado --</option>
                @foreach($estado_enum as $estado)
                    @if ($estado['nombre'] == old('estado',$data->estado??''))
                        <option value="{{ $estado['nombre'] }}" selected>{{ $estado['nombre'] }}</option>    
                    @else
                        <option value="{{ $estado['nombre'] }}">{{ $estado['nombre'] }}</option>
                    @endif
                @endforeach
            </select>
        </div>                   
    </div>
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="filtro_centrocosto" class="col-lg-3 col-form-label">Filtra Centro de Costo</label>
            <select id="filtro_centrocosto_id" data-placeholder="Filtra Centro de Costo" class="col-lg-4 form-control" data-fouc>
                <option value="">-- Elija centro de costo --</option>
                @foreach($centrocosto_query as $key => $value)
                    <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                @endforeach
            </select>
        </div>
    </div>
</div>
<h4>Niveles</h4>
<div class="card-body">
    <table class="table" id="arbolaprobacion-nivel-table">
        <thead>
            <tr>
                <th style="width: 6%;"></th>
                <th style="width: 10%;">Nivel</th>
                <th style="width: 25%;">Centro Costo</th>
                <th style="width: 30%;">Usuario</th>
                <th style="width: 15%;">Desde Monto</th>
                <th style="width: 15%;">Hasta Monto</th>
                <th style="width: 10%;">Moneda</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tbody-arbolaprobacion-nivel-table">
        @if ($data->arbolaprobacion_niveles ?? '') 
            @foreach (old('arbolaprobacion_nivel', $data->arbolaprobacion_niveles->count() ? $data->arbolaprobacion_niveles : ['']) as $arbolaprobacion_niveles)
                <tr class="item-arbolaprobacion-nivel">
                    <td>
                        <input type="hidden" class="id form-control" name="ids[]" value="{{$arbolaprobacion_niveles->id ?? ''}}">
                        <input type="text" name="arbolaprobacion_nivel[]" class="form-control iiarbolaprobacion_nivel" readonly value="{{ $loop->index+1 }}" />
                    </td>
                    <td>
                        <input type="number" class="nivel form-control" name="niveles[]" min="1" value="{{$arbolaprobacion_niveles->nivel ?? ''}}" required>
                    </td>
                    <td>
                        <select name="centrocosto_ids[]" data-placeholder="Centro de Costo" class="centrocosto form-control required" required data-fouc>
                            <option value="">-- Elija centro de costo --</option>
                            @foreach($centrocosto_query as $key => $value)
                                @if( (int) $value->id == (int) old('centrocosto_ids[]', $arbolaprobacion_niveles->centrocosto_id ?? ''))
                                    <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                                @else
                                    <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                                @endif
                            @endforeach
                        </select>
                    </td>                    
                    <td>
                        <div class="form-group row" id="usuario">
                            <input type="text" style="WIDTH: 40px;HEIGHT: 38px" class="usuario_id" name="usuario_ids[]" value="{{$arbolaprobacion_niveles->usuario_id ?? ''}}" >
                            <input type="hidden" class="usuario_id_previa" name="usuario_id_previa[]" value="{{$arbolaprobacion_niveles->usuario_id ?? ''}}" >
                            <button type="button" title="Consulta usuarios" style="padding:1;" class="btn-accion-tabla consultausuario tooltipsC">
                                    <i class="fa fa-search text-primary"></i>
                            </button>
                            <input type="text" style="font-size: 16px; WIDTH: 300px;HEIGHT: 38px" class="nombreusuario form-control" name="nombreusuarios[]" value="{{$arbolaprobacion_niveles->usuarios->nombre ?? ''}}" >
                        </div>
                    </td>
                    <td>
                        <input type="number" class="desdemonto form-control" name="desdemontos[]" value="{{$arbolaprobacion_niveles->desdemonto ?? ''}}">
                    </td>
                    <td>
                        <input type="number" class="hastamonto form-control" name="hastamontos[]" value="{{$arbolaprobacion_niveles->hastamonto ?? ''}}">
                    </td>                    
                    <td>
                        <select name="moneda_ids[]" data-placeholder="Moneda" class="moneda form-control required" required data-fouc>
                            @foreach($moneda_query as $key => $value)
                                @if( (int) $value->id == (int) old('moneda_ids[]', $arbolaprobacion_niveles->moneda_id ?? ''))
                                    <option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
                                @else
                                    <option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
                                @endif
                            @endforeach
                        </select>
                    </td>                    
                    <td>
                        <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_arbolaprobacion_nivel tooltipsC">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    @include('configuracion.arbolaprobacion.template')
    <div class="row">
        <div class="col-md-12">
            <button id="agrega_renglon_arbolaprobacion_nivel" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        </div>
    </div>
</div>
@include('includes.admin.modalconsultausuario')

