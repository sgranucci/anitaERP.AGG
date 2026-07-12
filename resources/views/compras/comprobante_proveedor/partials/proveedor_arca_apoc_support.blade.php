<div
    id="cp-proveedor-arca-apoc-config"
    class="d-none"
    aria-hidden="true"
    data-arca-apoc-habilitado="{{ filter_var(config('arca_wsapoc.validar_comprobante_proveedor', true), FILTER_VALIDATE_BOOLEAN) && filter_var(config('arca_wsapoc.habilitado', true), FILTER_VALIDATE_BOOLEAN) ? '1' : '0' }}"
    data-arca-apoc-validar-url="{{ route('comprobante_proveedor_validar_proveedor_arca_apoc') }}"
    data-suspender-en-abm="0"
></div>
