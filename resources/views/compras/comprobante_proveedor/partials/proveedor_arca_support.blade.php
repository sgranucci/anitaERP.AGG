<div
    id="cp-proveedor-arca-config"
    class="d-none"
    aria-hidden="true"
    data-arca-validar-impuestos="{{ filter_var(config('arca.padron_validacion_proveedor.habilitado', true), FILTER_VALIDATE_BOOLEAN) ? '1' : '0' }}"
    data-arca-validar-url="{{ route('comprobante_proveedor_validar_proveedor_arca') }}"
    data-arca-constancia-url="{{ route('arca_constancia_inscripcion') }}"
    data-condicioniva-ri-id="{{ (int) config('arca.padron_validacion_proveedor.condicioniva_responsable_inscripto_id', 1) }}"
    data-condicioniva-monotributo-id="{{ (int) config('arca.padron_validacion_proveedor.condicioniva_monotributo_id', 4) }}"
    data-suspender-en-abm="0"
    data-url-editar-proveedor="{{ url('compras/proveedor/__ID__/editar') }}"
></div>
