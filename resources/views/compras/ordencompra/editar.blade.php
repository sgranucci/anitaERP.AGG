@extends(!empty($acceso_visualizacion_por_hash) ? 'layouts.requisicion-visualizar-hash' : "theme.$theme.layout")
@section('titulo')
Órdenes de compra
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/compras/ordencompra-ui.css') }}?v={{ @filemtime(public_path('assets/css/compras/ordencompra-ui.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/pages/css/compras/ordencompra/asignar_factura_legajo.css') }}?v={{ @filemtime(public_path('assets/pages/css/compras/ordencompra/asignar_factura_legajo.css')) ?: time() }}">
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/presupuesto/partidagasto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/presupuesto/capex/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cuentacontable/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/centrocosto/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/articulo_proveedor/operativo.js') }}" type="text/javascript"></script>
<script>
window.msColoresOpciones = @json(($color_query ?? collect())->map(fn ($c) => ['id' => (int) $c->id, 'nombre' => $c->nombre])->values());
window.msTallesOpciones = @json(($talle_query ?? collect())->map(fn ($t) => ['id' => (int) $t->id, 'nombre' => $t->nombre])->values());
</script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/lineas.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/form-color-talle.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/arbolaprobacion/panel_ia.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/formulario.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/formulario.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/enviar-proveedor.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/cambiar_sector_legajo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/cambiar_sector_legajo.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/enviar_gastronomia_firmante.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/enviar_gastronomia_firmante.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/asignar_factura_legajo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/asignar_factura_legajo.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/seguridad/ingreso_proveedor/modal.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/seguridad/ingreso_proveedor/modal.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/seguridad/ingreso_proveedor/autorizar.js') }}" type="text/javascript"></script>
<script>
$(function () {
    if (window.OcCambiarSectorLegajo) {
        window.OcCambiarSectorLegajo.initForm($('#formOcCambiarSector'));
        if ($('#formOcEnviarGastronomia').length) {
            window.OcCambiarSectorLegajo.initForm($('#formOcEnviarGastronomia'), { forzarPaquete: true });
        }
        if ($('#formOcEnviarCuentasAPagar').length) {
            window.OcCambiarSectorLegajo.initForm($('#formOcEnviarCuentasAPagar'), { forzarCxp: true });
        }
    }
});
</script>
@if (!empty($sugerir_envio_oc) && (int) $sugerir_envio_oc === (int) ($data->id ?? 0))
<script>
    window.ocSugerirEnvioProveedor = { ordencompra_id: {{ (int) $data->id }} };
</script>
@endif
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('consultar_ordencompra', $filtrosQuery ?? []);
    $formRouteParams = isset($data) && $data
        ? ['id' => $data->id] + ($filtrosQuery ?? [])
        : ($filtrosQuery ?? []);
@endphp
<div class="row oc-ui" id="ordencompra-editar-root">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')

        @include('compras.ordencompra.partials.modal_enviar_proveedor')
        @include('compras.ordencompra.partials.modal_asignar_factura_legajo')
        @include('compras.ordencompra.partials.modal_firmante_gastronomia_arbol')

        @if (isset($data) && $data && empty($visualizar))
            <div class="modal fade" id="modalOcCambiarEstado" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('ordencompra_cambiar_estado', ['id' => $data->id]) }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Cambiar estado</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for="oc_estado_nuevo">Nuevo estado</label>
                                    <select name="estado" id="oc_estado_nuevo" class="form-control" required>
                                        @foreach ($estados_oc as $est)
                                            <option value="{{ $est }}" {{ ($data->estadoordencompra ?? '') === $est ? 'selected' : '' }}>{{ $est }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="oc_estado_obs">Observación</label>
                                    <textarea name="observacion" id="oc_estado_obs" class="form-control" rows="3" maxlength="2000" placeholder="Opcional"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="modalOcCambiarSector" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('ordencompra_cambiar_sector', ['id' => $data->id]) }}"
                              id="formOcCambiarSector"
                              enctype="multipart/form-data"
                              data-ordencompra-id="{{ $data->id }}"
                              data-sector-gastronomia-id="{{ (int) ($oc_sector_gastronomia_id ?? 0) }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Cambiar sector de legajo</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for="oc_sector_nuevo">Sector</label>
                                    <select name="sector_legajocompra_id" id="oc_sector_nuevo" class="form-control" required>
                                        @foreach ($sectores_legajo as $sec)
                                            <option value="{{ $sec->id }}" {{ (int) ($data->sector_legajocompra_id ?? 0) === (int) $sec->id ? 'selected' : '' }}>{{ $sec->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @include('compras.ordencompra.partials.bloque_factura_legajo_sector', ['prefix' => 'oc'])
                                <div class="form-group">
                                    <label for="oc_sector_obs">Observaci&oacute;n / comentario al &aacute;rbol</label>
                                    <input type="text" name="observacion" id="oc_sector_obs" class="form-control" maxlength="255" placeholder="Motivo del traslado (tambi&eacute;n va al &aacute;rbol si aplica)">
                                </div>
                                <div class="form-group">
                                    <label for="oc_sector_leyenda">Leyenda / detalle</label>
                                    <textarea name="leyenda" id="oc_sector_leyenda" class="form-control" rows="2" maxlength="2000"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Registrar cambio</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if (!empty($oc_puede_enviar_gastronomia))
            <div class="modal fade" id="modalOcEnviarGastronomia" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('ordencompra_enviar_gastronomia', ['id' => $data->id]) }}"
                              id="formOcEnviarGastronomia"
                              enctype="multipart/form-data"
                              data-ordencompra-id="{{ $data->id }}"
                              data-firmantes-url="{{ route('ordencompra_firmantes_gastronomia_arbol', ['id' => $data->id]) }}"
                              data-sector-gastronomia-id="{{ (int) ($oc_sector_gastronomia_id ?? 0) }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Enviar a Gastronomía</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted small">El referente verá la factura, la OC y la recepción. Al autorizar, el legajo pasa a Cuentas a pagar.</p>
                                @include('compras.ordencompra.partials.bloque_factura_legajo_sector', [
                                    'prefix' => 'ocg',
                                    'tituloBloque' => 'Factura y recepción del legajo (igual que para Cuentas a pagar)',
                                ])
                                <div class="form-group">
                                    <label for="ocg_sector_obs">Comentario al referente</label>
                                    <input type="text" name="observacion" id="ocg_sector_obs" class="form-control" maxlength="255" placeholder="Opcional">
                                </div>
                                <div class="form-group">
                                    <label for="ocg_sector_leyenda">Leyenda / detalle</label>
                                    <textarea name="leyenda" id="ocg_sector_leyenda" class="form-control" rows="2" maxlength="2000"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Enviar a Gastronomía</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif
            @if (!empty($oc_puede_enviar_cuentas_a_pagar))
            <div class="modal fade" id="modalOcEnviarCuentasAPagar" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('ordencompra_enviar_cuentas_a_pagar', ['id' => $data->id]) }}"
                              id="formOcEnviarCuentasAPagar"
                              enctype="multipart/form-data"
                              data-ordencompra-id="{{ $data->id }}"
                              data-sector-gastronomia-id="{{ (int) ($oc_sector_gastronomia_id ?? 0) }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Enviar a Cuentas a pagar</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted small">
                                    @if (!empty($oc_puede_enviar_gastronomia))
                                        En Gastronomía (CC 85) primero hay que enviar al referente. Este envío directo a Cuentas a pagar queda bloqueado hasta que autoricen.
                                    @else
                                        Pasa el legajo a Cuentas a pagar. Exige factura. La COM es obligatoria según la configuración de Cuentas a pagar de la empresa, salvo que un contrato vigente indique otra ruta.
                                    @endif
                                </p>
                                @include('compras.ordencompra.partials.bloque_factura_legajo_sector', [
                                    'prefix' => 'ocx',
                                    'tituloBloque' => 'Factura y recepción del legajo',
                                ])
                                <div class="form-group">
                                    <label for="ocx_sector_obs">Observación</label>
                                    <input type="text" name="observacion" id="ocx_sector_obs" class="form-control" maxlength="255" placeholder="Opcional">
                                </div>
                                <div class="form-group">
                                    <label for="ocx_sector_leyenda">Leyenda / detalle</label>
                                    <textarea name="leyenda" id="ocx_sector_leyenda" class="form-control" rows="2" maxlength="2000"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Enviar a Cuentas a pagar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif
            @if (!empty($oc_puede_enviar_pagos))
            <div class="modal fade" id="modalOcEnviarPagos" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('ordencompra_enviar_pagos', ['id' => $data->id]) }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Enviar a Pagos</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted small">Pasa el legajo de Cuentas a pagar a <strong>PAGOS</strong>. Requiere la factura ya cargada.</p>
                                <div class="form-group">
                                    <label for="ocp_obs">Observación</label>
                                    <input type="text" name="observacion" id="ocp_obs" class="form-control" maxlength="255" placeholder="Opcional">
                                </div>
                                <div class="form-group">
                                    <label for="ocp_leyenda">Leyenda / detalle</label>
                                    <textarea name="leyenda" id="ocp_leyenda" class="form-control" rows="2" maxlength="2000"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Enviar a Pagos</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif
            @if (!empty($oc_puede_devolver_cxp))
            <div class="modal fade" id="modalOcDevolverCxp" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('ordencompra_devolver_cuentas_a_pagar', ['id' => $data->id]) }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Devolver a Cuentas a pagar</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted small">Vuelve el legajo de Pagos a <strong>CUENTAS A PAGAR</strong>. El comentario es obligatorio.</p>
                                <div class="form-group">
                                    <label for="oc_dev_cxp_obs">Comentario / motivo</label>
                                    <input type="text" name="observacion" id="oc_dev_cxp_obs" class="form-control" maxlength="255" required>
                                </div>
                                <div class="form-group">
                                    <label for="oc_dev_cxp_leyenda">Detalle</label>
                                    <textarea name="leyenda" id="oc_dev_cxp_leyenda" class="form-control" rows="3" maxlength="2000"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-warning">Devolver a Cuentas a pagar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif
            @if (!empty($oc_puede_devolver_compras))
            <div class="modal fade" id="modalOcDevolverCompras" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('ordencompra_devolver_compras', ['id' => $data->id]) }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Devolver a Compras</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted small">Vuelve el legajo de Cuentas a pagar a <strong>COMPRAS</strong>. El comentario es obligatorio.</p>
                                <div class="form-group">
                                    <label for="oc_dev_com_obs">Comentario / motivo</label>
                                    <input type="text" name="observacion" id="oc_dev_com_obs" class="form-control" maxlength="255" required>
                                </div>
                                <div class="form-group">
                                    <label for="oc_dev_com_leyenda">Detalle</label>
                                    <textarea name="leyenda" id="oc_dev_com_leyenda" class="form-control" rows="3" maxlength="2000"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-warning">Devolver a Compras</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif
            @if (!empty($oc_puede_finalizar_legajo))
            <div class="modal fade" id="modalOcFinalizarLegajo" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('ordencompra_finalizar_legajo', ['id' => $data->id]) }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Finalizar legajo</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted small">Pasa el legajo de Pagos a <strong>FINALIZADO</strong>. Queda en el histórico.</p>
                                <div class="form-group">
                                    <label for="ocf_obs">Observación</label>
                                    <input type="text" name="observacion" id="ocf_obs" class="form-control" maxlength="255" placeholder="Opcional">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Finalizar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif
        @endif

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    @if (isset($data) && $data)
                        Orden de compra {{ $data->numeroordencompra }}
                    @else
                        Nueva orden de compra
                    @endif
                    @if (!empty($wizardRequisicionId))
                        <span class="badge badge-info ml-2">Desde requisición #{{ (int) $wizardRequisicionId }} — múltiples OC</span>
                    @endif
                </h3>
                @include('compras.ordencompra.partials.toolbar_acciones')
            </div>

            @include('compras.ordencompra.partials.identidad')

            <form action="{{ isset($data) && $data ? route('actualizar_ordencompra', $formRouteParams) : route('guardar_ordencompra', $filtrosQuery ?? []) }}"
                method="POST" id="form-ordencompra-general" class="form-horizontal form--label-right" enctype="multipart/form-data" autocomplete="off" novalidate>
                @csrf
                @if (isset($data) && $data)
                    @method('PUT')
                @endif
                @if (!empty($soloConsulta))
                    <input type="hidden" name="origen" value="modal_consulta">
                    <input type="hidden" name="vista" value="consulta">
                @endif

                @include('compras.ordencompra.partials.tabs_header')

                <div class="card-body">
                    @if (!empty($wizardRequisicionId))
                        <div class="alert alert-info">
                            <strong>Generación múltiple:</strong> en la solapa <em>Artículos</em> elija el <strong>origen del precio</strong> en cada ítem. Se creará una orden de compra por cada combinación distinta de proveedor y condiciones de compra/entrega. En <em>Comprobantes a venir</em> cargue los comprobantes; la misma definición se aplicará a cada OC generada. Puede dejar ítems sin precio: se cerrarán en la requisición con leyenda en la columna de cierre.
                            <a href="{{ route('solo_consulta_requisicion', ['id' => (int) $wizardRequisicionId]) }}" class="alert-link">Volver a la requisición</a>
                        </div>
                    @endif
                    @include('compras.ordencompra.form')
                </div>

                @if (empty($visualizar))
                    <div class="card-footer oc-form-footer">
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-save"></i>
                            {{ isset($data) && $data ? 'Actualizar' : 'Guardar' }}
                        </button>
                        @if (!empty($soloConsulta))
                        <button type="button" class="btn btn-secondary ml-2" onclick="window.close()">Cerrar solapa</button>
                        @endif
                    </div>
                @elseif (!empty($soloConsulta))
                    <div class="card-footer oc-form-footer text-center">
                        <button type="button" class="btn btn-secondary" onclick="window.close()">Cerrar solapa</button>
                    </div>
                @endif
            </form>
            @include('compras.ordencompra.form_modales_y_json')
            @if (!empty($wizardRequisicionId))
                @php
                    $wizardRequisicionMetaJson = json_encode([
                        'requisicion_id' => (int) $wizardRequisicionId,
                        'post_url' => urlAppDesdeRoute('requisicion_generar_multiples_oc', ['id' => (int) $wizardRequisicionId]),
                        'csrf' => csrf_token(),
                        'puede_enviar_proveedor' => can('editar-ordencompra', false),
                        'volver_url' => urlAppDesdeRoute('solo_consulta_requisicion', ['id' => (int) $wizardRequisicionId]),
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
                @endphp
                <script type="application/json" id="wizard-requisicion-multiples-meta">{!! $wizardRequisicionMetaJson !!}</script>
                <script src="{{ asset('assets/pages/scripts/compras/requisicion/wizard-multiples-oc.js') }}" type="text/javascript"></script>
            @endif
        </div>
    </div>
</div>
@endsection
