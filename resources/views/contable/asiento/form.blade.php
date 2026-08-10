<div class="asiento-datos-form">
    <div class="card card-outline card-info mb-3">
        <div class="card-header py-2">
            <h3 class="card-title mb-0">
                <i class="fa fa-book mr-1"></i> Datos del asiento
            </h3>
        </div>
        <div class="card-body">
        <div class="row">
            <div class="col-sm-6">
                @include('includes.form-empresa-asignada', [
                    'empresa_query' => $empresa_query,
                    'empresa_id' => $data->empresa_id ?? session('empresa_id'),
                    'mostrar_id' => true,
                    'col_label' => 'col-lg-4 text-right pr-2',
                    'col_input' => 'col-lg-7',
                ])
                <div class="form-group row">
                    <label for="tipoasiento_id" class="col-lg-4 control-label text-right pr-2">Tipo de asiento</label>
                    <div class="col-lg-7">
                    <select name="tipoasiento_id" id="tipoasiento_id" data-placeholder="Tipo de asiento" class="form-control required" data-fouc>
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
            </div>
            <div class="col-sm-6">
                <div class="form-group row">
                    <label for="fecha" class="col-lg-4 control-label text-right pr-2">Fecha</label>
                    <div class="col-lg-5">
                        <input type="date" name="fecha" id="fecha" class="form-control" value="{{old('fecha', $data->fecha ?? date('Y-m-d'))}}">
                    </div>
                </div>
            </div>
        </div>
        <div class="form-group row mb-0">
            <label for="observacion" class="col-lg-2 control-label text-right pr-2">Observaciones</label>
            <div class="col-lg-9">
                <input type="text" name="observacion" id="observacion" class="form-control" value="{{old('observacion', $data->observacion ?? '')}}">
            </div>
        </div>
        </div>
    </div>

    @include('contable.asiento.partials.origen_remesa')
    @include('contable.asiento.partials.referencias')

    <input type="hidden" id="numeroasiento" name="numeroasiento" value="{{ $data->numeroasiento ?? '' }}" />
    <input type="hidden" id="id" name="id" value="{{ $data->id ?? '' }}" />
    @include('includes.proceso_overlay_aviso', [
        'overlayId' => 'asiento-guardando-overlay',
        'tituloId' => 'asiento-guardando-titulo',
        'subtituloId' => 'asiento-guardando-subtitulo',
        'titulo' => 'Guardando asiento…',
        'subtitulo' => 'Por favor espere. No cierre la página.',
    ])
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
            font-size: 1.05rem;
        }
        #cuenta-table .debe,
        #cuenta-table .haber {
            font-size: 1.05rem;
            font-weight: 600;
            min-width: 7.5rem;
            letter-spacing: 0.01em;
        }
        #cuenta-table .cotizacion {
            font-size: 0.95rem;
            min-width: 5.5rem;
        }
        #cuenta-table td.asiento-monto-celda {
            min-width: 8rem;
        }
        #cuenta-table .asiento-detalle-celda {
            max-width: 11rem;
            vertical-align: middle;
        }
        #cuenta-table .asiento-detalle-preview {
            display: block;
            max-width: 8.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.75rem;
            line-height: 1.2;
            color: #495057;
        }
        #cuenta-table .asiento-detalle-preview.is-empty {
            color: #adb5bd;
            font-style: italic;
        }
        #cuenta-table .asiento-abrir-detalle {
            padding: 0.15rem 0.4rem;
            line-height: 1.2;
            flex-shrink: 0;
        }
        #cuenta-table .asiento-abrir-detalle.has-detalle {
            border-color: #17a2b8;
            color: #117a8b;
        }
    </style>
    <div class="card card-outline card-info mb-0">
        <div class="card-header py-2 d-flex align-items-center justify-content-between">
            <h3 class="card-title mb-0"><i class="fa fa-list"></i> Cuentas</h3>
        </div>
        <div class="card-body">
        <table class="table table-sm table-bordered" id="cuenta-table">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th style="width: 12%;">C&oacute;digo</th>
                    <th style="width: 18%;">Descripci&oacute;n</th>
                    <th style="width: 13%;">Centro de costo</th>
                    <th style="width: 6%;">Moneda</th>
                    <th style="width: 12%;" class="text-right">Debe</th>
                    <th style="width: 12%;" class="text-right">Haber</th>
                    <th style="width: 9%;" class="text-right">Cotizaci&oacute;n</th>
                    <th style="width: 12%;">Detalle</th>
                    <th style="width: 4%;"></th>
                </tr>
            </thead>
            <tbody id="tbody-cuenta-table">
            @if ($data->asiento_movimientos ?? '') 
                @foreach (old('cuenta', $data->asiento_movimientos->count() ? $data->asiento_movimientos : ['']) as $cuenta)
                    <tr class="item-cuenta">
                        <td>
                            @php
                                $ctaIdLinea = (int) ($cuenta->cuentacontable_id ?? 0);
                            @endphp
                            <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;" id="cuenta">
                                <input type="hidden" name="cuenta[]" class="form-control iicuenta" readonly value="{{ $loop->index+1 }}" />
                                <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="{{$cuenta->cuentacontable_id ?? ''}}" >
                                <input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="{{$cuenta->cuentacontable_id ?? ''}}" >
                                <button type="button" title="Consulta cuentas contables (F1)" style="padding:1; flex: 0 0 auto;"
                                        class="btn-accion-tabla consultacuentacontable tooltipsC">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                @if (can('editar-cuentas-contables', false) || can('listar-cuentas-contables', false))
                                    <a href="{{ $ctaIdLinea > 0 ? route('editar_cuentacontable', ['id' => $ctaIdLinea, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
                                       target="_blank" rel="noopener"
                                       class="btn-accion-tabla btn-link-editar-cuentacontable tooltipsC flex-shrink-0 {{ $ctaIdLinea > 0 ? '' : 'd-none' }}"
                                       title="Consultar cuenta contable en ABM">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                <input type="text" style="flex: 0 0 100px; width: 100px; height: 38px;"
                                       class="codigocuentacontable form-control" name="codigos[]"
                                       value="{{$cuenta->cuentacontables->codigo ?? ''}}"
                                       placeholder="C&oacute;d." autocomplete="off">
                                <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="{{$cuenta->cuentacontables->codigo ?? ''}}" >
                            </div>
                        </td>
                        <td>
                            <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombrecuentacontable form-control" name="nombres[]"
                                   value="{{$cuenta->cuentacontables->nombre ?? ''}}" readonly placeholder="Descripci&oacute;n">
                        </td>
                        <td>
                            @php
                                $ccIdLinea = old('centrocosto_ids.'.$loop->index, $cuenta->centrocosto_id ?? 0);
                            @endphp
                            {{-- Siempre al menos una opción: un <select> vacío no viaja en el POST y desalinea índices --}}
                            <select name="centrocosto_ids[]" data-placeholder="Centro de costo" class="centrocosto form-control" data-fouc>
                                <option value="{{ $ccIdLinea }}" selected>{{ ((int) $ccIdLinea > 0) ? $ccIdLinea : 'Sin CC' }}</option>
                            </select>
                            <input type="hidden" class="centrocosto_id_previo" name="centrocosto_id_previo[]" value="{{ $ccIdLinea }}" >
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
                        <td class="asiento-monto-celda">
                            @php
                                $debeValor = old('debes.'.$loop->index, ($cuenta->monto ?? 0) > 0 ? number_format($cuenta->monto, 2, ',', '.') : '');
                            @endphp
                            <input type="text" inputmode="decimal" name="debes[]" class="form-control text-right debe" value="{{ $debeValor }}">
                        </td>
                        <td class="asiento-monto-celda">
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
                        <td class="asiento-detalle-celda">
                            @php
                                $detalleLinea = old('observaciones.'.$loop->index, $cuenta->observacion ?? '');
                                $detalleTrim = trim((string) $detalleLinea);
                            @endphp
                            <textarea name="observaciones[]" class="d-none asiento-ta-detalle observacion" aria-hidden="true">{{ $detalleLinea }}</textarea>
                            <div class="d-flex align-items-center" style="gap: 4px;">
                                <button type="button"
                                        title="{{ $detalleTrim !== '' ? 'Editar detalle de la línea' : 'Agregar detalle de la línea' }}"
                                        class="btn btn-sm btn-outline-secondary asiento-abrir-detalle {{ $detalleTrim !== '' ? 'has-detalle' : '' }}">
                                    <i class="fa fa-align-left"></i>
                                </button>
                                <span class="asiento-detalle-preview {{ $detalleTrim === '' ? 'is-empty' : '' }}"
                                      title="{{ $detalleTrim }}">{{ $detalleTrim !== '' ? $detalleTrim : '—' }}</span>
                            </div>
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
                <button id="agrega_renglon_cuenta" type="button" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-plus"></i> Agrega rengl&oacute;n
                </button>
                <span class="text-muted small ml-2">El detalle de la 1.&ordf; l&iacute;nea se copia a los renglones nuevos (y a los vac&iacute;os al guardarlo). En este ABM la moneda la fija el 1.&ordf; movimiento (no se pueden mezclar monedas).</span>
            </div>
        </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAsientoDetalleLinea" tabindex="-1" role="dialog" aria-labelledby="modalAsientoDetalleLineaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalAsientoDetalleLineaLabel">Detalle de la l&iacute;nea</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">Leyenda / detalle del movimiento contable. Si es la primera l&iacute;nea, al guardar se copia a los renglones sin detalle.</p>
                <textarea id="asiento_detalle_linea_editor" class="form-control" rows="6" maxlength="255" placeholder="Detalle de la l&iacute;nea…"></textarea>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary btn-sm" id="asiento_detalle_linea_guardar">Guardar</button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
@include('includes.contable.modalconsultacuentacontable')
@include('includes.contable.modalconsulta_asiento_ordencompra')
@include('includes.contable.modalconsulta_asiento_comprobante')
@include('includes.contable.modalconsulta_asiento_venta')
@include('contable.asiento.copiarasientomodal')
@include('contable.asiento.revertirasientomodal')

