@extends("theme.$theme.layout")
@section('titulo')
Recepción {{ $recepcion->numerorecepcion }}
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/articulo_proveedor/operativo.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/confirmar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor/confirmar.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor/form.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/consulta_oc.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/depmae/consulta.js')) ?: time() }}" type="text/javascript"></script>
@if (config('recepcion_proveedor.modal_articulo_proveedor_habilitado'))
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/modal_articulo_proveedor.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor/modal_articulo_proveedor.js')) ?: time() }}" type="text/javascript"></script>
@endif
@if (can('cambiar-cotizacion-recepcion-proveedor', false))
<script>
window.abrirRecalcularTraTito = @json(session('abrir_recalcular_tra_tito'));
</script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/cambiar_cotizacion.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor/cambiar_cotizacion.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/recalcular_tra_tito.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor/recalcular_tra_tito.js')) ?: time() }}" type="text/javascript"></script>
@endif
<script src="{{ asset('assets/pages/scripts/seguridad/ingreso_proveedor/modal.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/seguridad/ingreso_proveedor/modal.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/seguridad/ingreso_proveedor/autorizar.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('recepcion_proveedor', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-truck"></i>
                    Recepción {{ $recepcion->numerorecepcion }}
                    @if($recepcion->estado === 'BORRADOR')
                        <span class="badge badge-secondary ml-2">BORRADOR</span>
                    @elseif($recepcion->estado === 'CONFIRMADA')
                        <span class="badge badge-success ml-2">CONFIRMADA</span>
                    @elseif($recepcion->estado === 'ANULADA')
                        <span class="badge badge-dark ml-2">ANULADA</span>
                    @else
                        <span class="badge badge-info ml-2">{{ $recepcion->estado }}</span>
                    @endif
                    @if($recepcion->fl_precio_pendiente_aprobacion)
                        <span class="badge badge-info ml-2">Precio pendiente OC</span>
                    @elseif($recepcion->fl_precio_diferencia)
                        <span class="badge badge-warning ml-2">Precio distinto a OC</span>
                    @endif
                </h3>
                <div class="card-tools d-flex flex-wrap align-items-center">
                    @include('stock.recepcion_proveedor.partials.boton_imprimir_com_pdf', [
                        'recepcionId' => $recepcion->id,
                        'clase' => 'btn btn-danger btn-sm mr-2',
                    ])
                    @if($recepcion->estado === 'BORRADOR' && empty($soloConsulta) && can('confirmar-recepcion-proveedor', false) && ! $recepcion->fl_precio_pendiente_aprobacion && ($validacionAbonoCompleta ?? true))
                    <button type="submit" class="btn btn-success btn-sm mr-2" form="form-recepcion-confirmar"
                            id="btn-confirmar-recepcion-proveedor">
                        <i class="fa fa-check"></i> Confirmar
                    </button>
                    @endif
                    @if (can('crear-ingreso-proveedor', false) && !empty($mostrar_solapa_ingresos))
                    <button type="button" class="btn btn-outline-light btn-sm mr-2 js-ingreso-ticket-nuevo" title="Solicitar ticket de ingreso a planta">
                        <i class="fa fa-id-badge"></i> Ticket de ingreso
                    </button>
                    @endif
                    @if (!empty($mostrar_solapa_validacion))
                    <button type="button" class="btn btn-outline-light btn-sm mr-2 js-rp-abrir-validacion" title="Ver la última carga de respuestas de la validación de abono">
                        <i class="fa fa-check-square-o"></i> Ver validación
                    </button>
                    @endif
                    @if (empty($ocultarVolver))
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @else
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.close()">
                        <i class="fa fa-fw fa-times"></i> Cerrar solapa
                    </button>
                    @endif
                </div>
            </div>
            @if($recepcion->estado === 'BORRADOR')
            <form action="{{ route('actualizar_recepcion_proveedor', ['id' => $recepcion->id] + ($filtrosQuery ?? [])) }}" id="form-recepcion-proveedor" method="POST" enctype="multipart/form-data"
                  @if(!empty($soloConsulta) && empty($puedeActualizarRecepcion)) onsubmit="return false;" @endif>
                @csrf
                @method('PUT')
                @if (! empty($soloConsulta))
                    <input type="hidden" name="origen" value="modal_consulta">
                @endif
                <div class="card-body @if(!empty($soloConsulta) && empty($puedeActualizarRecepcion)) pe-none @endif" @if(!empty($soloConsulta) && empty($puedeActualizarRecepcion)) style="opacity:.92" @endif>
                    @include('compras.contrato_validacion_abono.partials.banner')
                    @include('stock.recepcion_proveedor.form', [
                        'modoEdicion' => ! empty($soloConsulta) ? ! empty($puedeActualizarRecepcion) : true,
                        'recepcion' => $recepcion,
                        'asientoPreview' => $asientoPreview ?? ['activo' => false],
                    ])
                </div>
            </form>
            <form action="{{ route('confirmar_recepcion_proveedor', ['id' => $recepcion->id] + ($filtrosQuery ?? [])) }}" id="form-recepcion-confirmar" method="POST" class="d-none" aria-hidden="true">
                @csrf
            </form>
            <form action="{{ route('eliminar_recepcion_proveedor', ['id' => $recepcion->id]) }}" id="form-recepcion-eliminar" method="POST" class="d-none" aria-hidden="true">
                @csrf
                @method('DELETE')
            </form>
            @if (empty($soloConsulta) || ! empty($puedeActualizarRecepcion))
            <div class="card-footer">
                @include('stock.recepcion_proveedor.partials.acciones_borrador')
            </div>
            @endif
            @else
            <div class="card-body">
                @include('compras.contrato_validacion_abono.partials.banner')
                @include('stock.recepcion_proveedor.form', [
                    'modoEdicion' => false,
                    'recepcion' => $recepcion,
                    'asientoPreview' => $asientoPreview ?? ['activo' => false],
                ])
            </div>
            <div class="card-footer">
                <div class="d-flex flex-wrap align-items-center">
                    @include('stock.recepcion_proveedor.partials.boton_imprimir_com_pdf', [
                        'recepcionId' => $recepcion->id,
                        'clase' => 'btn btn-danger mr-2 mb-2',
                    ])
                    @if (empty($soloConsulta))
                    @if($recepcion->estado === 'CONFIRMADA' && can('cambiar-cotizacion-recepcion-proveedor', false))
                    <button type="button" class="btn btn-info mr-2 mb-2 btn-cambiar-cotizacion-recepcion"
                            data-id="{{ $recepcion->id }}"
                            data-numero="{{ $recepcion->numerorecepcion }}"
                            data-cotizacion="{{ rtrim(rtrim(number_format((float) ($recepcion->cotizacion ?? 1), 6, '.', ''), '0'), '.') }}">
                        <i class="fas fa-dollar-sign"></i> Cambiar cotización
                    </button>
                    @endif
                    @if($recepcion->estado === 'CONFIRMADA' && $recepcion->tipo === 'RECEPCION' && can('devolver-recepcion-proveedor', false))
                    <a href="{{ route('crear_devolucion_recepcion_proveedor', $recepcion->id) }}" class="btn btn-warning mr-2 mb-2">
                        <i class="fa fa-undo"></i> Devolución a proveedor
                    </a>
                    @endif
                    @endif
                </div>
            </div>
            @endif
        </div>
        @include('stock.recepcion_proveedor.partials.partes_unicas')
    </div>
</div>
@if($recepcion->estado === 'BORRADOR')
@include('stock.recepcion_proveedor.partials.modal_accion_lineas_sin_cantidad')
@endif
@include('includes.stock.modalconsultadeposito')
@include('includes.stock.modalconsultaordencompra_recepcion')
@include('stock.recepcion_proveedor.partials.modal_cambiar_cotizacion')
@if (can('cambiar-cotizacion-recepcion-proveedor', false))
@include('stock.recepcion_proveedor.partials.modal_recalcular_tra_tito')
<input type="hidden" id="rp-preview-recalcular-tra-tito-url" value="{{ route('recepcion_proveedor_preview_recalcular_tra_tito', ['id' => '__ID__']) }}">
<input type="hidden" id="rp-aplicar-recalcular-tra-tito-url" value="{{ route('recepcion_proveedor_aplicar_recalcular_tra_tito', ['id' => '__ID__']) }}">
@endif
@if (!empty($mostrar_solapa_ingresos))
    @include('includes.seguridad.modal_ingreso_proveedor', [
        'ingresoContexto' => [
            'empresa_id' => optional($recepcion->ordencompras)->empresa_id ?? $recepcion->empresa_id ?? null,
            'proveedor_id' => optional($recepcion->ordencompras)->proveedor_id ?? optional($recepcion->proveedores)->id ?? null,
            'ordencompra_id' => $recepcion->ordencompra_id ?? null,
        ],
    ])
    @include('seguridad.ingreso_proveedor.partials.modal_rechazo')
@endif
@endsection
