@extends("theme.$theme.layout")
@section('titulo')
    Nueva solicitud de logística
@endsection
@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/logistica-portal.css') }}?v={{ filemtime(public_path('assets/css/logistica-portal.css')) }}">
@endsection
@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}"></script>
<script>
window.LOGISTICA_CATALOGO = @json($catalogo);
window.LOGISTICA_TRABAJOS = @json($trabajos);
window.LOGISTICA_RESOLVER_OC = @json(route('resolver_ordencompra_logistica'));
</script>
<script src="{{ asset('assets/pages/scripts/logistica/solicitud.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/logistica/solicitud.js')) ?: time() }}"></script>
@endsection
@php
    $tieneInsumos = collect($catalogo['tipos'])->contains(fn ($tipo) => ($tipo['codigo'] ?? '') === 'insumos');
    $inicial = $tieneInsumos ? 'insumos' : 'trabajos';
@endphp
@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-primary log-portal">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-truck"></i> Nueva solicitud</h3>
                <div class="card-tools">
                    <a href="{{ route('logistica_solicitud') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="log-split">
                    <aside class="log-rail">
                        <div class="log-rail-kicker">Qué solicitás</div>
                        @foreach ($catalogo['tipos'] as $tipo)
                            <button type="button" class="log-tile log-naturaleza {{ $inicial === $tipo['codigo'] ? 'is-on' : '' }}" data-codigo="{{ $tipo['codigo'] }}">
                                <span class="log-ico"><i class="fa {{ $tipo['icono'] }}"></i></span>
                                <span>{{ $tipo['nombre'] }}</span>
                            </button>
                        @endforeach
                        @if (count($catalogo['tipos']) === 0)
                            <p class="small px-2">No tenés tipos de solicitud habilitados.</p>
                        @endif

                        <div id="log-rail-insumos" @if ($inicial !== 'insumos') style="display:none;" @endif>
                            <div class="log-rail-kicker">Categorías</div>
                            <div id="log-rail-categorias"></div>
                        </div>
                        <div id="log-rail-trabajos" @if ($inicial === 'insumos') style="display:none;" @endif>
                            <div class="log-rail-kicker">Trabajos</div>
                            @foreach ($trabajos as $trabajo)
                                <button type="button" class="log-tile log-trabajo" data-codigo="{{ $trabajo->codigo }}" data-piso="{{ $trabajo->prioridad_piso }}" data-nombre="{{ $trabajo->nombre }}">
                                    <span class="log-ico"><i class="fa {{ $trabajo->icono }}"></i></span>
                                    <span>{{ $trabajo->nombre }}</span>
                                </button>
                            @endforeach
                        </div>
                    </aside>

                    <section class="log-stage">
                        <form method="POST" action="{{ route('guardar_logistica_solicitud') }}" id="form-solicitud-logistica" class="{{ $inicial !== 'insumos' ? 'log-off' : '' }}">
                            @csrf
                            <div class="log-stage-head">
                                <h4 id="log-titulo-insumos">Insumos</h4>
                                <input type="search" id="log-buscar" class="form-control form-control-sm log-buscar" placeholder="Texto o número" autocomplete="off">
                            </div>
                            <div class="log-grid" id="log-grilla">
                                @foreach ($catalogo['items'] as $item)
                                    <article class="log-card"
                                             data-id="{{ $item['id'] }}"
                                             data-categoria="{{ $item['categoria_id'] }}"
                                             data-sku="{{ $item['sku'] }}"
                                             data-nombre="{{ $item['nombre'] }}"
                                             data-precio="{{ $item['precio'] }}"
                                             data-cc="{{ implode(',', $item['cc_ids'] ?? []) }}"
                                             data-texto="{{ strtolower($item['sku'].' '.$item['nombre']) }}"
                                             style="display:none;">
                                        <div class="log-card-top">
                                            <span class="badge badge-light">{{ $item['sku'] }}</span>
                                            @if (!empty($item['favorito']))
                                                <span class="text-warning" title="Favorito">★</span>
                                            @endif
                                        </div>
                                        <h5>{{ $item['nombre'] }}</h5>
                                        <div class="log-sku">{{ $item['unidad'] }}</div>
                                        <div class="log-precio">$ {{ number_format((float) $item['precio'], 2, ',', '.') }}</div>
                                        <div class="log-card-acciones">
                                            <input type="number" class="form-control form-control-sm log-cant" min="0.01" step="0.01" value="1">
                                            <button type="button" class="btn btn-sm btn-primary log-agregar">Agregar</button>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                            <p id="log-vacio" class="text-muted px-3" style="display:none;">No hay artículos en esta categoría.</p>

                            <div class="log-cart">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Pedido · <span id="log-cant-items">0</span> ítems</strong>
                                    <strong>Total estimado <span id="log-total">$ 0,00</span></strong>
                                </div>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th>SKU</th>
                                                <th>Descripción</th>
                                                <th class="text-right">Cantidad</th>
                                                <th class="text-right">Importe</th>
                                                <th style="width:3rem;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="log-carrito-body"></tbody>
                                    </table>
                                </div>
                                @include('contable.partials.campo_consulta_centrocosto', [
                                    'prefix' => 'solicitud',
                                    'layout' => 'form_row',
                                    'inputName' => 'centrocosto_id',
                                    'inputId' => 'solicitud_centrocosto_id',
                                    'centrocostoId' => $centrocostoId ?: '',
                                    'codigo' => $centrocostoCodigo,
                                    'descripcion' => $centrocostoNombre,
                                    'required' => true,
                                    'col_label' => 'col-lg-3 control-label text-right pr-2',
                                    'col_input' => 'col-lg-9',
                                ])
                                <div class="form-group row">
                                    <label class="col-lg-3 control-label text-right pr-2">Prioridad</label>
                                    <div class="col-lg-4">
                                        <select name="prioridad" class="form-control">
                                            <option value="Normal">Normal</option>
                                            <option value="Urgente">Urgente</option>
                                        </select>
                                    </div>
                                </div>
                                <div id="logistica-lineas"></div>
                                <button type="submit" class="btn btn-success">Enviar solicitud</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('guardar_logistica_solicitud') }}" id="form-solicitud-trabajo" class="form-horizontal log-trabajo {{ $inicial === 'insumos' ? 'log-off' : '' }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="trabajo_codigo" id="trabajo_codigo" value="">
                            <p id="log-trabajo-placeholder" class="text-muted pt-3">Elegí un trabajo en el panel de la izquierda.</p>
                            <div id="log-trabajo-cuerpo" style="display:none;">
                                <h4 id="log-trabajo-titulo" class="mt-2"></h4>
                                <p id="log-trabajo-responsable" class="text-muted small"></p>

                                <div class="trab-panel" data-codigo="slots" style="display:none;">
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Origen</label>
                                        <div class="col-lg-9">
                                            <div class="d-flex flex-wrap ubi-grupo" style="gap:8px;">
                                                @foreach ($ubicaciones as $ubicacion)
                                                    <button type="button" class="btn btn-outline-secondary btn-sm ubi-btn" data-id="{{ $ubicacion->id }}">{{ $ubicacion->nombre }}</button>
                                                @endforeach
                                            </div>
                                            <input type="hidden" name="ubicacion_origen_id" value="" disabled>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Destino</label>
                                        <div class="col-lg-9">
                                            <div class="d-flex flex-wrap ubi-grupo" style="gap:8px;">
                                                @foreach ($ubicaciones as $ubicacion)
                                                    <button type="button" class="btn btn-outline-secondary btn-sm ubi-btn" data-id="{{ $ubicacion->id }}">{{ $ubicacion->nombre }}</button>
                                                @endforeach
                                            </div>
                                            <input type="hidden" name="ubicacion_destino_id" value="" disabled>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Cantidad</label>
                                        <div class="col-lg-3"><input type="number" name="cantidad" class="form-control" min="1" step="1" disabled></div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Fecha tentativa</label>
                                        <div class="col-lg-4"><input type="date" name="fecha_tentativa" class="form-control" disabled></div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Descripción</label>
                                        <div class="col-lg-9"><input type="text" name="detalle" class="form-control" maxlength="500" disabled></div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Accesorios</label>
                                        <div class="col-lg-9">
                                            <label class="mr-3"><input type="radio" name="accesorios" value="0" checked disabled> No</label>
                                            <label><input type="radio" name="accesorios" value="1" disabled> Sí</label>
                                            <input type="text" name="accesorios_detalle" class="form-control mt-2" placeholder="Cuáles" style="display:none;" disabled>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Foto</label>
                                        <div class="col-lg-9"><input type="file" name="foto" class="form-control" accept="image/*,.pdf" disabled></div>
                                    </div>
                                </div>

                                <div class="trab-panel" data-codigo="retiro" style="display:none;">
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Empresa</label>
                                        <div class="col-lg-9">
                                            <div class="d-flex flex-wrap" style="gap:8px;">
                                                @foreach ($empresas as $empresa)
                                                    <button type="button" class="btn btn-outline-secondary btn-sm retiro-empresa" data-id="{{ $empresa['id'] }}">{{ $empresa['nombre'] }}</button>
                                                @endforeach
                                            </div>
                                            <input type="hidden" name="empresa_id" id="retiro_empresa_id" value="" disabled>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Orden de compra</label>
                                        <div class="col-lg-4">
                                            <input type="text" name="ordencompra_numero" id="retiro_oc" class="form-control" inputmode="numeric" disabled>
                                            <small id="retiro_oc_ayuda" class="text-muted"></small>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Dirección de retiro</label>
                                        <div class="col-lg-9"><input type="text" name="direccion_retiro" id="retiro_direccion" class="form-control" maxlength="180" disabled></div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Foto de la OC</label>
                                        <div class="col-lg-9"><input type="file" name="foto" class="form-control" accept="image/*,.pdf" disabled></div>
                                    </div>
                                </div>

                                <div class="trab-panel" data-codigo="elementos" style="display:none;">
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Motivo</label>
                                        <div class="col-lg-4">
                                            <select name="motivo" class="form-control" disabled>
                                                <option value="resguardo">Resguardo</option>
                                                <option value="destruccion">Destrucción</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Ubicación</label>
                                        <div class="col-lg-9">
                                            <div class="d-flex flex-wrap ubi-grupo" style="gap:8px;">
                                                @foreach ($ubicaciones as $ubicacion)
                                                    <button type="button" class="btn btn-outline-secondary btn-sm ubi-btn" data-id="{{ $ubicacion->id }}">{{ $ubicacion->nombre }}</button>
                                                @endforeach
                                            </div>
                                            <input type="hidden" name="ubicacion_origen_id" value="" disabled>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Descripción</label>
                                        <div class="col-lg-9"><input type="text" name="detalle" class="form-control" maxlength="500" disabled></div>
                                    </div>
                                </div>

                                <div class="trab-panel" data-codigo="butacas" style="display:none;">
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Tipo</label>
                                        <div class="col-lg-4">
                                            <select name="tipo_butaca" class="form-control" disabled>
                                                <option value="vip">VIP</option>
                                                <option value="especial">Especial</option>
                                                <option value="comunes">Comunes</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Cantidad</label>
                                        <div class="col-lg-3"><input type="number" name="cantidad" class="form-control" min="1" max="10" step="1" disabled></div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">UID</label>
                                        <div class="col-lg-4"><input type="text" name="uid_bien" class="form-control" maxlength="40" placeholder="BTC-00214" disabled></div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-lg-3 control-label text-right pr-2">Notas</label>
                                        <div class="col-lg-9"><input type="text" name="detalle" class="form-control" maxlength="500" disabled></div>
                                    </div>
                                </div>

                                @include('contable.partials.campo_consulta_centrocosto', [
                                    'prefix' => 'solicitudtrab',
                                    'layout' => 'form_row',
                                    'inputName' => 'centrocosto_id',
                                    'inputId' => 'solicitudtrab_centrocosto_id',
                                    'centrocostoId' => $centrocostoId ?: '',
                                    'codigo' => $centrocostoCodigo,
                                    'descripcion' => $centrocostoNombre,
                                    'required' => true,
                                    'col_label' => 'col-lg-3 control-label text-right pr-2',
                                    'col_input' => 'col-lg-9',
                                ])
                                <div class="form-group row">
                                    <label class="col-lg-3 control-label text-right pr-2">Prioridad</label>
                                    <div class="col-lg-4">
                                        <select name="prioridad" id="trab-prioridad" class="form-control"></select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">Enviar solicitud</button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>
@include('includes.contable.modalconsultacentrocosto')
@endsection
