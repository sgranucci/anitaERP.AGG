@extends("theme.$theme.layout")
@section('titulo')
    Legajo cambio / devolución Nº {{ $data->numero }}
@endsection
@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cuentacaja/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/cambio_devolucion_opp.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/facturacion_local/cambio_devolucion_opp.js')) ?: time() }}" type="text/javascript"></script>
@include('ventas.facturacion_local.cambio_devolucion.partials.scripts_form', [
    'editable' => $editable ?? false,
    'lineaInteractiva' => ($editable ?? false) || ($puedeCorregirReemplazo ?? false) || ($puedeSumarReemplazo ?? false),
])
@if (session('abrir_pdf_opp'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.open(@json(session('abrir_pdf_opp')), '_blank', 'noopener');
});
</script>
@endif
@endsection

@php
    use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceEstadosSupport;
    use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceLiquidacionSupport;
    $estado = (string) $data->estado;
    $puedeActualizar = $editable && can('actualizar-cambio-devolucion-marketplace-facturacion-local', false);
    $ncDevolucionEmitida = (int) ($data->venta_nc_id ?? 0) > 0;
    $puedeElegirPrecioDevolucion = $data->exists
        && ! $ncDevolucionEmitida
        && ! in_array($estado, [
            CambioDevolucionMarketplaceEstadosSupport::CERRADO,
            CambioDevolucionMarketplaceEstadosSupport::ANULADO,
        ], true);
    $puedeCerrarOpp = $estado === CambioDevolucionMarketplaceEstadosSupport::PENDIENTE_COMPENSACION
        && (string) $data->diferencia_sentido === CambioDevolucionMarketplaceLiquidacionSupport::SENTIDO_FERLI_ACREDITA
        && (float) $data->diferencia_importe >= 0.01
        && (int) ($data->caja_movimiento_id ?? 0) <= 0
        && can('registrar-compensacion-cambio-devolucion-marketplace-facturacion-local', false);
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-primary">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">
                    Legajo Nº {{ $data->numero }} —
                    {{ CambioDevolucionMarketplaceEstadosSupport::etiqueta($estado) }}
                </h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('manual_cambio_devolucion_marketplace') }}" class="btn btn-outline-light btn-sm mr-1" target="_blank" rel="noopener" title="Circuito de cambios y devoluciones">
                        <i class="fas fa-book-open"></i> Guía del circuito
                    </a>
                    <a href="{{ route('facturacion_local_cambios_devolucion') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>

            <form id="form-cambio-devolucion" method="POST"
                  action="{{ route('actualizar_cambio_devolucion_marketplace', $data->id) }}"
                  enctype="multipart/form-data" class="form-horizontal">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('ventas.facturacion_local.cambio_devolucion.partials.form')
                </div>
            </form>

            <div class="card-footer d-flex flex-wrap align-items-center">
                @if ($puedeActualizar)
                    <button type="submit" form="form-cambio-devolucion" class="btn btn-success botonsubmit mr-2 mb-1">
                        <i class="fa fa-save"></i> Actualizar
                    </button>
                @endif

                @if ($estado === CambioDevolucionMarketplaceEstadosSupport::BORRADOR && can('actualizar-cambio-devolucion-marketplace-facturacion-local', false))
                    <form method="POST" action="{{ route('confirmar_cambio_devolucion_marketplace', $data->id) }}" class="d-inline mr-2 mb-1"
                          onsubmit="return confirm('¿Confirmar legajo y pasar a Abierto?');">
                        @csrf
                        <button type="submit" class="btn btn-primary">Confirmar legajo</button>
                    </form>
                @endif

                @if ($estado === CambioDevolucionMarketplaceEstadosSupport::ABIERTO)
                    @if (!empty($esSoloDevolucion) && can('actualizar-cambio-devolucion-marketplace-facturacion-local', false))
                        <form method="POST" action="{{ route('continuar_solo_devolucion_cambio_devolucion_marketplace', $data->id) }}" class="d-inline mr-2 mb-1"
                              onsubmit="return confirm('¿Continuar como solo devolución (reembolso), sin emitir factura de reemplazo?');">
                            @csrf
                            <button type="submit" class="btn btn-info">Continuar sin reemplazo (reembolso)</button>
                        </form>
                    @elseif (can('emitir-fac-cambio-devolucion-marketplace-facturacion-local', false))
                        <form method="POST" action="{{ route('emitir_fac_cambio_devolucion_marketplace', $data->id) }}" class="form-inline d-inline-flex align-items-center mr-2 mb-1"
                              onsubmit="var s=this.querySelector('[name=modo_precio]'); return confirm('¿Emitir la factura del par que se lleva con este precio: '+(s?s.options[s.selectedIndex].text:'')+'? Debe haber turno abierto en el local.');">
                            @csrf
                            @include('ventas.facturacion_local.cambio_devolucion.partials.select_modo_precio', [
                                'etiquetaPrecio' => 'Precio de la factura',
                            ])
                            <button type="submit" class="btn btn-warning">Emitir FAC reemplazo (NCD)</button>
                        </form>
                    @endif
                @endif

                @if (($puedeFacturarAgregado ?? false) && can('emitir-fac-cambio-devolucion-marketplace-facturacion-local', false))
                    <form method="POST" action="{{ route('emitir_fac_cambio_devolucion_marketplace', $data->id) }}" class="form-inline d-inline-flex align-items-center mr-2 mb-1"
                          onsubmit="var s=this.querySelector('[name=modo_precio]'); return confirm('¿Facturar solo el reemplazo agregado, con este precio: '+(s?s.options[s.selectedIndex].text:'')+'? La factura anterior queda.');">
                        @csrf
                        @include('ventas.facturacion_local.cambio_devolucion.partials.select_modo_precio', [
                            'etiquetaPrecio' => 'Precio de la factura',
                        ])
                        <button type="submit" class="btn btn-warning">Facturar el reemplazo agregado</button>
                    </form>
                @endif

                @if (($puedeRefacturar ?? false) && can('emitir-fac-cambio-devolucion-marketplace-facturacion-local', false))
                    <form method="POST" action="{{ route('emitir_fac_cambio_devolucion_marketplace', $data->id) }}" class="form-inline d-inline-flex align-items-center mr-2 mb-1"
                          onsubmit="var s=this.querySelector('[name=modo_precio]'); return confirm('¿Facturar de nuevo el reemplazo con este precio: '+(s?s.options[s.selectedIndex].text:'')+'? El legajo sigue hasta el cierre.');">
                        @csrf
                        @include('ventas.facturacion_local.cambio_devolucion.partials.select_modo_precio', [
                            'etiquetaPrecio' => 'Precio de la factura',
                        ])
                        <button type="submit" class="btn btn-warning">Facturar reemplazo de nuevo</button>
                    </form>
                @endif

                @if (($puedeNcReemplazo ?? false) && can('emitir-nc-cambio-devolucion-marketplace-facturacion-local', false))
                    <form method="POST" action="{{ route('emitir_nc_reemplazo_cambio_devolucion_marketplace', $data->id) }}" class="d-inline mr-2 mb-1"
                          onsubmit="return confirm('¿Emitir nota de crédito de la factura de reemplazo por un error de carga? El par vuelve al stock y después se puede facturar de nuevo. El legajo no se cierra.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">NC del reemplazo (error)</button>
                    </form>
                @endif

                @if ($estado === CambioDevolucionMarketplaceEstadosSupport::AGUARDANDO_RECEPCION && can('registrar-recepcion-cambio-devolucion-marketplace-facturacion-local', false))
                    <form method="POST" action="{{ route('registrar_recepcion_cambio_devolucion_marketplace', $data->id) }}" class="form-inline mr-2 mb-1">
                        @csrf
                        <select name="disposicion" class="form-control form-control-sm mr-1" required>
                            <option value="">Disposición…</option>
                            @foreach ($disposiciones as $dKey => $dLabel)
                                <option value="{{ $dKey }}">{{ $dLabel }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="observacion_recepcion" class="form-control form-control-sm mr-1" placeholder="Obs. recepción" style="width:180px">
                        <button type="submit" class="btn btn-info btn-sm">Registrar recepción</button>
                    </form>
                @endif

                @if ($puedeElegirPrecioDevolucion && $estado === CambioDevolucionMarketplaceEstadosSupport::RECIBIDO && can('emitir-nc-cambio-devolucion-marketplace-facturacion-local', false))
                    <form method="POST" action="{{ route('emitir_nc_cambio_devolucion_marketplace', $data->id) }}" class="form-inline d-inline-flex align-items-center mr-2 mb-1"
                          onsubmit="var s=this.querySelector('[name=modo_precio]'); return confirm('¿Emitir la nota de crédito de lo devuelto con este precio: '+(s?s.options[s.selectedIndex].text:'')+'? El motivo define si ese par ingresa al stock.');">
                        @csrf
                        @include('ventas.facturacion_local.cambio_devolucion.partials.select_modo_precio', [
                            'etiquetaPrecio' => 'Precio de la devolución',
                            'selectClass' => 'cdm-modo-precio-devolucion',
                        ])
                        <button type="submit" class="btn btn-danger">Emitir NC de lo devuelto (NCD)</button>
                    </form>
                @elseif ($puedeElegirPrecioDevolucion)
                    <div class="d-inline-flex flex-wrap align-items-center border rounded px-2 py-1 mr-2 mb-1">
                        @include('ventas.facturacion_local.cambio_devolucion.partials.select_modo_precio', [
                            'etiquetaPrecio' => 'Precio de la devolución',
                            'selectName' => '',
                            'selectClass' => 'cdm-modo-precio-devolucion',
                        ])
                        <span class="small text-muted">Se usa al emitir la nota de crédito de lo devuelto.</span>
                    </div>
                @endif

                @if ($puedeCerrarOpp)
                    <button type="button" class="btn btn-success btn-sm mr-2 mb-1" data-toggle="modal" data-target="#modal-cdm-cerrar-opp">
                        <i class="fa fa-money"></i> Cerrar con OPP (reembolso)
                    </button>
                @endif

                @if ($estado === CambioDevolucionMarketplaceEstadosSupport::PENDIENTE_COMPENSACION && can('registrar-compensacion-cambio-devolucion-marketplace-facturacion-local', false))
                    <form method="POST" action="{{ route('registrar_compensacion_cambio_devolucion_marketplace', $data->id) }}" class="form-inline mr-2 mb-1">
                        @csrf
                        <input type="text" name="compensacion_observacion" class="form-control form-control-sm mr-1" required
                               placeholder="Cómo se compensó (medio, importe…)" style="min-width:260px">
                        <button type="submit" class="btn btn-outline-success btn-sm">Cerrar con compensación</button>
                    </form>
                @endif

                @if ((int) ($data->caja_movimiento_id ?? 0) > 0)
                    <a href="{{ route('imprimir_opp_cambio_devolucion_marketplace', $data->id) }}"
                       class="btn btn-outline-primary btn-sm mr-2 mb-1" target="_blank" rel="noopener">
                        <i class="fa fa-file-pdf-o"></i> PDF OPP
                    </a>
                @endif

                @if (in_array($estado, [
                        CambioDevolucionMarketplaceEstadosSupport::BORRADOR,
                        CambioDevolucionMarketplaceEstadosSupport::ABIERTO,
                        CambioDevolucionMarketplaceEstadosSupport::AGUARDANDO_RECEPCION,
                    ], true) && can('anular-cambio-devolucion-marketplace-facturacion-local', false))
                    <form method="POST" action="{{ route('anular_cambio_devolucion_marketplace', $data->id) }}" class="form-inline mb-1"
                          onsubmit="return confirm('¿Anular el legajo? No anula comprobantes fiscales ya emitidos.');">
                        @csrf
                        <input type="text" name="observacion_anulacion" class="form-control form-control-sm mr-1" required
                               placeholder="Motivo anulación" style="width:200px">
                        <button type="submit" class="btn btn-outline-danger btn-sm">Anular legajo</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

@if ($puedeCerrarOpp)
<div class="modal fade" id="modal-cdm-cerrar-opp" tabindex="-1" role="dialog" aria-labelledby="modal-cdm-cerrar-opp-label" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('cerrar_con_opp_cambio_devolucion_marketplace', $data->id) }}" id="form-cdm-cerrar-opp">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-cdm-cerrar-opp-label">Cerrar legajo con OPP (reembolso)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Se emite una <strong>OPP sin proveedor</strong> por
                        <strong>$ {{ number_format((float) $data->diferencia_importe, 2, ',', '.') }}</strong>
                        (Ferli acredita), contra el puente NCD y el medio de pago elegido.
                        Al finalizar se cierra el legajo y se abre el PDF.
                    </p>
                    @include('caja.partials.campo_consulta_cuentacaja', [
                        'prefix' => 'cdm_opp_medio',
                        'layout' => 'form_row',
                        'label' => 'Medio de pago',
                        'inputName' => 'cuentacaja_id',
                        'inputId' => 'cdm_opp_cuentacaja_id',
                        'cuentacajaId' => '',
                        'codigo' => '',
                        'nombre' => '',
                        'required' => true,
                        'mostrar_editar' => false,
                        'col_label' => 'col-lg-3 col-form-label',
                        'col_input' => 'col-lg-9',
                        'ayuda' => 'Cuenta de caja distinta al puente NCD (Aplicación de crédito local).',
                    ])
                    <div class="form-group row">
                        <label for="cdm_opp_detalle_extra" class="col-lg-3 col-form-label">Detalle extra</label>
                        <div class="col-lg-9">
                            <input type="text" name="detalle_extra" id="cdm_opp_detalle_extra" class="form-control"
                                   maxlength="120" placeholder="Opcional (ej. transferencia, efectivo…)">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btn-cdm-cerrar-opp-submit">
                        Emitir OPP y cerrar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.caja.modalconsultacuentacaja')
@endif
@endsection
