@php
    $fechaCampo = static function (string $campo, $valor): string {
        $v = old($campo, $valor ?? '');
        $v = is_scalar($v) ? trim((string) $v) : '';

        return strlen($v) >= 10 ? substr($v, 0, 10) : $v;
    };
    $estadoItem = collect($estado_enum ?? [])->firstWhere('valor', (string) ($data->estado ?? ''));
    $estadoNombre = is_array($estadoItem) ? (string) ($estadoItem['nombre'] ?? '') : (string) ($data->estado ?? '');
    $rechazado = \App\Support\Caja\ChequeAvisoRechazoSupport::estaRechazado($data);
    $cliente = $data->clientes;
    $cpCliente = trim((string) ($cliente?->codigopostal ?? ''));
    $localidadCliente = trim((string) ($cliente?->localidades?->nombre ?? ''));
    if ($localidadCliente !== '' && $cpCliente !== '') {
        $localidadCliente .= ' ('.$cpCliente.')';
    }
    $fechaRechazoTxt = $fechaCampo('fecha_rechazo_vista', $data->fecha_rechazo ?? '');
    if ($fechaRechazoTxt !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fechaRechazoTxt, $mFecha)) {
        $fechaRechazoTxt = $mFecha[3].'/'.$mFecha[2].'/'.$mFecha[1];
    }
@endphp

<style>
    .cheque-tercero-form .form-group { margin-bottom: .55rem; }
    .cheque-tercero-form .form-control[readonly] { background: #f4f6f7; }
</style>

<div class="cheque-tercero-form">
    @if ($rechazado)
        <div class="alert alert-danger py-2 mb-3">
            <strong>Rechazado</strong>
            @if ($fechaRechazoTxt !== '')
                el {{ $fechaRechazoTxt }}
            @endif
            @if (trim((string) ($data->motivo_rechazo ?? '')) !== '')
                — {{ $data->motivo_rechazo }}
            @endif
            @if (! empty($data->ventaNd->codigo))
                <span class="ml-2">Nota de d&eacute;bito {{ $data->ventaNd->codigo }}</span>
            @endif
        </div>
    @endif

    <div class="row">
        <div class="col-lg-6">
            <div class="card card-outline card-info">
                <div class="card-header py-2">
                    <h3 class="card-title mb-0">Cheque</h3>
                </div>
                <div class="card-body">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query ?? collect(),
                        'empresa_id' => $data->empresa_id ?? null,
                        'required' => true,
                        'solo_lectura' => true,
                        'col_label' => 'col-5 text-right pr-2',
                        'col_input' => 'col-7',
                    ])

                    <div class="form-group row">
                        <label class="col-5 col-form-label text-right pr-2">Interno</label>
                        <div class="col-7">
                            <input type="text" class="form-control" readonly value="{{ $data->nro_interno_anita ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="numerocheque" class="col-5 col-form-label text-right pr-2">N&uacute;mero</label>
                        <div class="col-7">
                            <input type="text" name="numerocheque" id="numerocheque" class="form-control" maxlength="50"
                                   value="{{ old('numerocheque', $data->numerocheque ?? '') }}">
                        </div>
                    </div>

                    @include('caja.partials.campo_consulta_banco', [
                        'prefix' => 'cheque',
                        'layout' => 'form_row',
                        'inputName' => 'banco_id',
                        'inputId' => 'banco_id',
                        'bancoId' => old('banco_id', $data->banco_id ?? ''),
                        'codigo' => old('banco_codigo', $data->bancos->codigo ?? ''),
                        'descripcion' => old('banco_nombre', $data->bancos->nombre ?? ''),
                        'label' => 'Banco',
                        'required' => false,
                        'col_label' => 'col-5',
                        'col_input' => 'col-7',
                    ])

                    <div class="form-group row">
                        <label for="cuentalibradora" class="col-5 col-form-label text-right pr-2">Cuenta libradora</label>
                        <div class="col-7">
                            <input type="text" name="cuentalibradora" id="cuentalibradora" class="form-control" maxlength="50"
                                   value="{{ old('cuentalibradora', $data->cuentalibradora ?? '') }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="sucursalpago" class="col-5 col-form-label text-right pr-2">Sucursal</label>
                        <div class="col-4">
                            <input type="text" name="sucursalpago" id="sucursalpago" class="form-control" maxlength="20"
                                   value="{{ old('sucursalpago', $data->sucursalpago ?? '') }}">
                        </div>
                        <label for="codigopostalbanco" class="col-1 col-form-label text-right px-1">CP</label>
                        <div class="col-2">
                            <input type="text" name="codigopostalbanco" id="codigopostalbanco" class="form-control" maxlength="10"
                                   value="{{ old('codigopostalbanco', $data->codigopostalbanco ?? '') }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fechaemision" class="col-5 col-form-label text-right pr-2">Fecha emisi&oacute;n</label>
                        <div class="col-7">
                            <input type="date" name="fechaemision" id="fechaemision" class="form-control"
                                   value="{{ $fechaCampo('fechaemision', $data->fechaemision ?? '') }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fechapago" class="col-5 col-form-label text-right pr-2">Fecha pago</label>
                        <div class="col-7">
                            <input type="date" name="fechapago" id="fechapago" class="form-control"
                                   value="{{ $fechaCampo('fechapago', $data->fechapago ?? '') }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="monto" class="col-5 col-form-label text-right pr-2">Monto</label>
                        <div class="col-7">
                            <input type="number" step="0.01" name="monto" id="monto" class="form-control text-right"
                                   value="{{ old('monto', $data->monto ?? '0') }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-5 col-form-label text-right pr-2">Moneda / cotiz.</label>
                        <div class="col-7">
                            <div class="d-flex" style="gap: 6px;">
                                <input type="hidden" name="moneda_id" value="{{ old('moneda_id', $data->moneda_id ?? '') }}">
                                <input type="text" class="form-control" readonly
                                       value="{{ trim(($data->monedas->abreviatura ?? '').' '.($data->monedas->nombre ?? '')) }}">
                                <input type="number" step="0.0001" name="cotizacion" id="cotizacion" class="form-control text-right"
                                       title="Cotizaci&oacute;n" style="max-width: 8rem;"
                                       value="{{ old('cotizacion', $data->cotizacion ?? '1') }}">
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="negociable" class="col-5 col-form-label text-right pr-2">F&iacute;sico / e-cheq</label>
                        <div class="col-7">
                            <select id="negociable" name="negociable" class="form-control">
                                @foreach (($negociable_enum ?? \App\Models\Caja\Cheque::$enumNegociable) as $negociable)
                                    <option value="{{ $negociable['valor'] }}"
                                        @selected($negociable['valor'] == old('negociable', $data->negociable ?? 'N'))>
                                        {{ $negociable['nombre'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="nro_echeq" class="col-5 col-form-label text-right pr-2">Nro. e-cheq</label>
                        <div class="col-7">
                            <input type="text" name="nro_echeq" id="nro_echeq" class="form-control" maxlength="50"
                                   value="{{ old('nro_echeq', $data->nro_echeq ?? '') }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fecha_entrega" class="col-5 col-form-label text-right pr-2">Fecha entrega</label>
                        <div class="col-7">
                            <input type="date" name="fecha_entrega" id="fecha_entrega" class="form-control"
                                   value="{{ $fechaCampo('fecha_entrega', $data->fecha_entrega ?? '') }}">
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <label for="caracter" class="col-5 col-form-label text-right pr-2">Car&aacute;cter</label>
                        <div class="col-7">
                            <select id="caracter" name="caracter" class="form-control" required>
                                <option value="">-- Elija car&aacute;cter --</option>
                                @foreach ($caracter_enum as $caracter)
                                    <option value="{{ $caracter['valor'] }}"
                                        @selected($caracter['valor'] == old('caracter', $data->caracter ?? ''))>
                                        {{ $caracter['nombre'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card card-outline card-info">
                <div class="card-header py-2">
                    <h3 class="card-title mb-0">Qui&eacute;n lo entreg&oacute;</h3>
                </div>
                <div class="card-body">
                    <div class="form-group row">
                        <label class="col-5 col-form-label text-right pr-2">Cliente</label>
                        <div class="col-7">
                            <input type="text" class="form-control" readonly
                                   value="{{ trim(($cliente?->codigo ?? '').' '.($cliente?->nombre ?? '')) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-5 col-form-label text-right pr-2">Domicilio</label>
                        <div class="col-7">
                            <input type="text" class="form-control" readonly value="{{ $cliente?->domicilio ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-5 col-form-label text-right pr-2">Localidad</label>
                        <div class="col-7">
                            <input type="text" class="form-control" readonly value="{{ $localidadCliente }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-5 col-form-label text-right pr-2">CUIT</label>
                        <div class="col-7">
                            <input type="text" class="form-control" readonly value="{{ $cliente?->numerodocumento ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-5 col-form-label text-right pr-2">Condici&oacute;n IVA</label>
                        <div class="col-7">
                            <input type="text" class="form-control" readonly value="{{ $cliente?->condicionivas?->nombre ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="entregado" class="col-5 col-form-label text-right pr-2">Entregado por</label>
                        <div class="col-7">
                            <input type="text" name="entregado" id="entregado" class="form-control" maxlength="255"
                                   value="{{ old('entregado', $data->entregado ?? '') }}">
                        </div>
                    </div>
                    <div class="form-group row mb-0">
                        <label for="anombrede" class="col-5 col-form-label text-right pr-2">A nombre de</label>
                        <div class="col-7">
                            <input type="text" name="anombrede" id="anombrede" class="form-control" maxlength="255"
                                   value="{{ old('anombrede', $data->anombrede ?? '') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-info">
                <div class="card-header py-2">
                    <h3 class="card-title mb-0">Situaci&oacute;n</h3>
                </div>
                <div class="card-body">
                    <div class="form-group row">
                        <label class="col-5 col-form-label text-right pr-2">Estado</label>
                        <div class="col-7">
                            <input type="text" class="form-control" readonly value="{{ $estadoNombre }}">
                        </div>
                    </div>
                    @if (! empty($data->fecha_deposito))
                        <div class="form-group row">
                            <label class="col-5 col-form-label text-right pr-2">Dep&oacute;sito</label>
                            <div class="col-7">
                                <input type="text" class="form-control" readonly
                                       value="{{ $fechaCampo('fecha_deposito_vista', $data->fecha_deposito) }}{{ $data->nro_boleta_deposito ? ' · boleta '.$data->nro_boleta_deposito : '' }}{{ $data->cuentacajaDeposito ? ' · '.($data->cuentacajaDeposito->nombre ?? '') : '' }}">
                            </div>
                        </div>
                    @endif
                    @if (trim((string) ($data->nro_caucion ?? '')) !== '' && trim((string) ($data->nro_caucion ?? '')) !== '0')
                        <div class="form-group row">
                            <label class="col-5 col-form-label text-right pr-2">Cauci&oacute;n</label>
                            <div class="col-7">
                                <input type="text" class="form-control" readonly value="{{ $data->nro_caucion }}">
                            </div>
                        </div>
                    @endif
                    @if (! empty($data->cobranzas))
                        <div class="form-group row">
                            <label class="col-5 col-form-label text-right pr-2">Recibo</label>
                            <div class="col-7">
                                <input type="text" class="form-control" readonly value="{{ $data->cobranzas->numerotransaccion ?? '' }}">
                            </div>
                        </div>
                    @endif
                    @if (! empty($data->venta_nd_id))
                        <div class="form-group row{{ empty($data->comprobante_proveedor_id) ? ' mb-0' : '' }}">
                            <label class="col-5 col-form-label text-right pr-2">Nota de d&eacute;bito</label>
                            <div class="col-7 d-flex align-items-center">
                                <a href="{{ route('lista_una_factura_pdf', array_filter(['id' => $data->venta_nd_id, 'retorno' => $retornoImpresionNd ?? ''])) }}"
                                   class="text-primary pe-auto" target="_blank" rel="noopener">
                                    {{ $data->ventaNd->codigo ?? ('#'.$data->venta_nd_id) }}
                                </a>
                            </div>
                        </div>
                    @endif
                    @if (! empty($data->comprobante_proveedor_id))
                        <div class="form-group row mb-0">
                            <label class="col-5 col-form-label text-right pr-2">Deuda proveedor</label>
                            <div class="col-7 d-flex align-items-center">
                                <a href="{{ route('editar_comprobante_proveedor', ['id' => $data->comprobante_proveedor_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                   class="text-primary pe-auto" target="_blank" rel="noopener">
                                    {{ ($data->comprobanteProveedorRechazo->tipotransaccion_compras->abreviatura ?? 'NDR') }}
                                    {{ $data->comprobanteProveedorRechazo->letra }}-{{ $data->comprobanteProveedorRechazo->sucursal }}-{{ $data->comprobanteProveedorRechazo->numerocomprobante }}
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-secondary">
        <div class="card-header py-2">
            <h3 class="card-title mb-0">Movimiento de caja</h3>
        </div>
        <div class="card-body">
            <div class="form-group row">
                <label class="col-lg-2 col-form-label text-right pr-2">Tipo</label>
                <div class="col-lg-4">
                    <input type="text" class="form-control" readonly
                           value="{{ $data->caja_movimientos?->tipotransaccion_cajas?->nombre ?? '' }}">
                </div>
                <label class="col-lg-2 col-form-label text-right pr-2">N&uacute;mero</label>
                <div class="col-lg-4">
                    <input type="text" class="form-control" readonly value="{{ $data->caja_movimiento_id ?? '' }}">
                </div>
            </div>
            <div class="form-group row mb-0">
                <label class="col-lg-2 col-form-label text-right pr-2">Fecha</label>
                <div class="col-lg-4">
                    <input type="text" class="form-control" readonly
                           value="{{ $fechaCampo('fechacaja_vista', $data->caja_movimientos?->fecha ?? '') }}">
                </div>
            </div>
        </div>
    </div>
</div>
