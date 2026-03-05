<div class="card form1">
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="talonariovoucher" class="col-lg-3 col-form-label">Talonario Voucher</label>
                <select name="talonariovoucher_id" id="talonariovoucher_id" data-placeholder="Talonario Voucher" class="col-lg-4 form-control required" data-fouc>
                    <option value="">-- Seleccionar --</option>
                    @foreach($talonariovoucher_query as $key => $value)
                        @if( (int) $value->id == (int) old('talonariovoucher_id', $data->talonariovoucher_id ?? session('talonariovoucher_id')))
                            <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="fecha" class="col-lg-3 col-form-label">Fecha</label>
                <div class="col-lg-3">
                    <input type="date" name="fecha" id="fecha" class="form-control required" value="{{old('fecha', $data->fecha ?? date('Y-m-d'))}}">
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-3">
            <div class="form-group row">
                <label for="pax" class="col-lg-3 col-form-label">Pax</label>
                <div class="col-lg-3">
                    <input type="text" name="pax" id="pax" class="form-control pax" value="{{old('pax', $data->pax ?? '0')}}" readonly>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="form-group row">
                <label for="paxfree" class="col-lg-3 col-form-label">Free</label>
                <div class="col-lg-3">
                    <input type="text" name="paxfree" id="paxfree" class="form-control" value="{{old('paxfree', $data->paxfree ?? '0')}}" readonly>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="form-group row">
                <label for="incluido" class="col-lg-3 col-form-label">Incluido</label>
                <div class="col-lg-3">
                    <input type="text" name="incluido" id="incluido" class="form-control" value="{{old('incluido', $data->incluido ?? '0')}}" readonly>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="form-group row">
                <label for="opcional" class="col-lg-3 col-form-label">Opcional</label>
                <div class="col-lg-3">
                    <input type="text" name="opcional" id="opcional" class="form-control" value="{{old('opcional', $data->opcional ?? '0')}}" readonly>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="servicioterrestre" class="col-lg-2 col-form-label">Servicio</label>
                <input type="hidden" class="col-form-label servicioterrestre_id" id="servicioterrestre_id" name="servicioterrestre_id" value="{{$data->servicioterrestre_id ?? ''}}" >
                <input type="text" class="col-lg-2 codigoservicioterrestre" id="codigoservicioterrestre" name="codigoservicioterrestre" value="{{$codigoservicioterrestre ?? ''}}" >
                <input type="text" class="col-lg-6 col-form-label servicioterrestre" id="servicioterrestre" name="servicioterrestre" value="{{$data->servicioterrestres->nombre ?? ''}}" readonly>
                <button type="button" title="Consulta servicios" style="padding:1;" class="btn-accion-tabla consultaservicioterrestre tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="hidden" class="codigoservicioterrestre" id="codigoservicioterrestre" name="codigoservicioterrestre" value="{{$codigoservicioterrestre ?? ''}}" >
                <input type="hidden" name="nombreservicioterrestre" id="nombreservicioterrestre" class="form-control" value="{{old('nombreservicioterrestre', $data->servicioterrestres->nombre ?? '')}}">
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="proveedor" class="col-lg-2 col-form-label">Proveedor</label>
                <input type="text" class="col-lg-2 proveedor_id" id="proveedor_id" name="proveedor_id" value="{{$data->proveedor_id ?? ''}}" >
                <input type="text" class="col-lg-6 proveedor" id="proveedor" name="proveedor" value="{{$data->proveedores->nombre ?? ''}}" readonly>
                <button type="button" title="Consulta proveedores" style="padding:1;" class="btn-accion-tabla consultaproveedor tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="hidden" name="nombreproveedor" id="nombreproveedor" class="form-control" value="{{old('nombreproveedor', $data->proveedores->nombre ?? '')}}">
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-4">
            <div class="form-group row">
                <label for="montovoucher" class="col-lg-6 col-form-label">Monto voucher</label>
                <div class="col-lg-6">
                    <input type="number" name="montovoucher" id="montovoucher" class="form-control" value="{{old('montovoucher', $data->montovoucher ?? '0')}}">
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group row">
                <label for="montoempresa" class="col-lg-6 col-form-label">Monto empresa</label>
                <div class="col-lg-6">
                    <input type="number" name="montoempresa" id="montoempresa" class="form-control" value="{{old('montoempresa', $data->montoempresa ?? '0')}}">
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group row">
                <label for="montoproveedor" class="col-lg-6 col-form-label">Monto proveedor</label>
                <div class="col-lg-6">
                    <input type="number" name="montoproveedor" id="montoproveedor" class="form-control" value="{{old('montoproveedor', $data->montoproveedor ?? '0')}}">
                </div>
            </div>
        </div>
    </div>
    <div class="form-group row">
        <label for="observacion" class="col-lg-3 col-form-label">Observaciones</label>
        <div class="col-lg-8">
            <input type="text" name="observacion" id="observacion" class="form-control" value="{{old('observacion', $data->observacion ?? '')}}">
        </div>
    </div>
    <input type="hidden" class="moneda_default_id" id="moneda_default_id" name="moneda_default_id" value="{{config('caja.ID_MONEDA_DEFAULT_VOUCHER') ?? ''}}" >
    <h3>Guias</h3>
    <div class="card-body">
        <table class="table" id="guia-table">
            <thead>
                <tr>
                    <th style="width: 30%;">Guia</th>
                    <th style="width: 20%;">Tipo de comisión</th>
                    <th style="width: 10%;">Porcentaje</th>
                    <th style="width: 20%;">Monto Comisión</th>
                    <th style="width: 20%;">Orden de servicio</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-guia-table">
            @if ($data->voucher_guias ?? '') 
                @foreach (old('guia', $data->voucher_guias->count() ? $data->voucher_guias : ['']) as $guia)
                    <tr class="item-guia">
                        <td>
                            <input type="hidden" name="guia[]" class="form-control iiguia" readonly value="{{ $loop->index+1 }}" />
                            <div class="form-group row" id="guia">
                                <input type="hidden" name="guia[]" class="form-control iiguia" readonly value="" />
                                <input type="text" style="WIDTH: 40px;HEIGHT: 38px" class="codigoguia" name="codigoguias[]" value="{{$guia->guias->codigo ?? ''}}" >
                                <input type="hidden" class="guia_id" name="guia_ids[]" value="{{$guia->guia_id ?? ''}}" >
                                <input type="hidden" class="guia_id_previa" name="guia_id_previa[]" value="{{$guia->guia_id ?? ''}}" >
                                <button type="button" title="Consulta guías" style="padding:1;" class="btn-accion-tabla consultaguia tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" style="WIDTH: 250px;HEIGHT: 38px" class="nombreguia form-control" name="nombreguias[]" value="{{$guia->guias->nombre ?? ''}}" >
                            </div>
                        </td>
                        <td>
                            <select name="tipocomisiones[]" class="col-lg-6 form-control tipocomision" required>
                                <option value="">-- Elija tipo de comisión --</option>
                                @foreach ($tipocomision_enum as $tipocomision)
                                    <option value="{{ $tipocomision['valor'] }}"
                                        @if (old('tipocomision', $guia->tipocomision ?? '') == $tipocomision['valor']) selected @endif
                                        >{{ $tipocomision['nombre'] }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number" name="porcentajecomisiones[]" class="form-control porcentajecomision" value="{{old('porcentajecomisiones', $guia->porcentajecomision ?? '')}}">
                        </td>
                        <td>
                            <input type="number" name="montocomisiones[]" class="form-control montocomision" value="{{old('montocomisiones', $guia->montocomision ?? '')}}">
                        </td>
                        <td>
                            <input type="number" name="ordenservicio_ids[]" class="form-control ordenservicio_id" value="{{old('ordenservicios', $guia->ordenservicio_id ?? '')}}">
                        </td>
                        <td>
                            <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_guia tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            @endif
            </tbody>
        </table>
        @include('caja.voucher.template')
        <div class="row">
            <div class="col-md-12">
                <button id="agrega_renglon_guia" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
            </div>
        </div>
    </div>
</div>
@include('includes.receptivo.modalconsultareserva')
@include('includes.receptivo.modalconsultaservicioterrestre')
@include('includes.compras.modalconsultaproveedor')
@include('includes.receptivo.modalconsultaguia')

