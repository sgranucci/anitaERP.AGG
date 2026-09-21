@extends("theme.$theme.layout")
@section('titulo')
    Legajo cambio / devolución Nº {{ $data->numero }}
@endsection
@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script>
window.cdmBuscarVentaUrl = @json(route('api_buscar_venta_cambio_devolucion_marketplace'));
</script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/cambio_devolucion/form.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceEstadosSupport;
    $estado = (string) $data->estado;
    $puedeActualizar = $editable && can('actualizar-cambio-devolucion-marketplace-facturacion-local', false);
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

                @if ($estado === CambioDevolucionMarketplaceEstadosSupport::ABIERTO && can('emitir-fac-cambio-devolucion-marketplace-facturacion-local', false))
                    <form method="POST" action="{{ route('emitir_fac_cambio_devolucion_marketplace', $data->id) }}" class="d-inline mr-2 mb-1"
                          onsubmit="return confirm('¿Emitir FAC de reemplazo con medio puente NCD? Debe haber turno abierto en el local.');">
                        @csrf
                        <button type="submit" class="btn btn-warning">Emitir FAC reemplazo (NCD)</button>
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

                @if ($estado === CambioDevolucionMarketplaceEstadosSupport::RECIBIDO && can('emitir-nc-cambio-devolucion-marketplace-facturacion-local', false))
                    <form method="POST" action="{{ route('emitir_nc_cambio_devolucion_marketplace', $data->id) }}" class="d-inline mr-2 mb-1"
                          onsubmit="return confirm('¿Emitir NC completa de la factura original con medio puente NCD?');">
                        @csrf
                        <button type="submit" class="btn btn-danger">Emitir NC original (NCD)</button>
                    </form>
                @endif

                @if ($estado === CambioDevolucionMarketplaceEstadosSupport::PENDIENTE_COMPENSACION && can('registrar-compensacion-cambio-devolucion-marketplace-facturacion-local', false))
                    <form method="POST" action="{{ route('registrar_compensacion_cambio_devolucion_marketplace', $data->id) }}" class="form-inline mr-2 mb-1">
                        @csrf
                        <input type="text" name="compensacion_observacion" class="form-control form-control-sm mr-1" required
                               placeholder="Cómo se compensó (medio, importe…)" style="min-width:260px">
                        <button type="submit" class="btn btn-success btn-sm">Cerrar con compensación</button>
                    </form>
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
@endsection
