@extends("theme.$theme.layout")

@section('titulo')
    Portal de Proveedores — Pagos
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
            <strong>Portal de Proveedores — Pagos.</strong>
            Consulte órdenes de pago, descargue el comprobante PDF y los certificados de retención.
            En la versión externa el proveedor se obtendrá de la sesión autenticada.
        </div>

        @include('compras.portal_proveedor.partials.selector_cuenta', [
            'rutaSelector' => route('portal_proveedores_pagos'),
            'proveedor' => $proveedor,
            'proveedorId' => $proveedorId,
        ])

        @if ($proveedor)
        <div class="card">
            @include('compras.portal_proveedor.partials.cabecera_proveedor', [
                'proveedor' => $proveedor,
                'moduloActivo' => 'pagos',
            ])
            <div class="card-body">
                @include('compras.portal_proveedor.partials.nav_modulos', [
                    'moduloActivo' => 'pagos',
                    'proveedorId' => $proveedorId,
                ])

                @include('compras.portal_proveedor.partials.kpis_pagos', ['resumen' => $resumen])

                @include('compras.portal_proveedor.pagos.partials.filtros', [
                    'filtros' => $filtros,
                    'proveedorId' => $proveedorId,
                    'empresa_query' => $empresa_query,
                ])

                <div class="mb-2">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_portal_proveedores_pagos',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                </div>

                <div class="table-responsive p-0">
                    @include('compras.portal_proveedor.pagos.partials.tabla_datos', [
                        'pagos' => $pagos,
                        'proveedorId' => $proveedorId,
                        'puedeVerDetalle' => true,
                    ])
                </div>

                @if ($pagos)
                    <div class="mt-2 d-flex justify-content-between align-items-center flex-wrap">
                        <small class="text-muted">
                            @if ($pagos->total() > 0)
                                Mostrando {{ $pagos->firstItem() }}–{{ $pagos->lastItem() }} de {{ $pagos->total() }}
                            @endif
                        </small>
                        {{ $pagos->links() }}
                    </div>
                @endif
            </div>
        </div>
        @endif

        @include('includes.compras.modalconsultaproveedor')
    </div>
</div>
@endsection
