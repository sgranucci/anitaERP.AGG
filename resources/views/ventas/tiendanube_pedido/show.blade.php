@extends("theme.$theme.layout")

@section('titulo')
    Pedido Tiendanube #{{ $pedido->order_number }}
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/ventas/tiendanube_pedido/facturar.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tn-facturar-overlay',
    'tituloId' => 'tn-facturar-titulo',
    'subtituloId' => 'tn-facturar-subtitulo',
    'titulo' => 'Emitiendo factura…',
    'subtitulo' => 'Consultando ARCA. No cierre la página.',
])

<div class="row">
    <div class="col-12">
        @include('includes.mensaje')

        <div class="mb-2">
            <a href="{{ route('tiendanube_pedidos') }}" class="btn btn-outline-info btn-sm">
                <i class="fa fa-reply-all"></i> Volver al listado
            </a>
            @if (can('sincronizar-tiendanube-pedidos', false))
                <form action="{{ route('tiendanube_pedido_refrescar', $pedido->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-sync"></i> Refrescar desde TN
                    </button>
                </form>
            @endif
        </div>

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    Pedido #{{ $pedido->order_number }}
                    <small class="ml-2">(ID {{ $pedido->tiendanube_order_id }})</small>
                </h3>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Estado ERP:</strong>
                        <span class="badge {{ \App\Support\Ventas\Tiendanube\TiendanubePedidoEstadoSupport::badgeClass($pedido->estado_erp) }}">
                            {{ \App\Support\Ventas\Tiendanube\TiendanubePedidoEstadoSupport::etiqueta($pedido->estado_erp) }}
                        </span>
                    </div>
                    <div class="col-md-4">
                        <strong>Pago:</strong> {{ $pedido->payment_status }}
                        @if ($pedido->paid_at)
                            ({{ $pedido->paid_at->format('d/m/Y H:i') }})
                        @endif
                    </div>
                    <div class="col-md-4">
                        <strong>Total:</strong> {{ number_format((float) $pedido->total, 2, ',', '.') }} {{ $pedido->currency }}
                    </div>
                </div>

                @if ($pedido->error_mensaje)
                    <div class="alert alert-warning">{{ $pedido->error_mensaje }}</div>
                @endif

                @if ($pedido->venta_id)
                    <div class="alert alert-success">
                        Facturado: venta
                        <strong>{{ $pedido->venta->codigo ?? ('#'.$pedido->venta_id) }}</strong>
                        @if ($pedido->venta?->cae)
                            — CAE {{ $pedido->venta->cae }}
                        @endif
                    </div>
                @endif

                <h5>Líneas</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Tipo</th>
                                <th>SKU</th>
                                <th>Descripción</th>
                                <th class="text-right">Cant.</th>
                                <th class="text-right">Precio</th>
                                <th class="text-right">Subtotal</th>
                                <th>Artículo ERP</th>
                                <th>Comb.</th>
                                <th>Talle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pedido->lineas as $linea)
                                <tr class="@if (! $linea->articulo_id && $linea->tipo === 'producto') table-danger @elseif ($linea->tipo === 'producto' && (! $linea->combinacion_id || ! $linea->talle_id)) table-warning @endif">
                                    <td>{{ $linea->tipo }}</td>
                                    <td>{{ $linea->sku }}</td>
                                    <td>{{ $linea->nombre }}</td>
                                    <td class="text-right">{{ number_format((float) $linea->quantity, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) $linea->price, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format($linea->subtotal(), 2, ',', '.') }}</td>
                                    <td>
                                        @if ($linea->articulo_id)
                                            {{ $linea->articulo->sku ?? $linea->articulo_id }}
                                            — {{ $linea->articulo->descripcion ?? '' }}
                                        @elseif ($linea->tipo === 'descuento')
                                            <span class="text-muted">Descuento pie</span>
                                        @elseif ($linea->tipo === 'envio')
                                            <span class="text-danger">Sin artículo envío (configurar SKU)</span>
                                        @else
                                            <span class="text-danger">Sin match</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($linea->combinacion_id)
                                            {{ $linea->combinacion->codigo ?? $linea->combinacion_id }}
                                            @if ($linea->combinacion?->nombre)
                                                — {{ $linea->combinacion->nombre }}
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        @if ($linea->talle_id)
                                            {{ $linea->talle->nombre ?? $linea->talle->codigo ?? $linea->talle_id }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($puedeFacturar)
                    <form method="POST" action="{{ route('tiendanube_pedido_facturar', $pedido->id) }}"
                          id="form-tn-facturar" class="form-horizontal">
                        @csrf
                        <input type="hidden" name="listaprecio_id" value="{{ $listaprecioId }}">

                        <div class="card card-outline card-info mb-3">
                            <div class="card-header"><strong>Emisión</strong></div>
                            <div class="card-body">
                                <div class="form-group row">
                                    <label class="col-lg-3 control-label text-right pr-2 requerido">Punto de venta</label>
                                    <div class="col-lg-5">
                                        <select name="puntoventa_id" id="puntoventa_id" class="form-control" required>
                                            <option value="">Seleccione…</option>
                                            @foreach ($puntoventas as $pv)
                                                <option value="{{ $pv->id }}" @if ((int) $pv->id === (int) $pvDefaultId) selected @endif>
                                                    {{ $pv->codigo }} — {{ $pv->nombre }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-lg-3 control-label text-right pr-2 requerido">Depósito</label>
                                    <div class="col-lg-5">
                                        <select name="deposito_id" id="deposito_id" class="form-control" required>
                                            <option value="">Seleccione…</option>
                                            @foreach ($depositos as $dep)
                                                <option value="{{ $dep->id }}" @if ((int) $dep->id === (int) $depDefaultId) selected @endif>
                                                    {{ $dep->codigo }} — {{ $dep->nombre }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card card-outline card-info mb-3">
                            <div class="card-header"><strong>Datos fiscales del comprador</strong></div>
                            <div class="card-body">
                                <div class="form-group row">
                                    <label class="col-lg-3 control-label text-right pr-2 requerido">Tipo comprobante</label>
                                    <div class="col-lg-4">
                                        <select name="letra" id="tn-letra" class="form-control">
                                            <option value="B" @if (($letraDefault ?? 'B') === 'B') selected @endif>Factura B (default)</option>
                                            <option value="A" @if (($letraDefault ?? 'B') === 'A') selected @endif>Factura A (con percepciones)</option>
                                        </select>
                                        <small class="form-text text-muted">
                                            Factura A exige CUIT y calcula percepciones IIBB/IVA según padrón.
                                        </small>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-lg-3 control-label text-right pr-2">Nombre / Razón social</label>
                                    <div class="col-lg-6">
                                        <input type="text" name="receptor_nombre" class="form-control"
                                               value="{{ old('receptor_nombre', $pedido->customer_name) }}">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-lg-3 control-label text-right pr-2">CUIT / DNI</label>
                                    <div class="col-lg-4">
                                        <input type="text" name="receptor_doc" class="form-control"
                                               value="{{ old('receptor_doc', $pedido->customer_doc) }}">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-lg-3 control-label text-right pr-2">Email</label>
                                    <div class="col-lg-5">
                                        <input type="email" name="receptor_email" class="form-control"
                                               value="{{ old('receptor_email', $pedido->customer_email) }}">
                                        <small class="form-text text-muted">Se envía la factura automáticamente a este email al emitir.</small>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-lg-3 control-label text-right pr-2">Domicilio</label>
                                    <div class="col-lg-7">
                                        <input type="text" name="receptor_domicilio" class="form-control"
                                               value="{{ old('receptor_domicilio', $domicilioDefault ?? '') }}">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="col-lg-6 offset-lg-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="forzar_cf" name="forzar_cf" value="1">
                                            <label class="custom-control-label" for="forzar_cf">
                                                Forzar consumidor final (solo si el monto lo permite)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card card-outline card-info mb-3">
                            <div class="card-header"><strong>Medios de pago</strong></div>
                            <div class="card-body">
                                <p class="text-muted mb-2">
                                    Gateway TN:
                                    <strong>{{ $pagoDetalle['gateway_name'] ?? ($pedido->gateway_name ?: ($pedido->gateway ?: '—')) }}</strong>
                                    @if (!empty($pagoDetalle['method']))
                                        · método <code>{{ $pagoDetalle['method'] }}</code>
                                    @endif
                                    @if (!empty($pagoDetalle['card']))
                                        · tarjeta <code>{{ $pagoDetalle['card'] }}</code>
                                    @endif
                                    @if (!empty($pagoDetalle['installments']))
                                        · cuotas {{ $pagoDetalle['installments'] }}
                                    @endif
                                    — total a cubrir:
                                    <strong id="tn-total-pedido">{{ number_format((float) $pedido->total, 2, '.', '') }}</strong>
                                </p>
                                <p class="small text-muted mb-2">
                                    @if (can('editar-configuracion-tiendanube', false))
                                        Mapa editable en
                                        <a href="{{ route('editar_configuracion_tiendanube') }}">Configuraci&oacute;n Tiendanube</a>
                                        (gateway&rarr;cuenta, PV&harr;dep&oacute;sito).
                                    @else
                                        Mapa gateway&rarr;cuenta / PV&harr;dep&oacute;sito en Configuraci&oacute;n Tiendanube.
                                    @endif
                                    Token API en <code>.env</code>.
                                </p>
                                <table class="table table-sm table-bordered" id="tabla-medios-tn">
                                    <thead style="background:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th>Cuenta de caja</th>
                                            <th style="width:160px;">Monto</th>
                                            <th style="width:60px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="medio-row">
                                            <td>
                                                <select name="cuentacaja_ids[]" class="form-control form-control-sm" required>
                                                    <option value="">Seleccione…</option>
                                                    @foreach ($cuentacajas as $cc)
                                                        <option value="{{ $cc->id }}"
                                                            @if ($cuentacajaSugeridaId && (int) $cc->id === (int) $cuentacajaSugeridaId) selected @endif>
                                                            {{ $cc->codigo }} — {{ $cc->nombre }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0.01" name="montos[]"
                                                       class="form-control form-control-sm monto-medio"
                                                       value="{{ number_format((float) $pedido->total, 2, '.', '') }}" required>
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-medio">
                                    + Agregar medio
                                </button>
                                <template id="tpl-medio-tn">
                                    <tr class="medio-row">
                                        <td>
                                            <select name="cuentacaja_ids[]" class="form-control form-control-sm">
                                                <option value="">Seleccione…</option>
                                                @foreach ($cuentacajas as $cc)
                                                    <option value="{{ $cc->id }}">{{ $cc->codigo }} — {{ $cc->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" name="montos[]"
                                                   class="form-control form-control-sm monto-medio" value="0">
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-accion-tabla btn-quitar-medio" title="Quitar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success" id="btn-tn-facturar">
                            <i class="fa fa-file-invoice"></i> Facturar pedido
                        </button>
                    </form>
                @elseif (! $pedido->estaFacturado())
                    <div class="alert alert-secondary mb-0">
                        No se puede facturar (sin permiso, no pagado, o bloqueado).
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
