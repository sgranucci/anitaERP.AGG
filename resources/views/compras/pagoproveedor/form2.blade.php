@php
    $movCaja = ($data->caja_movimientos ?? collect())->first();
    $cuentasCaja = $movCaja->caja_movimiento_cuentacajas ?? collect();
@endphp
<div class="card form2" style="display: none">
    <h3>Cuentas de caja</h3>
    <div class="card-body">
        <table class="table" id="cuenta-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Código</th>
                    <th style="width: 18%;">Descripción</th>
                    <th style="width: 7%;">Moneda</th>
                    <th style="width: 15%;">Monto</th>
                    <th style="width: 12%;">Cotización</th>
                    <th>Observación</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-cuenta-table">
            @foreach ($cuentasCaja as $cuenta)
                <tr class="item-cuenta">
                    <td>
                        <div class="form-group row" id="cuenta">
                            <input type="hidden" name="cuentacaja[]" class="form-control iicuenta" readonly value="{{ $loop->iteration }}" />
                            <input type="hidden" class="cuentacaja_id" name="cuentacaja_ids[]" value="{{ $cuenta->cuentacaja_id ?? '' }}">
                            <input type="hidden" class="cuentacaja_id_previa" name="cuentacaja_id_previa[]" value="{{ $cuenta->cuentacaja_id ?? '' }}">
                            <button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuentacaja tooltipsC">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            <input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigo form-control" name="codigos[]" value="{{ $cuenta->cuentacajas->codigo ?? '' }}">
                            <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="{{ $cuenta->cuentacajas->codigo ?? '' }}">
                        </div>
                    </td>
                    <td>
                        <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombre form-control" name="nombres[]" value="{{ $cuenta->cuentacajas->nombre ?? '' }}" readonly>
                    </td>
                    <td>
                        <select name="moneda_ids[]" class="moneda form-control required" required>
                            <option value="">-- Seleccionar --</option>
                            @foreach($moneda_query as $value)
                                <option value="{{ $value->id }}" @selected((int) $value->id === (int) ($cuenta->moneda_id ?? 0))>{{ $value->abreviatura }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" name="montos[]" class="form-control monto" value="{{ abs((float) ($cuenta->monto ?? 0)) }}">
                    </td>
                    <td>
                        <input type="number" name="cotizaciones[]" class="form-control cotizacion" value="{{ $cuenta->cotizacion ?? '0' }}">
                    </td>
                    <td>
                        <input type="text" name="observaciones[]" class="form-control observacion" value="{{ $cuenta->observacion ?? '' }}">
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
        @include('compras.pagoproveedor.template_cuenta')
        <div class="row">
            <div class="col-sm-6">
                <button type="button" id="agrega_renglon_cuenta" class="btn btn-danger">+ Agrega rengl&oacute;n</button>
            </div>
        </div>
        <div class="form-group row totales-por-moneda mt-2"></div>
    </div>
</div>
