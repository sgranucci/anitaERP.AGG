<div class="card form1">
    <div id="form-errors"></div>
    <div class="row" id="datosfactura" data-puntoventa="{{$puntoventa_query}}" data-tipotransaccion="{{$tipotransaccion_query}}" data-incoterm="{{$incoterm_query}}" data-formapago="{{$formapago_query}}"
        data-puntoventa_facturacion="{{json_encode($puntoventa_facturacion)}}">
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="fecha" class="col-lg-3 col-form-label">Fecha de la OV</label>
                <div class="col-lg-3">
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{old('fecha', $data->fecha ?? date('Y-m-d'))}}">
                </div>
            </div>
            @include('includes.form-empresa-asignada', [
                'empresa_query' => $empresa_query,
                'empresa_id' => $data->empresa_id ?? null,
                'mostrar_id' => true,
                'col_input' => 'col-lg-7',
            ])
            <div class="form-group row">
                <div class="input-group mb-3">
                    <label for="moneda" class="col-lg-3 col-form-label requerido">Monto Total</label>
                    <select name="moneda_id" id="moneda_id" data-placeholder="Moneda" class="col-lg-2 form-control required" data-fouc>
                        @foreach($moneda_query as $key => $value)
                            @if( (int) $value->id == (int) old('moneda_id', $data->moneda_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
                    <span class="input-group-text">#</span>
                    <input type="text" name="monto" id="monto" class="col-lg-3 form-control" placeholder="Monto total" aria-label="Monto total" value="{{number_format($data->monto??0,2,'.','')??''}}" required>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="tratamiento" class="col-lg-3 col-form-label">Tratamiento</label>
                <select name="tratamiento" id="tratamiento" data-placeholder="Tratamiento" class="col-lg-3 form-control required" data-fouc required>
                    @foreach($tratamiento_enum as $value)
                        @if( $value['nombre'] == old('tratamiento', $data->tratamiento ?? ''))
                            <option value="{{ $value['nombre'] }}" selected="select">{{ $value['nombre'] }}</option>    
                        @else
                            <option value="{{ $value['nombre'] }}">{{ $value['nombre'] }}</option>    
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
                <label for="estado" class="col-lg-3 col-form-label">Estado</label>
                <input type="text" name="estado" id="estado" class="col-lg-4 form-control" value="{{old('estado', $data->estado ?? 'SOLICITADA')}}" readonly>
            </div>            
        </div>        
    </div>
    <div class="col-md-12">
        <div class="form-group row">
            <label for="comentario" class="col-lg-1 col-form-label">Comentarios</label>
            <div class="col-lg-10">
                <input type="text" name="comentario" id="comentario" class="form-control" value="{{old('comentario', $data->comentario ?? '')}}">
            </div>
        </div>
    </div>
    <div class="col-md-12">
        <!-- textarea -->
        <div class="form-group">
            <table class="table" id="ordenventa-concepto-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Concepto</th>
                        <th style="width: 50%;">Detalle</th>
                        <th style="width: 7%;">Cantidad</th>
                        <th style="width: 15%;">Monto total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbody-ordenventa-concepto-table" class="container-concepto">
                    @if ($data->ordenventa_conceptos ?? '') 
                        @foreach (old('cuota', $data->ordenventa_conceptos->count() ? $data->ordenventa_conceptos : ['']) as $concepto)
                            @if (isset($concepto->monto))     
                            <tr>
                                <td>
                                    <input type="hidden" name="conceptos[]" class="form-control iiconcepto" readonly value="{{ $loop->index+1 }}" />
                                    <select name="concepto_ordenventa_ids[]" id="concepto_ordenventa_id" data-placeholder="Concepto" class="form-control required" data-fouc required>
                                        @foreach($concepto_ordenventa_query as $key => $value)
                                            @if( (int) $value->id == (int) old('concepto_ordenventa_id', $concepto->concepto_ordenventa_id ?? ''))
                                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                                            @else
                                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                                            @endif
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <textarea id="detalle" name="detalleconceptos[]" class="form-control required" rows="3" required placeholder="Detalle ...">{{old('detalle', $concepto->detalle ?? '')}}</textarea>
                                </td>
                                <td>
                                    <input type="number" name="cantidadconceptos[]" class="form-control cantidadconcepto" value="{{$concepto->cantidad}}">
                                </td>
                                <td>
                                    <input type="number" name="montoconceptos[]" class="form-control montoconcepto" value="{{number_format($concepto->monto??0,2,'.','')??''}}">
                                </td>
                                <td>
                                    <button style="width: 7%;" type="button" class="btn-accion-tabla eliminar_ordenventa_concepto tooltipsC">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                            @endif
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
        @include('ordenventa.ordenventa.template')
    <div class="col-md-11">
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group row">
                <button id="agrega_renglon_ordenventa_concepto" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row  justify-content-end">
                <label for="totalconcepto" class="col-lg-2 col-form-label">Total conceptos</label>
                <input type="text" id="totalconcepto" class="col-lg-3 form-control totalconcepto" value="" readonly>
            </div>
        </div>
    </div>
    </div>
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group row" id="div-cliente">
                <label for="cliente" class="col-lg-3 col-form-label">Razón Social</label>
                <input type="text" class="col-lg-2" id="cliente_id" name="cliente_id" value="{{$data->cliente_id??''}}" >
                <button type="button" title="Consulta clientes" style="padding:1;" class="btn-accion-tabla consultacliente tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="col-lg-5 form-control" id="nombrecliente" name="nombrecliente" value="{{$data->clientes->nombre??$data->nombrecliente??''}}" >
                <div class="form-group boton-alta-cliente" style="display: none">
                    <button type="button" id="botonaltacliente" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i>Alta Cliente
                    </button>
                </div>
                <a href="{{route('editar_cliente', ['id' => $data->cliente_id ?? 0])}}" style="display: flex; align-items: center;" class="btn-accion-tabla tooltipsC editarcliente" title="Editar este registro">
                    <i class="fa fa-edit"></i>
                </a>   
            </div>
            <div class="form-group row">
                <div class="input-group mb-3">
                    <label for="fantasiacliente" class="col-lg-3 col-form-label requerido">Nombre de Fantasía</label>
                    <input type="text" name="fantasiacliente" id="fantasiacliente" class="form-control" value="{{old('fantasiacliente', $data->fantasiacliente ?? '')}}" required/>
                </div>
            </div>            
            <div class="form-group row">
                <div class="input-group mb-3">
                    <label for="domicilio" class="col-lg-3 col-form-label requerido">Domicilio</label>
                    <input type="text" name="domicilio" id="domicilio" class="form-control" value="{{old('domicilio', $data->domicilio ?? '')}}" required/>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="nroinscripcion" class="col-lg-3 col-form-label requerido">C.U.I.T.</label>
                <div class="col-lg-4">
                    <input type="text" name="nroinscripcion" id="nroinscripcion" class="form-control" value="{{old('nroinscripcion', $data->nroinscripcion ?? '')}}" required/>
                </div>
            </div>
            <div class="form-group row">
                <label for="telefono" class="col-lg-3 col-form-label">Teléfono</label>
                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                <div class="col-lg-4">
                    <input type="text" name="telefono" id="telefono" class="form-control" value="{{old('telefono', $data->telefono ?? '')}}"/>
                </div>
            </div>
        </div>
    </div>
    <div class='col-md-12'>
        <div class="row mt-0">
            <div class="col-md-3">
                <div class="form-group">
                    <label class="requerido">País</label>
                    <select name="pais_id" id="pais_id" data-placeholder="País" class="form-control required" data-fouc>
                        <option value="">-- Seleccionar --</option>
                        @foreach($pais_query as $key => $value)
                            @if( (int) $value->id == (int) old('pais_id', $data->pais_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3" id='prov'>
                <div class="form-group">
                    <label class="requerido">Provincia</label>
                    <select name="provincia_id" id="provincia_id" data-placeholder="Provincia" class="form-control required" data-fouc>
                        <option value="">-- Seleccionar --</option>
                        @foreach($provincia_query as $key => $value)
                            @if( (int) $value->id == (int) old('provincia_id', $data->provincia_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
                    <input type="hidden" id="desc_provincia" name="desc_provincia" value="{{old('desc_provincia', $data->desc_provincia ?? '')}}" >
                </div>
            </div>
            <div class="col-md-3" id='loc'>
                <div class="form-group">
                    <label>Localidad</label>
                    <select name="localidad_id" id='localidad_id' data-placeholder="Localidad" class="form-control" data-fouc>
                        @if($data->localidad_id ?? '')
                            @if($data->localidad_id == "")
                                <option selected></option>
                            @else
                                <option value="{{old('localidad_id', $data['localidad_id'])}}" selected>{{$data->localidades->nombre}}</option>
                            @endif
                        @endif
                    </select>
                    <input type="hidden" id="localidad_id_previa" name="localidad_id_previa" value="{{old('localidad_id', $data->localidad_id ?? '')}}" >
                    <input type="hidden" id="desc_localidad" name="desc_localidad" value="{{old('desc_localidad', $data->localidades->nombre ?? '')}}" >
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Código Postal</label>
                    <input type="text" name="codigopostal" id="codigopostal" value="{{old('codigopostal', $data['codigopostal'] ?? '')}}" class="col-lg-5 form-control" placeholder="Codigo Postal">
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="email" class="col-lg-3 col-form-label">Email</label>
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <div class="col-lg-8">
                    <input type="email" name="email" id="email" class="form-control" value="{{old('email', $data->email ?? '')}}" placeholder="Ingrese email">
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row">
                <label class="col-lg-3 requerido">Forma de Pago</label>
                <select name="formapago_id" id="formapago_id" data-placeholder="Forma de Pago" class="col-lg-4 form-control required" data-fouc>
                    <option value="">-- Seleccionar --</option>
                    @foreach($formapago_query as $key => $value)
                        @if( (int) $value->id == (int) old('formapago_id', $data->formapago_id ?? ''))
                            <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <input type="hidden" id="ordenventa_id" name="ordenventa_id" value="{{ $data->id ?? '' }}" />
    <input type="hidden" id="numeroordenventa" name="numeroordenventa" value="{{ $data->numeroordenventa ?? '' }}" />
    <input type="hidden" id="creousuario_id" name="creousuario_id" value="{{ $data->creousuario_id ?? '' }}" />
    <input type="hidden" name="venta_id" id="venta_id" class="col-lg-2 form-control" value="{{$data->ventas[0]->id??''}}">
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
@include('includes.ventas.modalconsultacliente')


