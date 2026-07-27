@extends("theme.$theme.layout")

@section('titulo')
    Portal de Proveedores
@endsection

@section('styles')
    @include('compras.portal_proveedor.partials.estilos')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
@if (!empty($pdfIaHabilitado) && can('cargar-portal-proveedores', false))
<script src="{{ asset('assets/pages/scripts/compras/precarga_comprobante_proveedor/pdf_ia.js') }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="alert alert-info">
            <strong>MVP interno del Portal de Proveedores.</strong>
            Seleccione un proveedor para simular su cuenta. Hay dos formas de presentar facturas:
            <strong>scan PDF directo</strong> o <strong>envío por mail</strong> a la casilla del agente
            (mismo pipeline Document AI / conceptos por CC que Facturas_scan).
            En la versión externa el proveedor se obtendrá de la sesión autenticada y este selector no existirá.
        </div>

        @include('compras.portal_proveedor.partials.canal_mail', ['canalMail' => $canalMail ?? []])

        @include('compras.portal_proveedor.partials.selector_cuenta', [
            'rutaSelector' => route('portal_proveedores'),
            'proveedor' => $proveedor,
            'proveedorId' => $proveedorId,
        ])

        @if ($proveedor)
        <div class="card">
            @include('compras.portal_proveedor.partials.cabecera_proveedor', [
                'proveedor' => $proveedor,
                'moduloActivo' => 'facturas',
                'canalMail' => $canalMail ?? [],
                'pdfIaHabilitado' => $pdfIaHabilitado ?? false,
            ])

            <div class="card-body">
                @include('compras.portal_proveedor.partials.nav_modulos', [
                    'moduloActivo' => 'facturas',
                    'proveedorId' => $proveedorId,
                ])

                <div class="table-responsive p-0">
                    <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>ID</th>
                                <th>Empresa</th>
                                <th>Comprobante</th>
                                <th>Fecha</th>
                                <th>OC</th>
                                <th class="text-right">Total</th>
                                <th>Estado</th>
                                <th>Origen</th>
                                <th>Recibida</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($precargas as $precarga)
                            <tr>
                                <td>{{ $precarga->id }}</td>
                                <td>{{ optional($precarga->empresas)->nombre }}</td>
                                <td>
                                    {{ optional($precarga->tipotransaccion_compras)->abreviatura }}
                                    {{ $precarga->letra }} {{ $precarga->sucursal }}-{{ $precarga->numerocomprobante }}
                                </td>
                                <td>{{ $precarga->fechafactura }}</td>
                                <td>{{ $precarga->numeroordencompra }}</td>
                                <td class="text-right">
                                    {{ $precarga->moneda ?: 'PESOS' }}
                                    {{ number_format((float) $precarga->total, 2, ',', '.') }}
                                </td>
                                <td>
                                    @if (!empty($precarga->pararevisar))
                                    <span class="badge badge-warning">Para revisar</span>
                                    @else
                                    <span class="badge badge-info">{{ $precarga->estado }}</span>
                                    @endif
                                </td>
                                <td>
                                    <small>{{ \App\Support\Compras\PrecargaComprobanteOrigenEntrada::etiqueta($precarga->origen_entrada) }}</small>
                                </td>
                                <td>{{ optional($precarga->created_at)->format('d/m/Y H:i') }}</td>
                                <td class="text-nowrap">
                                    @if (filled($precarga->rutaalmacenamiento))
                                    <a href="{{ route('portal_proveedores_factura', ['id' => $precarga->id, 'proveedor_id' => $proveedorId]) }}"
                                       class="btn-accion-tabla tooltipsC"
                                       title="Ver factura PDF"
                                       target="_blank"
                                       rel="noopener">
                                        <i class="fa fa-file-pdf-o text-danger"></i>
                                    </a>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10">
                                    <div class="portal-empty">
                                        <i class="fa fa-file-text-o"></i>
                                        El proveedor todavía no presentó facturas.
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($precargas)
                    <div class="mt-2">
                        {{ $precargas->links() }}
                    </div>
                @endif
            </div>
        </div>

        @if (!empty($pdfIaHabilitado) && can('cargar-portal-proveedores', false))
            @include('compras.precarga_comprobante_proveedor.partials.modal_pdf_ia', [
                'pdfIaPreviewUrl' => route('portal_proveedores_pdf_ia_preview'),
                'pdfIaResolverOcUrl' => route('portal_proveedores_pdf_ia_resolver_oc'),
                'pdfIaConfirmarUrl' => route('portal_proveedores_pdf_ia_confirmar'),
                'pdfIaProveedorIdSelector' => '#proveedor_id',
                'pdfIaOverlayId' => 'portal-proveedor-proceso-overlay',
            ])
            @include('includes.proceso_overlay_aviso', [
                'overlayId' => 'portal-proveedor-proceso-overlay',
                'tituloId' => 'portal-proveedor-proceso-titulo',
                'subtituloId' => 'portal-proveedor-proceso-subtitulo',
                'titulo' => 'Analizando factura…',
                'subtitulo' => 'El OCR y la validación pueden demorar. No cierre la página.',
            ])
        @endif
        @endif

        @include('includes.compras.modalconsultaproveedor')
    </div>
</div>
@endsection
