<div class="card form1">
    <div id="form-errors"></div>
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="empresa" class="col-lg-3 col-form-label">Empresa</label>
                <select name="empresa_id" id="empresa_id" data-placeholder="Empresa" class="col-lg-7 form-control required" data-fouc required>
                    @foreach($empresa_query as $key => $value)
                        @if( (int) $value->id == (int) old('empresa_id', $data->empresa_id ?? ''))
                            <option value="{{ $value->id }}" selected="select">{{ $value->id }} {{ $value->nombre }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>            
            <div class="form-group row">
                <label for="presupuesto" class="col-lg-3 col-form-label">Presupuesto</label>
                <select name="presupuesto_id" id="presupuesto_id" data-placeholder="Presupuesto" class="col-lg-7 form-control required" data-fouc required>
                    @foreach($presupuesto_query as $key => $value)
                        @if( (int) $value->id == (int) old('presupuesto_id', $data->presupuesto_id ?? ''))
                            <option value="{{ $value->id }}" selected="select">{{ $value->id }} {{ $value->nombre }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>            
            <div class="form-group row">
                <label for="centrocosto" class="col-lg-3 col-form-label">Centro de Costo</label>
                <select name="centrocosto_id" id="centrocosto_id" data-placeholder="Centro de Costo" class="col-lg-5 form-control required" data-fouc required>
                    @foreach($centrocosto_query as $key => $value)
                        @if( (int) $value->id == (int) old('centrocosto_id', $data->centrocosto_id ?? ''))
                            <option value="{{ $value->id }}" selected="select">{{ $value->codigo }}-{{ $value->nombre }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>                    
            <div class="form-group row">
                <label for="nombre" class="col-lg-3 col-form-label">Nombre</label>
                <input type="text" name="nombre" id="nombre" class="col-lg-8 form-control requerido" placeholder="Nombre" aria-label="Nombre" value="{{$data->nombre ?? ''}}" required>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="codigo" class="col-lg-3 col-form-label">Número de Proyecto</label>
                <input type="text" name="codigo" id="codigo" class="col-lg-3 form-control requerido" placeholder="Número de Proyecto" aria-label="Número de Proyecto" value="{{$data->codigo ?? ''}}" required>
            </div>                  
            <div class="form-group row">
                <label for="codigoproyecto" class="col-lg-3 col-form-label">Código de Proyecto</label>
                <input type="text" name="codigoproyecto" id="codigoproyecto" class="col-lg-3 form-control requerido" placeholder="Codigo de Proyecto" aria-label="Codigo de Proyecto" value="{{$data->codigoproyecto ?? ''}}" required>
            </div>             
            <div class="form-group row">
                <label for="estado" class="col-lg-3 col-form-label">Monto Total</label>
                <select name="monedatotal_id" id="monedatotal_id" data-placeholder="Moneda" class="col-lg-2 form-control required" data-fouc>
                    @foreach($moneda_query as $key => $value)
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                    @endforeach
                </select>
                <span class="input-group-text">#</span>                
                <input type="text" name="montototal" id="montototal" class="col-lg-3 form-control" placeholder="Monto total" aria-label="Monto total" value="" readonly>
            </div>                
            <div class="form-group row">
                <label for="estado" class="col-lg-3 col-form-label">Estado</label>
                <input type="text" name="estado" id="estado" class="col-lg-4 form-control" value="{{old('estado', $data->estado ?? 'SOLICITADA')}}" readonly>
            </div>            
        </div>        
    </div>
    <div class="col-md-12">
        <div class="form-group row">
            <label for="detalle" class="col-lg-1 col-form-label">Detalle</label>
            <div class="col-lg-10">
                <input type="text" name="detalle" id="detalle" class="form-control" value="{{old('detalle', $data->detalle ?? '')}}">
            </div>
        </div>
    </div>
    <div class="col-md-12">
        <!-- textarea -->
        <div class="form-group">
            <table class="table" id="capex-partida-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">Nro.Partida</th>
                        <th style="width: 35%;">Nombre</th>
                        <th style="width: 28%;">Proveedor</th>
                        <th style="width: 8%;">Moneda</th>
                        <th style="width: 15%;">Monto Total Partida</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbody-capex-partida-table" class="container-partida">
                    @if ($data->capex_partidas ?? '') 
                        @foreach (old('cuota', $data->capex_partidas->count() ? $data->capex_partidas : ['']) as $partida)
                            @if (isset($partida->moneda_id))     
                            <tr>
                                <td>
                                    <input type="hidden" name="items[]" class="form-control item" readonly value="{{ $loop->index+1 }}" />
                                    <input type="hidden" name="capex_partida_ids[]" class="form-control capex_partida_id" readonly value="{{ $partida->id }}" />
                                    <input type="hidden" name="creousuario_ids[]" class="creousuario_id" value="{{ $partida->creousuario_id }}" />
                                    <input type="hidden" name="estados[]" class="estadopartida" value="" />
                                    <input type="text" name="codigos[]" class="form-control codigopartida" value="{{$partida->codigo}}" readonly>                                    
                                </td>
                                <td>
                                    <input type="text" name="nombres[]" class="form-control nombre" value="{{$partida->nombre}}">                                    
                                </td>
                                <td>
                                    <div class="form-group row">
                                        <input type="text" class="col-lg-2 proveedor_id form-control" name="proveedor_ids[]" value="{{$partida->proveedor_id ?? ''}}" >
                                        <input type="text" class="col-lg-8 nombreproveedor form-control" name="nombreproveedores[]" value="{{$partida->proveedores->nombre ?? ''}}" readonly>
                                        <button type="button" title="Consulta proveedores" style="padding:1;" class="btn-accion-tabla consultaproveedor tooltipsC">
                                            <i class="fa fa-search text-primary"></i>
                                        </button>
                                        <input type="hidden" class="codigoproveedor" name="codigoproveedores[]" value="{{$partida->proveedores->codigo ?? ''}}" >
                                    </div>
                                </td>
                                <td>
                                    <select name="moneda_ids[]" data-placeholder="Moneda" class="form-control required moneda_id" data-fouc readonly required>
                                        @foreach($moneda_query as $key => $value)
                                            @if( (int) $value->id == (int) old('moneda_id', $partida->moneda_id ?? ''))
                                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                                            @else
                                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                                            @endif
                                        @endforeach
                                    </select>                                    
                                </td>
                                <td>
                                    @php $totalPartida = 0; @endphp
                                    @foreach ($partida->capex_partida_montos as $monto)
                                        @php $totalPartida += $monto->monto; @endphp
                                    @endforeach
                                    <input type="number" class="form-control montopartida" id="montopartida" name="montopartida" value="{{$totalPartida}}" readonly>
                                </td>                                
                                <td>
                                	<a href="#" class="btn-accion-tabla tooltipsC carga_partida_monto" title="Carga montos mensuales">
                                   		<i class="fa fa-calendar text-success"></i>
                                	</a>                                    
                                    <button style="width: 7%;" type="button" class="btn-accion-tabla eliminar_capex_partida tooltipsC">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                            @endif
                        @endforeach
                    @endif
                </tbody>
            </table>
            <table class="table">
                </thead>
                <tbody class="capex-partida-monto-armado-table"></tbody>
            </table>
        </div>
    </div>
    @include('presupuesto.capex.template')
    <div class="col-md-11">
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group row">
                <button id="agrega_renglon_capex_partida" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row  justify-content-end">
                <label for="totalpartida" class="col-lg-2 col-form-label">Total partidas</label>
                <input type="text" id="totalpartida" class="col-lg-3 form-control totalpartida" value="" readonly>
            </div>
        </div>
    </div>
    </div>
    <input type="hidden" id="capex_id" name="capex_id" value="{{ $data->id ?? '' }}" />
    <input type="hidden" id="creousuario_id" name="creousuario_id" value="{{ $data->creousuario_id ?? '' }}" />
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />


