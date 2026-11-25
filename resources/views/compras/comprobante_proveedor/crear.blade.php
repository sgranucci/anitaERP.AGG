@extends("theme.$theme.layout")
@section('titulo')
    Comprobantes de proveedor
@endsection

@section("scripts")
@include('includes.contable.asiento_montos_formato_js')
<script src="{{ asset('assets/pages/scripts/compras/arca-padron-validacion-async.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/arca-padron-validacion-async.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/arca-apoc-validacion-async.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/arca-apoc-validacion-async.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor_servicio_selector.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor_servicio_selector.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/concepto_ivacompra/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/concepto_ivacompra/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/tipotransaccion_compra/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/tipotransaccion_compra/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cuentacontable/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/conceptos_ivacompra_coherencia.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/comprobante_proveedor/formulario.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/comprobante_proveedor/formulario.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/provincia/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/configuracion/provincia/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/importacion/consulta_carpeta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/importacion/consulta_carpeta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/comprobante_proveedor/importacion.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/comprobante_proveedor/importacion.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('compras.comprobante_proveedor.partials.formulario_card', ['esEdicion' => false])
@endsection
