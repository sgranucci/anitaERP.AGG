@extends("theme.$theme.layout")
@section('titulo')
    Comprobantes de proveedor
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/arca-padron-validacion-async.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/arca-apoc-validacion-async.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/conceptos_ivacompra_coherencia.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/comprobante_proveedor/formulario.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('compras.comprobante_proveedor.partials.formulario_card', ['esEdicion' => true])
@endsection
