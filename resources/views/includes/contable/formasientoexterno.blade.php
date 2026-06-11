@php
    $asientoEdicion = ($data ?? null)?->asientos?->first();
    $lineasAsiento = $asientoEdicion?->asiento_movimientos ?? collect();
@endphp
<div class="card formasientoexterno" style="display: none">
    <input type="hidden" name="tipoasiento_id" id="tipoasiento_id" value="{{old('tipoasiento_id', $asientoEdicion->tipoasiento_id ?? '')}}">
    <input type="hidden" name="fechaasiento" id="fechasiento" value="{{old('fecha', $asientoEdicion->fecha ?? date('Y-m-d'))}}">
    <input type="hidden" name="observacionasiento" id="observacionasiento" value="{{old('observacion', $asientoEdicion->observacion ?? '')}}">
    <input type="hidden" name="numeroasiento" value="{{ $asientoEdicion->numeroasiento ?? '' }}" />
    <input type="hidden" name="idasiento" value="{{ $asientoEdicion->idasiento ?? '' }}" />
    <h3>Cuentas</h3>
    <div class="card-body">
        <table class="table" id="cuenta-asiento-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Código</th>
                    <th style="width: 18%;">Descripción</th>
                    <th style="width: 15%;">Centro de costo</th>
                    <th style="width: 7%;">Moneda</th>
                    <th style="width: 15%;">Debe</th>
                    <th style="width: 15%;">Haber</th>
                    <th style="width: 12%;">Cotización</th>
                    <th style="width: 30%;">Detalle</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-cuenta-asiento-table" class="container-asiento">
            @if ($lineasAsiento->isNotEmpty())
                @foreach ($lineasAsiento as $cuenta)
                    <tr class="item-cuenta-asiento">
                        <td>
                            <div class="form-group row" id="cuentacontable">
                                <input type="hidden" name="cuentacontable[]" class="form-control iicuentacontable" readonly value="{{ $loop->index+1 }}" />
                                <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="{{$cuenta->cuentacontable_id ?? ''}}" >
                                <input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="{{$cuenta->cuentacontable_id ?? ''}}" >
                                <button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuenta tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigoasiento form-control" name="codigoasientos[]" value="{{$cuenta->cuentacontables->codigo ?? ''}}" >
                                <input type="hidden" class="codigo_previo_cuentacontable" name="codigo_previo_cuentacontables[]" value="{{$cuenta->cuentacontables->codigo ?? ''}}" >
                                <input type="hidden" class="carga_cuentacontable_manual" name="carga_cuentacontable_manuales[]" value="{{old('carga_cuentacontable_manuales', 0 ?? '')}}" >
                            </div>
                        </td>							
                        <td>
                            <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombrecuentacontable form-control" name="nombrecuentacontables[]" value="{{$cuenta->cuentacontables->nombre ?? ''}}" readonly>
                        </td>
                        <td>
                            <select name="centrocostoasiento_ids[]" data-placeholder="Centro de costo" class="centrocostoasiento form-control" data-fouc>
                            </select>
                            <input type="hidden" class="centrocostoasiento_id_previo" name="centrocostoasiento_id_previo[]" value="{{old('centrocostoasiento_ids', $cuenta->centrocosto_id ?? '')}}" >
                        </td>
                        <td>
                            <select name="monedaasiento_ids[]" data-placeholder="Moneda" class="monedaasiento form-control required" required data-fouc>
                                <option value="">-- Seleccionar --</option>
                                @foreach($moneda_query as $key => $value)
                                    @if( (int) $value->id == (int) old('monedaasiento_ids[]', $cuenta->moneda_id ?? ''))
                                        <option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
                                    @else
                                        <option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
                                    @endif
                                @endforeach
                            </select>
                            <input type="hidden" class="monedaasiento_id_previo" name="monedaasiento_id_previo[]" value="{{old('monedaasiento_ids', $cuenta->moneda_id ?? '')}}" >
                        </td>
                        <td>
                            <input type="number" name="debeasientos[]" style="text-align: right;" class="form-control debeasiento" value="{{old('debeasientos[]', ($cuenta->monto > 0 ? number_format($cuenta->monto,2,'.','') : '') ?? '')}}">
                        </td>
                        <td>
                            <input type="number" name="haberasientos[]" style="text-align: right;" class="form-control haberasiento" value="{{old('haberasientos[]', ($cuenta->monto < 0 ? number_format(abs($cuenta->monto),2,'.','') : '') ?? '')}}">
                        </td>
                        <td>
                            <input type="number" name="cotizacionasientos[]" style="text-align: right;" class="form-control cotizacionasiento" value="{{old('cotizacionasientos[]', $cuenta->cotizacion ?? '0')}}">
                        </td>
                        <td>
                            <input type="text" name="observacionasientos[]" style="text-align: right;" class="form-control observacionasiento" value="{{old('observacionasientos[]', $cuenta->observacion ?? '')}}">
                        </td>
                        <td>
                            <button type="button" title="Elimina esta linea" style="text-align: right;" class="btn-accion-tabla eliminar_cuenta_asiento tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            @endif
            </tbody>
        </table>
        @include('includes.contable.templateasientoexterno')
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group row">
                    <button id="agrega_renglon_asiento" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
                </div>
            </div>
            <div class="form-group row">
                <label for="totaldebeasientoasiento" class="col-lg-3 col-form-label">Total debe</label>
                <input type="text" id="totaldebeasiento" name="totaldebeasiento" class="form-control col-lg-3" readonly value="" />
                <label for="totaldebeasiento" class="col-lg-3 col-form-label">Total haber</label>
                <input type="text" id="totalhaberasiento" name="totalhaberasiento" class="form-control col-lg-3" readonly value="" />
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />

