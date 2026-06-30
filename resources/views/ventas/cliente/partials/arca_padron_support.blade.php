<div
    id="cliente-arca-config"
    class="d-none"
    aria-hidden="true"
    data-arca-constancia-url="{{ route('arca_constancia_inscripcion') }}"
    data-arca-validar-impuestos="{{ filter_var(config('arca.padron_validacion_cliente.habilitado', true), FILTER_VALIDATE_BOOLEAN) ? '1' : '0' }}"
    data-condicioniva-ri-id="{{ (int) config('arca.padron_validacion_cliente.condicioniva_responsable_inscripto_id', 1) }}"
    data-condicioniva-monotributo-id="{{ (int) config('arca.padron_validacion_cliente.condicioniva_monotributo_id', 4) }}"
    data-verificar-documento-url="{{ route('verificar_cliente_documento_alta') }}"
></div>
@include('compras.proveedor.arca-padron-modals')
