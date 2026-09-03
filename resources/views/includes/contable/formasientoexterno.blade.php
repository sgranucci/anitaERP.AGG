@php
    // hasOne del comprobante: no usar Asiento::first() (eso toma el primer asiento de toda la tabla).
    // En altas (p. ej. OP) $data puede ser stdClass sin relación asientos: ?-> igual dispara el notice.
    $asientoEdicion = (isset($data) && is_object($data) && isset($data->asientos))
        ? $data->asientos
        : null;
    $lineasAsiento = $asientoEdicion?->asiento_movimientos ?? collect();
    $totalDebeAsientoExt = 0.0;
    $totalHaberAsientoExt = 0.0;
    if ($lineasAsiento->isNotEmpty()) {
        foreach ($lineasAsiento as $movimientoTotExt) {
            $montoTotExt = (float) ($movimientoTotExt->monto ?? 0);
            if ($montoTotExt > 0) {
                $totalDebeAsientoExt += $montoTotExt;
            } elseif ($montoTotExt < 0) {
                $totalHaberAsientoExt += abs($montoTotExt);
            }
        }
        $totalDebeAsientoExt = round($totalDebeAsientoExt, 2);
        $totalHaberAsientoExt = round($totalHaberAsientoExt, 2);
    }
    $totalDebeAsientoExtTxt = $lineasAsiento->isNotEmpty() ? number_format($totalDebeAsientoExt, 2, ',', '.') : '';
    $totalHaberAsientoExtTxt = $lineasAsiento->isNotEmpty() ? number_format($totalHaberAsientoExt, 2, ',', '.') : '';
    $valorOldEscalarAsiento = static function (string $campo, $default = '') {
        $valor = old($campo, $default);
        if (is_array($valor) || is_object($valor)) {
            return $default;
        }

        return $valor ?? $default;
    };
    $valorOldIndiceAsiento = static function (string $campo, int $indice, $default = '') {
        $valor = old($campo.'.'.$indice);
        if ($valor === null) {
            $valor = old($campo, $default);
        }
        if (is_array($valor)) {
            $valor = $valor[$indice] ?? $default;
        }
        if (is_array($valor) || is_object($valor)) {
            $valor = $default;
        }

        return $valor ?? $default;
    };
@endphp
<div class="card card-outline card-info formasientoexterno" style="display: none">
    <input type="hidden" name="tipoasiento_id" id="tipoasiento_id" value="{{ $valorOldEscalarAsiento('tipoasiento_id', $asientoEdicion?->tipoasiento_id ?? '') }}">
    <input type="hidden" name="fechaasiento" id="fechasiento" value="{{ $valorOldEscalarAsiento('fecha', $asientoEdicion?->fecha ?? date('Y-m-d')) }}">
    <input type="hidden" name="observacionasiento" id="observacionasiento" value="{{ $valorOldEscalarAsiento('observacion', $asientoEdicion?->observacion ?? '') }}">
    <input type="hidden" name="numeroasiento" value="{{ $asientoEdicion?->numeroasiento ?? '' }}" />
    <input type="hidden" name="idasiento" value="{{ $asientoEdicion?->idasiento ?? '' }}" />
        <div class="card-header py-2">
            <h3 class="card-title mb-0">Cuentas</h3>
        </div>
    <style>
        #cuenta-asiento-table tfoot.asiento-totales-pie td {
            background-color: #e9ecef;
            border-top: 2px solid #ced4da;
            vertical-align: middle;
        }
        #cuenta-asiento-table tfoot.asiento-totales-pie .asiento-total-celda {
            background-color: #e9ecef !important;
            color: #495057;
            border: 0;
            box-shadow: none;
            font-weight: 700;
        }
    </style>
    <div class="card-body">
        <table class="table table-sm table-bordered" id="cuenta-asiento-table">
            <thead style="background:#85C1E9;color:#17202A;">
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
                                <input type="hidden" class="carga_cuentacontable_manual" name="carga_cuentacontable_manuales[]" value="{{ $valorOldIndiceAsiento('carga_cuentacontable_manuales', (int) $loop->index, '0') }}" >
                            </div>
                        </td>							
                        <td>
                            <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombrecuentacontable form-control" name="nombrecuentacontables[]" value="{{$cuenta->cuentacontables->nombre ?? ''}}" readonly>
                        </td>
                        <td>
                            <select name="centrocostoasiento_ids[]" data-placeholder="Centro de costo" class="centrocostoasiento form-control" data-fouc>
                            </select>
                            <input type="hidden" class="centrocostoasiento_id_previo" name="centrocostoasiento_id_previo[]" value="{{ $valorOldIndiceAsiento('centrocostoasiento_ids', (int) $loop->index, $cuenta->centrocosto_id ?? '') }}" >
                        </td>
                        <td>
                            <select name="monedaasiento_ids[]" data-placeholder="Moneda" class="monedaasiento form-control required" required data-fouc>
                                <option value="">-- Seleccionar --</option>
                                @foreach($moneda_query as $key => $value)
                                    @if( (int) $value->id == (int) $valorOldIndiceAsiento('monedaasiento_ids', (int) $loop->index, $cuenta->moneda_id ?? ''))
                                        <option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
                                    @else
                                        <option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
                                    @endif
                                @endforeach
                            </select>
                            <input type="hidden" class="monedaasiento_id_previo" name="monedaasiento_id_previo[]" value="{{ $valorOldIndiceAsiento('monedaasiento_ids', (int) $loop->index, $cuenta->moneda_id ?? '') }}" >
                        </td>
                        <td>
                            @php
                                $montoAsientoLin = (float) ($cuenta->monto ?? 0);
                                $debeAsientoValor = $valorOldIndiceAsiento(
                                    'debeasientos',
                                    (int) $loop->index,
                                    $montoAsientoLin > 0 ? number_format($montoAsientoLin, 2, ',', '.') : ''
                                );
                            @endphp
                            <input type="text" inputmode="decimal" name="debeasientos[]" class="form-control text-right debeasiento" value="{{ $debeAsientoValor }}">
                        </td>
                        <td>
                            @php
                                $haberAsientoValor = $valorOldIndiceAsiento(
                                    'haberasientos',
                                    (int) $loop->index,
                                    $montoAsientoLin < 0 ? number_format(abs($montoAsientoLin), 2, ',', '.') : ''
                                );
                            @endphp
                            <input type="text" inputmode="decimal" name="haberasientos[]" class="form-control text-right haberasiento" value="{{ $haberAsientoValor }}">
                        </td>
                        <td>
                            @php
                                $cotizAsientoValor = $valorOldIndiceAsiento(
                                    'cotizacionasientos',
                                    (int) $loop->index,
                                    isset($cuenta->cotizacion) ? number_format((float) $cuenta->cotizacion, 2, ',', '.') : '0,00'
                                );
                            @endphp
                            <input type="text" inputmode="decimal" name="cotizacionasientos[]" class="form-control text-right cotizacionasiento" value="{{ $cotizAsientoValor }}">
                        </td>
                        <td>
                            <input type="text" name="observacionasientos[]" style="text-align: right;" class="form-control observacionasiento" value="{{ $valorOldIndiceAsiento('observacionasientos', (int) $loop->index, $cuenta->observacion ?? '') }}">
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
            <tfoot class="asiento-totales-pie">
                <tr class="asiento-totales-fila">
                    <td colspan="4" class="text-right font-weight-bold text-secondary">Totales</td>
                    <td>
                        <input type="text" id="totaldebeasiento" name="totaldebeasiento" class="form-control form-control-sm text-right asiento-total-celda" readonly value="{{ $totalDebeAsientoExtTxt }}" />
                    </td>
                    <td>
                        <input type="text" id="totalhaberasiento" name="totalhaberasiento" class="form-control form-control-sm text-right asiento-total-celda" readonly value="{{ $totalHaberAsientoExtTxt }}" />
                    </td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
        @include('includes.contable.templateasientoexterno')
        <div class="row mt-2">
            <div class="col-sm-12">
                <button id="agrega_renglon_asiento" type="button" class="btn btn-danger">+ Agrega rengl&oacute;n</button>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />

