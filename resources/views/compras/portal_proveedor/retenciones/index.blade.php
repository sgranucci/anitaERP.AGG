@extends("theme.$theme.layout")

@section('titulo')
    Portal de Proveedores — Retenciones
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
            <strong>Portal de Proveedores — Retenciones.</strong>
            Consulte y descargue certificados de Ganancias, IVA, SUSS e IIBB asociados a sus pagos.
        </div>

        @include('compras.portal_proveedor.partials.selector_cuenta', [
            'rutaSelector' => route('portal_proveedores_retenciones'),
            'proveedor' => $proveedor,
            'proveedorId' => $proveedorId,
        ])

        @if ($proveedor)
        <div class="card">
            @include('compras.portal_proveedor.partials.cabecera_proveedor', [
                'proveedor' => $proveedor,
                'moduloActivo' => 'retenciones',
            ])
            <div class="card-body">
                @include('compras.portal_proveedor.partials.nav_modulos', [
                    'moduloActivo' => 'retenciones',
                    'proveedorId' => $proveedorId,
                ])

                @include('compras.portal_proveedor.partials.kpis_pagos', ['resumen' => $resumen])

                @include('compras.portal_proveedor.retenciones.partials.filtros', [
                    'filtros' => $filtros,
                    'proveedorId' => $proveedorId,
                    'empresa_query' => $empresa_query,
                ])

                <div class="mb-2">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_portal_proveedores_retenciones',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                </div>

                <div class="table-responsive p-0">
                    @include('compras.portal_proveedor.retenciones.partials.tabla_datos', [
                        'retenciones' => $retenciones,
                        'proveedorId' => $proveedorId,
                        'puedeVerDetalle' => true,
                    ])
                </div>

                @if ($retenciones)
                    <div class="mt-2 d-flex justify-content-between align-items-center flex-wrap">
                        <small class="text-muted">
                            @if ($retenciones->total() > 0)
                                Mostrando {{ $retenciones->firstItem() }}–{{ $retenciones->lastItem() }} de {{ $retenciones->total() }}
                            @endif
                        </small>
                        {{ $retenciones->links() }}
                    </div>
                @endif
            </div>
        </div>
        @endif

        @include('includes.compras.modalconsultaproveedor')
    </div>
</div>
@endsection
