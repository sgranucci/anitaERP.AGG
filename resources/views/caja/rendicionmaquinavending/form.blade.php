@php
    $esEdicion = isset($data) && $data && (int) $data->id > 0;
    $rendVentasId = old('maquinavending_rendicion_id', $data?->maquinavending_rendicion_id ?? '');
    $codigo = old('codigo', $data?->codigo ?? '');
    $empresaId = old('empresa_id', $data?->empresa_id ?? '');
    $empresasDisponibles = collect($empresa_query ?? []);
    $inicialApp = [
        'esEdicion' => $esEdicion,
        'rendicionCajaId' => (int) ($data?->id ?? 0),
        'maquinavendingRendicionId' => (int) $rendVentasId,
        'empresaId' => (int) $empresaId,
    ];
    $etiquetaRendicionVentas = $esEdicion
        ? '#'.(int) ($data->maquinavendingRendicion?->numero_cierre ?? 0).' — '.($data->maquinavending?->nombre ?? '')
        : '';
    $pvLabel = trim(($data?->puntoventaCae?->codigo ?? '').' — '.($data?->puntoventaCae?->nombre ?? ''), ' —');
@endphp
<div id="rendicion-mv-caja-app" data-inicial='@json($inicialApp)'>
    @if (($caja_id ?? 0) <= 0 && ! $esEdicion)
    <div class="alert alert-danger">
        No tiene caja asignada para hoy. Ingrese desde <strong>Movimientos de caja</strong> o solicite asignaci&oacute;n de cajero antes de registrar la presentaci&oacute;n.
    </div>
    @endif

    <div id="alert-errores-rendicion-mv-caja" class="alert alert-danger d-none" role="alert">
        <button type="button" class="close js-cerrar-error-rendicion" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="alert-heading mb-2"><i class="fa fa-exclamation-triangle"></i> Atenci&oacute;n</h4>
        <div class="js-contenido-errores-rendicion mb-0"></div>
    </div>

    <input type="hidden" name="caja_id" id="caja_id" value="{{ old('caja_id', $data?->caja_id ?? $caja_id ?? '') }}">
    <input type="hidden" name="maquinavending_rendicion_id" id="maquinavending_rendicion_id" value="{{ $rendVentasId }}">
    <input type="hidden" name="maquinavending_id" id="maquinavending_id" value="{{ old('maquinavending_id', $data?->maquinavending_id ?? '') }}">
    <input type="hidden" name="puntoventa_cae_id" id="puntoventa_cae_id" value="{{ old('puntoventa_cae_id', $data?->puntoventa_cae_id ?? '') }}">
    <input type="hidden" name="puntoventa_caea_id" id="puntoventa_caea_id" value="{{ old('puntoventa_caea_id', $data?->puntoventa_caea_id ?? '') }}">

    <div class="card card-outline card-secondary mb-3">
        <div class="card-header py-2"><strong>Datos de la rendici&oacute;n</strong></div>
        <div class="card-body py-2">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="empresa_id" class="requerido">Empresa</label>
                    @if ($esEdicion)
                        <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $empresaId }}">
                        <input type="text" class="form-control" readonly value="{{ $data->empresa?->nombre ?? '—' }}">
                    @else
                        @include('includes.form-empresa-asignada-control', [
                            'empresa_query' => $empresasDisponibles,
                            'empresa_id' => $empresaId,
                            'id' => 'empresa_id',
                            'name' => 'empresa_id',
                            'required' => true,
                            'opcion_vacia' => '— Seleccionar —',
                        ])
                    @endif
                </div>
                <div class="form-group col-md-4">
                    <label for="fecharendicion" class="requerido">Fecha/hora registro en caja</label>
                    <input type="datetime-local" name="fecharendicion" id="fecharendicion" class="form-control" required
                           value="{{ old('fecharendicion', ($data?->fecharendicion) ? $data->fecharendicion->format('Y-m-d\\TH:i') : now()->format('Y-m-d\\TH:i')) }}">
                    <small class="text-muted">Momento real del registro. La fecha contable es la jornada de la rendici&oacute;n Ventas.</small>
                </div>
                <div class="form-group col-md-4">
                    <label for="codigo" class="requerido">Ticket / c&oacute;digo caja</label>
                    <input type="text" name="codigo" id="codigo" class="form-control" required maxlength="50" value="{{ $codigo }}"
                           placeholder="Se propone al elegir rendici&oacute;n Ventas">
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-info mb-3" id="card-seleccion-rendicion-ventas">
        <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap">
            <strong>Rendici&oacute;n Ventas a presentar</strong>
            <a href="{{ $comprobante_ventas_url ?? '#' }}" id="link-comprobante-ventas"
               class="btn btn-danger btn-sm {{ ! empty($comprobante_ventas_url) ? '' : 'd-none' }}"
               target="_blank" rel="noopener">
                <i class="fa fa-file-pdf-o"></i> Comprobante Ventas
            </a>
        </div>
        <div class="card-body py-2">
            @if ($esEdicion)
                <p class="mb-0">
                    <span class="text-muted">Rendici&oacute;n Ventas:</span>
                    <strong id="lbl-rendicion-ventas-seleccionada">{{ $etiquetaRendicionVentas ?: '—' }}</strong>
                </p>
            @else
                <div class="form-row align-items-end">
                    <div class="form-group col-md-8 mb-md-0">
                        <label for="etiqueta_rendicion_ventas" class="requerido">Rendici&oacute;n pendiente</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="etiqueta_rendicion_ventas" readonly
                                   placeholder="Consultar rendici&oacute;n pendiente…" value="{{ $etiquetaRendicionVentas }}">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-warning consultarendicionventas" title="Buscar rendici&oacute;n Ventas">
                                    <i class="fa fa-search"></i> Consultar
                                </button>
                            </div>
                        </div>
                        <small class="text-muted">Solo rendiciones registradas en Ventas y a&uacute;n no presentadas en caja.</small>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if (! $esEdicion)
    <div id="aviso-sin-rendicion-cargada" class="alert alert-warning mb-3">
        <strong><i class="fa fa-info-circle"></i> Para ver totales y medios de pago:</strong>
        seleccione la empresa, pulse <strong>Consultar</strong> y elija la rendici&oacute;n Ventas pendiente.
    </div>
    @endif

    <div id="panel-datos-rendicion" class="{{ $rendVentasId ? '' : 'd-none' }}">
        <div class="card card-outline card-primary mb-3">
            <div class="card-header py-2 bg-primary text-white">
                <strong><i class="fa fa-check-square-o"></i> Verificaci&oacute;n del cajero — datos vending</strong>
            </div>
            <div class="card-body py-2">
                <p class="mb-2 small">
                    Compare con el comprobante de rendici&oacute;n entregado por el operador de vending:
                    totales de ventas y cobranzas, medios de pago y jornada contable.
                </p>
                <ul class="small mb-0 pl-3">
                    <li class="text-muted">Abrir el comprobante Ventas (bot&oacute;n arriba) si est&aacute; disponible.</li>
                    <li class="text-muted">Revisar total ventas y total cobrado del cierre.</li>
                    <li class="text-muted">Contrastar medios rendidos en caja con lo f&iacute;sico recibido.</li>
                </ul>
            </div>
        </div>

        <div class="card card-outline card-info mb-3">
            <div class="card-header py-2"><strong>Datos de la rendici&oacute;n Ventas</strong></div>
            <div class="card-body py-2 small">
                <div class="row">
                    <div class="col-md-4">
                        <span class="text-muted d-block">Jornada contable</span>
                        <strong id="lbl-fecha-jornada">{{ $data?->maquinavendingRendicion?->fecha_jornada?->format('d/m/Y') ?? '—' }}</strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted d-block">Punto de venta</span>
                        <strong id="lbl-pv">{{ $pvLabel ?: '—' }}</strong>
                    </div>
                    <div class="col-md-2">
                        <span class="text-muted d-block">Total ventas</span>
                        <strong id="lbl-total-ventas">${{ number_format((float) old('totalfactura', $data?->totalfactura ?? 0), 2, ',', '.') }}</strong>
                    </div>
                    <div class="col-md-2">
                        <span class="text-muted d-block">Total cobrado</span>
                        <strong id="lbl-total-cobrado">${{ number_format((float) old('totalcobrado', $data?->totalcobrado ?? 0), 2, ',', '.') }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-secondary mb-3">
            <div class="card-header py-2">
                <strong>Medios rendidos en caja</strong>
                <small class="text-muted d-block font-weight-normal mt-1">Montos precargados desde la rendici&oacute;n Ventas (solo lectura). Lo declarado aqu&iacute; es lo que se replica en Anita como <strong>rendvalor</strong>.</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" id="tabla-movimientos-mv-caja">
                        <thead class="thead-light">
                            <tr>
                                <th>Medio de pago</th>
                                <th class="text-right" style="width:15%;">Monto rendido</th>
                                <th class="text-right" style="width:12%;">Cotiz.</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-movimientos-mv-caja">
                        @if ($esEdicion)
                            @foreach ($data->movimientos as $idx => $mov)
                            <tr>
                                <td>
                                    {{ $mov->cuentacaja->nombre ?? '' }}
                                    <input type="hidden" name="movimientos[{{ $idx }}][cuentacaja_id]" value="{{ $mov->cuentacaja_id }}">
                                </td>
                                <td><input type="number" step="0.01" class="form-control form-control-sm text-right mv-monto" name="movimientos[{{ $idx }}][monto]" value="{{ number_format((float) $mov->monto, 2, '.', '') }}" readonly></td>
                                <td><input type="number" step="0.0001" class="form-control form-control-sm text-right" name="movimientos[{{ $idx }}][cotizacion]" value="{{ number_format((float) $mov->cotizacion, 4, '.', '') }}" readonly></td>
                            </tr>
                            @endforeach
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <input type="hidden" name="totalfactura" id="totalfactura" value="{{ old('totalfactura', $data?->totalfactura ?? 0) }}">
        <input type="hidden" name="totalcobrado" id="totalcobrado" value="{{ old('totalcobrado', $data?->totalcobrado ?? 0) }}">
        <input type="hidden" name="iniciodelfondo" value="{{ old('iniciodelfondo', $data?->iniciodelfondo ?? 0) }}">
        <input type="hidden" name="totalinvitacion" value="0">
        <input type="hidden" name="totalnotacredito" value="0">
        <input type="hidden" name="totalredondeo" value="0">
        <input type="hidden" name="totalredondeoinvitacion" value="0">
        <input type="hidden" name="sobrantefaltante" id="sobrantefaltante" value="{{ old('sobrantefaltante', $data?->sobrantefaltante ?? 0) }}">
    </div>

    <div class="form-group row">
        <label for="observacion" class="col-lg-3 col-form-label">Observaciones</label>
        <div class="col-lg-8">
            <textarea name="observacion" id="observacion" class="form-control" rows="2" maxlength="65535">{{ old('observacion', $data?->observacion ?? '') }}</textarea>
        </div>
    </div>
</div>
