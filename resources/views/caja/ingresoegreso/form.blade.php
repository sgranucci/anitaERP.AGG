<div class="card card-outline card-info form1 mb-0 border-0 shadow-none">
    <div id="form-errors"></div>
    <input type="hidden" name="solicitudpago_id" id="solicitudpago_id"
           value="{{ old('solicitudpago_id', $data->solicitudpago_id ?? request('solicitudpago_id')) }}">
    @php
        $montoPendienteSp = 0.0;
        if (! empty($solicitudpagoOrigen)) {
            $montoPendienteSp = \App\Support\Caja\IngresoEgresoSolicitudpagoSupport::montoPendiente($solicitudpagoOrigen);
        }
    @endphp
    <input type="hidden" id="solicitudpago_monto_pendiente" name="solicitudpago_monto_pendiente"
           value="{{ $montoPendienteSp > 0 ? number_format($montoPendienteSp, 2, '.', '') : '' }}">
    @if ($montoPendienteSp > 0)
        <input type="hidden" id="solicitudpago_moneda_id" value="{{ (int) ($solicitudpagoOrigen->moneda_id ?? 0) }}">
    @endif
    <div class="row">
        <div class="col-sm-6">
            @include('includes.form-empresa-asignada', [
                'empresa_query' => $empresa_query,
                'empresa_id' => $data->empresa_id ?? session('empresa_id'),
                'mostrar_id' => true,
                'col_label' => 'col-lg-3 control-label text-right pr-2',
                'col_input' => 'col-lg-7',
            ])
            <div class="form-group row">
                <label for="tipotransaccion_caja_id" class="col-lg-3 control-label text-right pr-2">Tipo de transacción</label>
                <div class="col-lg-7">
                    <select name="tipotransaccion_caja_id" id="tipotransaccion_caja_id" data-placeholder="Tipo de transacción" class="form-control required" data-fouc required>
                        <option value="">-- Seleccionar --</option>
                        @foreach($tipotransaccion_caja_query as $key => $value)
                            @if( (int) $value->id == (int) old('tipotransaccion_caja_id', $data->tipotransaccion_caja_id ?? session('tipotransaccion_caja_id')))
                                <option value="{{ $value->id }}" data-abreviatura="{{ $value->abreviatura }}" data-operacion="{{ $value->operacion }}" data-signo="{{ $value->signo }}" selected="select">{{ $value->nombre }}</option>
                            @else
                                <option value="{{ $value->id }}" data-abreviatura="{{ $value->abreviatura }}" data-operacion="{{ $value->operacion }}" data-signo="{{ $value->signo }}">{{ $value->nombre }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>
            <div id="aviso-transferencia-ie" class="alert alert-info py-2" style="display:none;">
                <strong>Transferencia:</strong> cargue al menos dos cuentas de caja —
                monto <strong>positivo</strong> en la cuenta que recibe y <strong>negativo</strong> en la que entrega.
                Debe y Haber de caja, y el asiento contable, deben quedar balanceados.
            </div>
            @php
                $proveedorModelIe = $data->proveedores ?? null;
                $proveedorIdIe = old('proveedor_id', $data->proveedor_id ?? '');
                if ($proveedorIdIe && (int) $proveedorIdIe !== (int) optional($proveedorModelIe)->id) {
                    $proveedorModelIe = \App\Models\Compras\Proveedor::query()->find($proveedorIdIe);
                }
            @endphp
            @include('includes.compras.campo_proveedor_consulta', [
                'proveedor_id' => $proveedorIdIe,
                'codigo_proveedor' => old('codigoproveedor', optional($proveedorModelIe)->codigo ?? ''),
                'nombre_proveedor' => old('nombreproveedor', optional($proveedorModelIe)->nombre ?? ''),
                'col_label' => 'col-lg-3 control-label text-right pr-2',
                'col_input' => 'col-lg-7',
                'requerido' => false,
                'estilo_contenedor' => 'display: none',
            ])
        </div>
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="fecha" class="col-lg-3 control-label text-right pr-2">Fecha</label>
                <div class="col-lg-5">
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', $data->fecha ?? date('Y-m-d')) }}">
                </div>
            </div>
            <div class="form-group row" id="div-ordenservicio" style="display: none">
                <label for="ordenservicio_id" class="col-lg-3 control-label text-right pr-2">Orden de servicio</label>
                <div class="col-lg-5">
                    <input type="text" class="form-control ordenservicio_id" id="ordenservicio_id" name="ordenservicio_id" value="{{ $data->ordenservicio_id ?? '' }}">
                </div>
            </div>
            <div class="form-group row" id="div-conceptogasto" style="display: none">
                <label for="conceptogasto_id" class="col-lg-3 control-label text-right pr-2">Concepto de gasto</label>
                <div class="col-lg-7">
                    <div class="input-group">
                        <input type="text" class="form-control conceptogasto_id" id="conceptogasto_id" name="conceptogasto_id" value="{{ $data->conceptogasto_id ?? '' }}">
                        <input type="text" class="form-control nombreconceptogasto" id="nombreconceptogasto" name="nombreconceptogasto" value="{{ optional($data->conceptogastos)->nombre ?? '' }}">
                        <div class="input-group-append">
                            <button type="button" title="Consulta conceptos" class="btn btn-outline-secondary consultaconceptogasto tooltipsC">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="form-group row">
        <label for="detalle" class="col-lg-3 control-label text-right pr-2">Detalle</label>
        <div class="col-lg-8">
            <input type="text" name="detalle" id="detalle" class="form-control" value="{{ old('detalle', $data->detalle ?? '') }}">
        </div>
    </div>
    <input type="hidden" id="numerotransaccion" name="numerotransaccion" value="{{ $data->numerotransaccion ?? '' }}" />
    <input type="hidden" id="id" name="id" value="{{ $data->id ?? '' }}" />
    <input type="hidden" id="rendicionreceptivo_id" name="rendicionreceptivo_id" value="{{ $data->rendicionreceptivo_id ?? '' }}" />
    <h2 id="loading" style="display:none">Guardando movimiento de caja ...</h2>

    <div class="card card-outline card-info mt-3 mb-0">
        <div class="card-header py-2">
            <h3 class="card-title mb-0"><i class="fa fa-wallet"></i> Cuentas de caja</h3>
        </div>
        <div class="card-body">
        <table class="table table-sm table-bordered" id="cuenta-table">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th style="width: 12%;">Código</th>
                    <th style="width: 18%;">Descripción</th>
                    <th style="width: 7%;">Moneda</th>
                    <th style="width: 15%;">Monto</th>
                    <th style="width: 12%;">Cotización</th>
                    <th>Observación</th>
                    <th class="width40"></th>
                </tr>
            </thead>
            <tbody id="tbody-cuenta-table">
            @php
                $abrevTipoIe = optional($data->tipotransaccioncajas ?? null)->abreviatura;
                if (! $abrevTipoIe && isset($tipotransaccion_caja_query)) {
                    $tipoSelId = (int) old('tipotransaccion_caja_id', $data->tipotransaccion_caja_id ?? 0);
                    $abrevTipoIe = optional($tipotransaccion_caja_query->firstWhere('id', $tipoSelId))->abreviatura;
                }
                $preservarSignoMonto = strtoupper((string) $abrevTipoIe) === \App\Support\Caja\IngresoEgresoTransferenciaSupport::ABREV_TRA;
                $lineasCuentas = collect();
                if (isset($data) && $data->caja_movimiento_cuentacajas && $data->caja_movimiento_cuentacajas->count() > 0) {
                    $lineasCuentas = $data->caja_movimiento_cuentacajas;
                }
            @endphp
            @foreach ($lineasCuentas as $cuenta)
                    <tr class="item-cuenta">
                        <td>
                            <div class="form-group row mb-0" id="cuenta">
                                <input type="hidden" name="cuentacaja[]" class="form-control iicuenta" readonly value="{{ $loop->index+1 }}" />
                                <input type="hidden" class="cuentacaja_id" name="cuentacaja_ids[]" value="{{$cuenta->cuentacaja_id ?? ''}}" >
                                <input type="hidden" class="cuentacaja_id_previa" name="cuentacaja_id_previa[]" value="{{$cuenta->cuentacaja_id ?? ''}}" >
                                <button type="button" title="Consulta cuentas (F1)" class="btn-accion-tabla consultacuentacaja tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigo form-control" name="codigos[]" value="{{$cuenta->cuentacajas->codigo ?? ''}}" title="C&oacute;digo: Enter valida, F1 consulta" autocomplete="off">
                                <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="{{$cuenta->cuentacajas->codigo ?? ''}}" >
                            </div>
                        </td>
                        <td>
                            <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombre form-control" name="nombres[]" value="{{$cuenta->cuentacajas->nombre ?? ''}}" readonly>
                        </td>
                        <td>
                            <select name="moneda_ids[]" data-placeholder="Moneda" class="moneda form-control required" required readonly data-fouc>
                                <option value="">-- Seleccionar --</option>
                                @foreach($moneda_query as $key => $value)
                                    @if( (int) $value->id == (int) old('moneda_ids.'.$loop->parent->index, $cuenta->moneda_id ?? ''))
                                        <option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>
                                    @else
                                        <option value="{{ $value->id }}">{{ $value->abreviatura }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </td>
                        <td>
                            @php
                                $montoLinea = '';
                                if (is_object($cuenta) && isset($cuenta->monto)) {
                                    $montoLinea = $preservarSignoMonto ? $cuenta->monto : abs($cuenta->monto);
                                }
                            @endphp
                            <input type="number" name="montos[]" class="form-control monto" step="0.01" value="{{ old('montos.'.$loop->index, $montoLinea) }}">
                        </td>
                        <td>
                            <input type="number" name="cotizaciones[]" class="form-control cotizacion" value="{{old('cotizaciones[]', $cuenta->cotizacion ?? '0')}}">
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
            </tbody>
        </table>
        @include('caja.ingresoegreso.template')
        <div class="row align-items-center">
            <div class="col-sm-4 mb-2">
                <button type="button" id="agrega_renglon_cuenta" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-plus"></i> Agrega renglón
                </button>
            </div>
            <div class="col-sm-8">
                <div class="form-group row mb-0 justify-content-end">
                    <label for="totaldebe" id="labeltotaldebe" class="col-auto col-form-label pr-2">Total debe</label>
                    <input type="text" id="totaldebe" name="totaldebe" class="form-control form-control-sm col-lg-2" readonly value="" />
                    <label for="totalhaber" id="labeltotalhaber" class="col-auto col-form-label pr-2">Total haber</label>
                    <input type="text" id="totalhaber" name="totalhaber" class="form-control form-control-sm col-lg-2" readonly value="" />
                </div>
            </div>
        </div>
        <div class="form-group row totales-por-moneda">
        </div>
        </div>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
@include('includes.contable.modalconsultacuentacontable')
@include('includes.caja.modalconsultacuentacaja')
@include('includes.compras.modalconsultaproveedor')
@include('caja.ingresoegreso.copiaringresoegresomodal')
@include('caja.ingresoegreso.revertiringresoegresomodal')
@include('includes.caja.modalconsultagasto')
