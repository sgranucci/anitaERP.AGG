@php
    $hayCircuito = isset($data) && $data && empty($visualizar) && (
        can('actualizar-ordencompra', false)
        || !empty($oc_puede_enviar_pagos)
        || !empty($oc_puede_devolver_cxp)
        || !empty($oc_puede_devolver_compras)
        || !empty($oc_puede_finalizar_legajo)
        || (($data->estadoordencompra ?? '') === \App\Support\Compras\OrdencompraEstados::SUSPENDIDA && can('actualizar-ordencompra', false))
        || (!empty($oc_revertir_cierre_lineas['puede_revertir']) && can('actualizar-ordencompra', false))
    );
    $hayMas = isset($data) && $data && (
        can('crear-comprobante-proveedor', false)
        || (can('crear-ingreso-proveedor', false) && !empty($mostrar_solapa_ingresos))
        || (empty($visualizar) && can('actualizar-ordencompra', false) && !empty($data->proveedor_id))
        || (!empty($data->requisicion_id) && (can('editar-requisicion', false) || can('listar-requisicion', false)))
    );
@endphp
<div class="card-tools oc-form-toolbar d-flex flex-wrap align-items-center justify-content-end">
    @if (empty($acceso_visualizacion_por_hash) && empty($ocultarVolver))
        <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm mr-1">
            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
        </a>
    @endif
    @if (isset($data) && $data && (can('listar-ordencompra', false) || can('editar-ordencompra', false)))
        <a href="{{ route('imprimir_pdf_ordencompra', ['id' => $data->id]) }}" class="btn btn-primary btn-sm mr-1" title="Descargar PDF de la orden de compra (Legal vertical)" target="_blank" rel="noopener noreferrer">
            <i class="fas fa-file-pdf"></i> PDF
        </a>
        <a href="{{ route('imprimir_pdf_ordencompra', ['id' => $data->id, 'formato' => 'apaisado']) }}" class="btn btn-outline-light btn-sm mr-1" title="PDF en Legal apaisado" target="_blank" rel="noopener noreferrer">
            <i class="fas fa-file-pdf"></i> Apaisado
        </a>
    @endif
    @if (isset($data) && $data && can('editar-ordencompra', false) && !empty($oc_datos_envio_proveedor['puede_enviar']))
        <button type="button" class="btn btn-success btn-sm mr-1 js-oc-enviar-proveedor" data-ordencompra-id="{{ $data->id }}" title="Enviar PDF de la OC al email del proveedor">
            <i class="fa fa-envelope"></i> Enviar al proveedor
        </button>
    @endif

    @if ($hayCircuito)
        <div class="btn-group mr-1">
            <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fa fa-random"></i> Circuito
            </button>
            <div class="dropdown-menu dropdown-menu-right">
                @if (can('actualizar-ordencompra', false))
                    <button type="button" class="dropdown-item" data-toggle="modal" data-target="#modalOcCambiarEstado">
                        <i class="fa fa-random"></i> Cambiar estado
                    </button>
                    <button type="button" class="dropdown-item" data-toggle="modal" data-target="#modalOcCambiarSector">
                        <i class="fa fa-folder-open"></i> Cambiar sector
                    </button>
                    @if (!empty($oc_puede_enviar_gastronomia))
                        <button type="button" class="dropdown-item" data-toggle="modal" data-target="#modalOcEnviarGastronomia">
                            <i class="fa fa-cutlery"></i> Enviar a Gastronomía
                        </button>
                    @endif
                    @if (!empty($oc_puede_enviar_cuentas_a_pagar))
                        <button type="button" class="dropdown-item" data-toggle="modal" data-target="#modalOcEnviarCuentasAPagar">
                            <i class="fa fa-share"></i> Enviar a Cuentas a pagar
                        </button>
                    @endif
                @endif
                @if (!empty($oc_puede_enviar_pagos))
                    <button type="button" class="dropdown-item" data-toggle="modal" data-target="#modalOcEnviarPagos">
                        <i class="fa fa-share-square-o"></i> Enviar a Pagos
                    </button>
                @endif
                @if (!empty($oc_puede_devolver_cxp) || !empty($oc_puede_devolver_compras) || !empty($oc_puede_finalizar_legajo))
                    <div class="dropdown-divider"></div>
                @endif
                @if (!empty($oc_puede_devolver_cxp))
                    <button type="button" class="dropdown-item" data-toggle="modal" data-target="#modalOcDevolverCxp">
                        <i class="fa fa-undo"></i> Devolver a Cuentas a pagar
                    </button>
                @endif
                @if (!empty($oc_puede_devolver_compras))
                    <button type="button" class="dropdown-item" data-toggle="modal" data-target="#modalOcDevolverCompras">
                        <i class="fa fa-reply"></i> Devolver a Compras
                    </button>
                @endif
                @if (!empty($oc_puede_finalizar_legajo))
                    <button type="button" class="dropdown-item" data-toggle="modal" data-target="#modalOcFinalizarLegajo">
                        <i class="fa fa-check"></i> Finalizar legajo
                    </button>
                @endif
                @if (($data->estadoordencompra ?? '') === \App\Support\Compras\OrdencompraEstados::SUSPENDIDA && can('actualizar-ordencompra', false))
                    <div class="dropdown-divider"></div>
                    <form action="{{ route('ordencompra_reactivar', ['id' => $data->id]) }}" method="POST" class="px-0" onsubmit="return confirm('¿Pasar la orden de compra de SUSPENDIDA a PENDIENTE?');">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="fa fa-undo"></i> Reactivar a pendiente
                        </button>
                    </form>
                @endif
                @if (!empty($oc_revertir_cierre_lineas['puede_revertir']) && can('actualizar-ordencompra', false))
                    <form action="{{ route('ordencompra_revertir_cierre_lineas', ['id' => $data->id]) }}" method="POST" class="px-0"
                        onsubmit="return confirm('¿Reabrir {{ count($oc_revertir_cierre_lineas['lineas'] ?? []) }} línea(s) cerrada(s) por error?\n\nSaldo pendiente de recepción: {{ number_format((float) ($oc_revertir_cierre_lineas['cantidad_pendiente_total'] ?? 0), 2, ',', '.') }}\n\nLa OC volverá a APROBADA si corresponde según recepciones confirmadas.');">
                        @csrf
                        <button type="submit" class="dropdown-item" title="Reabre líneas cerradas por error en recepción y recalcula el saldo pendiente">
                            <i class="fa fa-undo"></i> Revertir cierre de líneas
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    @if ($hayMas)
        <div class="btn-group">
            <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fa fa-ellipsis-h"></i> Más
            </button>
            <div class="dropdown-menu dropdown-menu-right">
                @if (can('crear-comprobante-proveedor', false))
                    <a href="{{ route('crear_comprobante_proveedor', ['ordencompra_id' => $data->id, 'origen' => 'oc']) }}" class="dropdown-item" title="Alta de comprobante de proveedor vinculado a esta OC">
                        <i class="fa fa-file-text-o"></i> Facturar proveedor
                    </a>
                @endif
                @if (can('crear-ingreso-proveedor', false) && !empty($mostrar_solapa_ingresos))
                    <button type="button" class="dropdown-item js-ingreso-ticket-nuevo" title="Solicitar ticket de ingreso a planta">
                        <i class="fa fa-id-badge"></i> Ticket de ingreso
                    </button>
                @endif
                @if (empty($visualizar) && can('actualizar-ordencompra', false) && !empty($data->proveedor_id))
                    <button type="button" class="dropdown-item js-oc-asignar-factura"
                            data-url="{{ route('ordencompra_asignar_factura_pdf', ['id' => $data->id]) }}"
                            data-numero="{{ $data->numeroordencompra }}"
                            data-proveedor="{{ $data->proveedores->nombre ?? '' }}">
                        <i class="fa fa-file-pdf-o"></i> Asignar factura PDF
                    </button>
                @endif
                @if (!empty($data->requisicion_id) && (can('editar-requisicion', false) || can('listar-requisicion', false)))
                    <a href="{{ route('editar_requisicion', ['id' => $data->requisicion_id]) }}" class="dropdown-item" target="_blank" rel="noopener noreferrer" title="Abre la requisición que originó esta OC">
                        <i class="fa fa-link"></i> Ver requisición
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
