<div class="card form1">
    <div class="row">
        <div class="col-sm-6">
            @include('includes.form-empresa-asignada', [
                'empresa_query' => $empresa_query,
                'empresa_id' => $data->empresa_id ?? session('empresa_id'),
                'mostrar_id' => true,
                'col_input' => 'col-lg-7',
            ])
            <div class="form-group row">
                <label for="tipoasiento" class="col-lg-3 col-form-label">Tipo de asiento</label>
                <select name="tipoasiento_id" id="tipoasiento_id" data-placeholder="Tipo de asiento" class="col-lg-7 form-control required" data-fouc>
                    <option value="">-- Seleccionar --</option>
                    @foreach($tipoasiento_query as $key => $value)
                        @if( (int) $value->id == (int) old('tipoasiento_id', $data->tipoasiento_id ?? session('tipoasiento_id')))
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
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{old('fecha', $data->fecha ?? date('Y-m-d'))}}">
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
    <input type="hidden" id="numeroasiento" name="numeroasiento" value="{{ $data->numeroasiento ?? '' }}" />
    <input type="hidden" id="id" name="id" value="{{ $data->id ?? '' }}" />
    <h2 id="loading"style="display:none">Guardando asiento ...</h2>
    <h3>Cuentas</h3>
    @php
        $totalDebeAsientoForm = 0.0;
        $totalHaberAsientoForm = 0.0;
        $tieneLineasAsientoForm = isset($data->asiento_movimientos) && $data->asiento_movimientos->count() > 0;
        if ($tieneLineasAsientoForm) {
            foreach ($data->asiento_movimientos as $movimientoTot) {
                $montoTot = (float) ($movimientoTot->monto ?? 0);
                if ($montoTot > 0) {
                    $totalDebeAsientoForm += $montoTot;
                } elseif ($montoTot < 0) {
                    $totalHaberAsientoForm += abs($montoTot);
                }
            }
            $totalDebeAsientoForm = round($totalDebeAsientoForm, 2);
            $totalHaberAsientoForm = round($totalHaberAsientoForm, 2);
        }
        $totalDebeAsientoFormTxt = $tieneLineasAsientoForm ? number_format($totalDebeAsientoForm, 2, ',', '.') : '';
        $totalHaberAsientoFormTxt = $tieneLineasAsientoForm ? number_format($totalHaberAsientoForm, 2, ',', '.') : '';
    @endphp
    <style>
        #cuenta-table tfoot.asiento-totales-pie td {
            background-color: #e9ecef;
            border-top: 2px solid #ced4da;
            vertical-align: middle;
        }
        #cuenta-table tfoot.asiento-totales-pie .asiento-total-celda {
            background-color: #e9ecef !important;
            color: #495057;
            border: 0;
            box-shadow: none;
            font-weight: 700;
        }
    </style>
    <div class="card-body">
        <table class="table" id="cuenta-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Código</th>
                    <th style="width: 18%;">Descripción</th>
                    <th style="width: 15%;">Centro de costo</th>
                    <th style="width: 7%;">Moneda</th>
                    <th style="width: 15%;" class="text-right">Debe</th>
                    <th style="width: 15%;" class="text-right">Haber</th>
                    <th style="width: 12%;" class="text-right">Cotizaci&oacute;n</th>
                    <th style="width: 30%;">Detalle</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-cuenta-table">
            @if ($data->asiento_movimientos ?? '') 
                @foreach (old('cuenta', $data->asiento_movimientos->count() ? $data->asiento_movimientos : ['']) as $cuenta)
                    <tr class="item-cuenta">
                        <td>
                            <div class="form-group row" id="cuenta">
                                <input type="hidden" name="cuenta[]" class="form-control iicuenta" readonly value="{{ $loop->index+1 }}" />
                                <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="{{$cuenta->cuentacontable_id ?? ''}}" >
                                <input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="{{$cuenta->cuentacontable_id ?? ''}}" >
                                <button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuenta tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigo form-control" name="codigos[]" value="{{$cuenta->cuentacontables->codigo ?? ''}}" >
                                <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="{{$cuenta->cuentacontables->codigo ?? ''}}" >
                            </div>
                        </td>							
                        <td>
                            <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombre form-control" name="nombres[]" value="{{$cuenta->cuentacontables->nombre ?? ''}}" readonly>
                        </td>
                        <td>
                            <select name="centrocosto_ids[]" data-placeholder="Centro de costo" class="centrocosto form-control" data-fouc>
                            </select>
                            <input type="hidden" class="centrocosto_id_previo" name="centrocosto_id_previo[]" value="{{old('centrocosto_ids', $cuenta->centrocosto_id ?? '')}}" >
                        </td>
                        <td>
                            <select name="moneda_ids[]" data-placeholder="Moneda" class="moneda form-control required" required data-fouc>
                                <option value="">-- Seleccionar --</option>
                                @foreach($moneda_query as $key => $value)
                                    @if( (int) $value->id == (int) old('moneda_ids[]', $cuenta->moneda_id ?? ''))
                                        <option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
                                    @else
                                        <option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
                                    @endif
                                @endforeach
                            </select>
                        </td>
                        <td>
                            @php
                                $debeValor = old('debes.'.$loop->index, ($cuenta->monto ?? 0) > 0 ? number_format($cuenta->monto, 2, ',', '.') : '');
                            @endphp
                            <input type="text" inputmode="decimal" name="debes[]" class="form-control text-right debe" value="{{ $debeValor }}">
                        </td>
                        <td>
                            @php
                                $haberValor = old('haberes.'.$loop->index, ($cuenta->monto ?? 0) < 0 ? number_format(abs($cuenta->monto), 2, ',', '.') : '');
                            @endphp
                            <input type="text" inputmode="decimal" name="haberes[]" class="form-control text-right haber" value="{{ $haberValor }}">
                        </td>
                        <td>
                            @php
                                $cotizValor = old('cotizaciones.'.$loop->index, isset($cuenta->cotizacion) ? number_format((float) $cuenta->cotizacion, 2, ',', '.') : '0,00');
                            @endphp
                            <input type="text" inputmode="decimal" name="cotizaciones[]" class="form-control text-right cotizacion" value="{{ $cotizValor }}">
                        </td>
                        <td>
                            <input type="text" name="observaciones[]" class="form-control observacion" value="{{old('observaciones[]', $cuenta->observacion ?? '')}}">
                        </td>
                        <td>
                            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuenta tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            @endif
            </tbody>
            <tfoot class="asiento-totales-pie">
                <tr class="asiento-totales-fila">
                    <td colspan="4" class="text-right font-weight-bold text-secondary">Totales</td>
                    <td>
                        <input type="text" id="totaldebe" name="totaldebe" class="form-control form-control-sm text-right asiento-total-celda" readonly value="{{ old('totaldebe', $totalDebeAsientoFormTxt) }}" />
                    </td>
                    <td>
                        <input type="text" id="totalhaber" name="totalhaber" class="form-control form-control-sm text-right asiento-total-celda" readonly value="{{ old('totalhaber', $totalHaberAsientoFormTxt) }}" />
                    </td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
        @include('contable.asiento.template')
        <div class="row mt-2">
            <div class="col-sm-12">
                <button id="agrega_renglon_cuenta" type="button" class="btn btn-danger">+ Agrega rengl&oacute;n</button>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
@include('includes.contable.modalconsultacuentacontable')
@include('contable.asiento.copiarasientomodal')
@include('contable.asiento.revertirasientomodal')

