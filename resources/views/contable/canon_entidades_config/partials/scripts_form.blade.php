<script>
window.CANON_CONFIG = {
    sectores: @json($sectores_sp ?? []),
    formasPago: @json($formas_pago_sp ?? []),
    conceptos: @json($conceptos_sp ?? [])
};
</script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/moneda/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/configuracion/moneda/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/centrocosto/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cuentacontable/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/contable/canon_municipal_config/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/canon_municipal_config/form.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/contable/canon_entidades_config/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/canon_entidades_config/form.js')) ?: time() }}"></script>
