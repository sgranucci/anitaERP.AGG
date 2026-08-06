@extends("theme.$theme.layout")

@section('titulo')
    Portal de Proveedores — Órdenes de compra
@endsection

@section('styles')
    @include('compras.portal_proveedor.partials.estilos')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="alert alert-info">
            <strong>Portal de Proveedores — Órdenes de compra.</strong>
            Consulte las OC activas o pendientes, el seguimiento de facturas asociadas y los pagos vinculados.
            En la versión externa el proveedor se obtendrá de la sesión autenticada.
        </div>

        @include('compras.portal_proveedor.partials.selector_cuenta', [
            'rutaSelector' => route('portal_proveedores_ordenes'),
            'proveedor' => $proveedor,
            'proveedorId' => $proveedorId,
        ])

        @if ($proveedor)
        <div class="card">
            @include('compras.portal_proveedor.partials.cabecera_proveedor', [
                'proveedor' => $proveedor,
                'moduloActivo' => 'ordenes',
            ])
            <div class="card-body">
                @include('compras.portal_proveedor.partials.nav_modulos', [
                    'moduloActivo' => 'ordenes',
                    'proveedorId' => $proveedorId,
                ])

                @include('compras.portal_proveedor.partials.kpis_ordenes', ['resumen' => $resumen])

                @include('compras.portal_proveedor.ordenes.partials.filtros', [
                    'filtros' => $filtros,
                    'proveedorId' => $proveedorId,
                    'empresa_query' => $empresa_query,
                ])

                <div class="mb-2">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_portal_proveedores_ordenes',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                </div>

                <div class="table-responsive p-0">
                    @include('compras.portal_proveedor.ordenes.partials.tabla_datos', [
                        'ordenes' => $ordenes,
                        'proveedorId' => $proveedorId,
                        'puedeVerDetalle' => true,
                    ])
                </div>

                @if ($ordenes)
                    <div class="mt-2 d-flex justify-content-between align-items-center flex-wrap">
                        <small class="text-muted">
                            @if ($ordenes->total() > 0)
                                Mostrando {{ $ordenes->firstItem() }}–{{ $ordenes->lastItem() }} de {{ $ordenes->total() }}
                            @endif
                        </small>
                        {{ $ordenes->links() }}
                    </div>
                @endif
            </div>
        </div>
        @endif

        @include('includes.compras.modalconsultaproveedor')
    </div>
</div>
@endsection
