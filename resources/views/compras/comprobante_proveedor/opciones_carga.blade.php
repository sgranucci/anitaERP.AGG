@extends("theme.$theme.layout")
@section('titulo')
    Cargar factura de proveedor
@endsection

@section("scripts")
@if (!empty($pdfIaHabilitado) && can('crear-precarga-proveedores', false))
<script src="{{ asset('assets/pages/scripts/compras/precarga_comprobante_proveedor/pdf_ia.js') }}" type="text/javascript"></script>
@endif
@if (can('crear-comprobante-proveedor', false))
<script src="{{ asset('assets/pages/scripts/compras/comprobante_proveedor/opciones_carga.js') }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-file-text-o"></i> ¿Cómo desea cargar la factura?</h3>
                <div class="card-tools">
                    <a href="{{ route('comprobante_proveedor') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">
                    Elija una de las cuatro formas de ingreso. La precarga (agente/API o IA) deja los datos listos para generar el comprobante;
                    las otras opciones abren el comprobante en borrador directamente.
                </p>

                <div class="row">
                    {{-- 1. Precarga existente (AGG / API) --}}
                    @if (can('listar-precarga-proveedores', false))
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="card h-100 border-info">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title text-info"><i class="fa fa-inbox"></i> Precarga</h5>
                                <p class="card-text small flex-grow-1">
                                    Facturas ya recibidas por el agente o la API (correo/PDF en <code>Facturas_scan</code>).
                                    Revise el listado y genere el comprobante desde cada fila.
                                </p>
                                <a href="{{ route('precarga_comprobante_proveedor') }}" class="btn btn-info btn-sm mt-auto">
                                    Ir al listado de precargas
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- 2. OC directa --}}
                    @if (can('crear-comprobante-proveedor', false))
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="card h-100 border-success">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title text-success"><i class="fa fa-shopping-cart"></i> Con OC</h5>
                                <p class="card-text small flex-grow-1">
                                    Alta directa del comprobante vinculado a una orden de compra (número de
                                    <strong>6 dígitos</strong>, como en Anita).
                                </p>
                                <form id="form-cp-desde-oc" class="mt-auto"
                                      data-url-resolver="{{ route('comprobante_proveedor_resolver_oc') }}">
                                    <div class="input-group input-group-sm mb-2">
                                        <input type="text" id="cp-numero-oc" class="form-control" maxlength="6"
                                               pattern="\d{6}" placeholder="Ej. 214482" inputmode="numeric"
                                               title="6 dígitos numéricos">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-success">Facturar</button>
                                        </div>
                                    </div>
                                    <div id="cp-oc-error" class="text-danger small d-none"></div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- 3. Sin OC --}}
                    @if (can('crear-comprobante-proveedor', false))
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="card h-100 border-secondary">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title text-secondary"><i class="fa fa-pencil"></i> Sin OC</h5>
                                <p class="card-text small flex-grow-1">
                                    Gastos o servicios sin orden de compra. Carga manual de proveedor, conceptos IVA y totales.
                                </p>
                                <a href="{{ route('crear_comprobante_proveedor') }}" class="btn btn-outline-secondary btn-sm mt-auto">
                                    Nuevo comprobante manual
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- 4. PDF modelo IA propio --}}
                    @if (!empty($pdfIaHabilitado) && can('crear-precarga-proveedores', false))
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="card h-100 border-primary">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title text-primary"><i class="fa fa-magic"></i> PDF — IA Anita</h5>
                                <p class="card-text small flex-grow-1">
                                    Suba el PDF; el modelo identifica empresa, proveedor, OC (obligatoria), conceptos y alícuotas.
                                    Crea una precarga para revisar antes de facturar.
                                </p>
                                <button type="button" class="btn btn-primary btn-sm mt-auto" data-toggle="modal" data-target="#modal-precarga-pdf-ia">
                                    Cargar PDF con IA
                                </button>
                            </div>
                        </div>
                    </div>
                    @elseif (can('crear-precarga-proveedores', false))
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="card h-100 border-light bg-light">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title text-muted"><i class="fa fa-magic"></i> PDF — IA Anita</h5>
                                <p class="card-text small flex-grow-1 text-muted">
                                    Modelo propio de lectura de facturas (respaldo del agente AGG y uso en otros clientes).
                                    Activar con <code>COMPROBANTE_PROVEEDOR_PDF_IA_HABILITADO=true</code>.
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if (!empty($pdfIaHabilitado) && can('crear-precarga-proveedores', false))
    @include('compras.precarga_comprobante_proveedor.partials.modal_pdf_ia')
@endif
@endsection
