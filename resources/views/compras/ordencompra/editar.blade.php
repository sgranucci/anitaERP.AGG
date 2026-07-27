@extends(!empty($acceso_visualizacion_por_hash) ? 'layouts.requisicion-visualizar-hash' : "theme.$theme.layout")
@section('titulo')
Órdenes de compra
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/presupuesto/partidagasto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/presupuesto/capex/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/articulo_proveedor/operativo.js') }}" type="text/javascript"></script>
<script>
window.msColoresOpciones = @json(($color_query ?? collect())->map(fn ($c) => ['id' => (int) $c->id, 'nombre' => $c->nombre])->values());
window.msTallesOpciones = @json(($talle_query ?? collect())->map(fn ($t) => ['id' => (int) $t->id, 'nombre' => $t->nombre])->values());
</script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/lineas.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/form-color-talle.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/arbolaprobacion/panel_ia.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/formulario.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/enviar-proveedor.js') }}" type="text/javascript"></script>
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
<div class="row" id="ordencompra-editar-root">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')

        @include('compras.ordencompra.partials.modal_enviar_proveedor')

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
                        <form method="POST" action="{{ route('ordencompra_cambiar_sector', ['id' => $data->id]) }}">
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
                                <div class="form-group">
                                    <label for="oc_sector_obs">Observación</label>
                                    <input type="text" name="observacion" id="oc_sector_obs" class="form-control" maxlength="255" placeholder="Motivo del traslado">
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
        @endif

        <div class="card card-danger">
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
                <div class="card-tools">
                    @if (empty($acceso_visualizacion_por_hash) && empty($ocultarVolver))
                        <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                    @if (isset($data) && $data && (can('listar-ordencompra', false) || can('editar-ordencompra', false)))
                        <a href="{{ route('imprimir_pdf_ordencompra', ['id' => $data->id]) }}" class="btn btn-primary btn-sm" title="Descargar PDF de la orden de compra (Legal vertical)" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-file-pdf"></i> Imprimir orden (PDF)
                        </a>
                        <a href="{{ route('imprimir_pdf_ordencompra', ['id' => $data->id, 'formato' => 'apaisado']) }}" class="btn btn-outline-primary btn-sm" title="PDF en Legal apaisado (todas las columnas de ítems)" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-file-pdf"></i> PDF apaisado
                        </a>
                    @endif
                    @if (isset($data) && $data && can('editar-ordencompra', false) && !empty($oc_datos_envio_proveedor['puede_enviar']))
                        <button type="button" class="btn btn-success btn-sm js-oc-enviar-proveedor" data-ordencompra-id="{{ $data->id }}" title="Enviar PDF de la OC al email del proveedor">
                            <i class="fa fa-envelope"></i> Enviar al proveedor
                        </button>
                    @endif
                    @if (isset($data) && $data && can('crear-comprobante-proveedor', false))
                        <a href="{{ route('crear_comprobante_proveedor', ['ordencompra_id' => $data->id]) }}" class="btn btn-outline-success btn-sm" title="Alta de comprobante de proveedor vinculado a esta OC">
                            <i class="fa fa-file-text-o"></i> Facturar proveedor
                        </a>
                    @endif
                    @if (isset($data) && $data && empty($visualizar))
                        @if (can('actualizar-ordencompra', false))
                            <button type="button" class="btn btn-outline-light btn-sm" data-toggle="modal" data-target="#modalOcCambiarEstado">
                                <i class="fa fa-random"></i> Cambiar estado
                            </button>
                            <button type="button" class="btn btn-outline-light btn-sm" data-toggle="modal" data-target="#modalOcCambiarSector">
                                <i class="fa fa-folder-open"></i> Cambiar sector
                            </button>
                        @endif
                        @if (!empty($data->requisicion_id) && (can('editar-requisicion', false) || can('listar-requisicion', false)))
                            <a href="{{ route('editar_requisicion', ['id' => $data->requisicion_id]) }}" class="btn btn-outline-warning btn-sm" target="_blank" rel="noopener noreferrer" title="Abre la requisición que originó esta OC">
                                <i class="fa fa-link"></i> Ver requisición
                            </a>
                        @endif
                        @if (($data->estadoordencompra ?? '') === \App\Support\Compras\OrdencompraEstados::SUSPENDIDA && can('actualizar-ordencompra', false))
                            <form action="{{ route('ordencompra_reactivar', ['id' => $data->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Pasar la orden de compra de SUSPENDIDA a PENDIENTE?');">
                                @csrf
                                <button type="submit" class="btn btn-warning btn-sm">Reactivar a pendiente</button>
                            </form>
                        @endif
                        @if (!empty($oc_revertir_cierre_lineas['puede_revertir']) && can('actualizar-ordencompra', false))
                            <form action="{{ route('ordencompra_revertir_cierre_lineas', ['id' => $data->id]) }}" method="POST" class="d-inline"
                                onsubmit="return confirm('¿Reabrir {{ count($oc_revertir_cierre_lineas['lineas'] ?? []) }} línea(s) cerrada(s) por error?\n\nSaldo pendiente de recepción: {{ number_format((float) ($oc_revertir_cierre_lineas['cantidad_pendiente_total'] ?? 0), 2, ',', '.') }}\n\nLa OC volverá a APROBADA si corresponde según recepciones confirmadas.');">
                                @csrf
                                <button type="submit" class="btn btn-warning btn-sm" title="Reabre líneas cerradas por error en recepción y recalcula el saldo pendiente">
                                    <i class="fa fa-undo"></i> Revertir cierre de líneas
                                </button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>

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

                <div class="text-center py-2 border-bottom rounded-top bg-white">
                    <button type="button" id="oc-boton-principal" class="btn btn-primary btn-sm mx-1 oc-tab-solapa font-weight-bold">Datos principales</button>
                    <button type="button" id="oc-boton-articulos" class="btn btn-info btn-sm mx-1 oc-tab-solapa">Artículos</button>
                    <button type="button" id="oc-boton-comprobantes" class="btn btn-info btn-sm mx-1 oc-tab-solapa">Comprobantes a venir</button>
                    <button type="button" id="oc-boton-archivos" class="btn btn-info btn-sm mx-1 oc-tab-solapa">
                        <span class="fa fa-paperclip"></span> Archivos asociados
                    </button>
                    @if (isset($data) && $data)
                        <button type="button" id="oc-boton-historia-legajo" class="btn btn-info btn-sm mx-1 oc-tab-solapa">Historia legajo</button>
                        <button type="button" id="oc-boton-historia-estados" class="btn btn-info btn-sm mx-1 oc-tab-solapa">Historia estados</button>
                        <button type="button" id="oc-boton-recepciones" class="btn btn-info btn-sm mx-1 oc-tab-solapa">
                            <span class="fa fa-truck"></span> Recepciones
                        </button>
                        <button type="button" id="oc-boton-historia-precios" class="btn btn-info btn-sm mx-1 oc-tab-solapa">
                            <span class="fa fa-history"></span> Historia precios
                        </button>
                        <button type="button" id="oc-boton-arbol" class="btn btn-info btn-sm mx-1 oc-tab-solapa">Árbol aprobación</button>
                    @endif
                </div>

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
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-save"></i>
                            {{ isset($data) && $data ? 'Actualizar' : 'Guardar' }}
                        </button>
                        @if (!empty($soloConsulta))
                        <button type="button" class="btn btn-secondary ml-2" onclick="window.close()">Cerrar solapa</button>
                        @endif
                    </div>
                @elseif (!empty($soloConsulta))
                    <div class="card-footer text-center">
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
