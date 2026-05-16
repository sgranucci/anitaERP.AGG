@extends("theme.$theme.layout")

@section('titulo')
    Proceso facturación gastronomía
@endsection

@section('scripts')
<script>
    window.GASTRONOMIA = {
        empresaId: {{ (int) $empresa_id }},
        prefijoSku: @json($prefijo_sku),
        skuCatalogoDigitosSufijo: {{ (int) $sku_catalogo_digitos_sufijo }},
        csrf: @json(csrf_token()),
        rutas: {
            crearCobranzaBase: @json(url('caja/cobranza/crear')),
            listaPdfFacturaBase: @json(url('ventas/listaunafactura')),
        },
        tieneCfgPv: @json($tiene_cfg_pv),
    };
</script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/gastronomia/proceso_facturacion.js') }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        @if (!$tiene_cfg_pv)
            <div class="alert alert-warning">
                No hay configuración de punto de venta gastronomía para el identificador PC actual (<code>{{ $identificador_pc_actual }}</code>).
                Configure en <a href="{{ route('consultar_configuracion_puntoventa_gastronomia') }}">Config. punto de venta gastronomía</a>
                con ese mismo valor, y/o ajuste <code>GASTRONOMIA_IDENTIFICADOR_PC</code> @if (config('gastronomia.identificador_pc_usar_ip_cliente'))
                (modo IP por terminal: <code>GASTRONOMIA_IDENTIFICADOR_USAR_IP_CLIENTE=true</code>; revise proxies nginx si la IP no es la de la PC)
                @endif.
            </div>
        @endif

        <div class="alert alert-info py-2 mb-3">
            SKU catálogo: prefijo <strong>{{ $prefijo_sku }}</strong>
            @if ((int) $sku_catalogo_digitos_sufijo > 0)
                — ingreso rápido: <strong>{{ (int) $sku_catalogo_digitos_sufijo }}</strong> dígitos tras el prefijo (<code>GASTRONOMIA_SKU_CATALOGO_DIGITOS_SUFIJO</code>)
            @endif
            —
            tipo transacción factura: env <code>GASTRONOMIA_TIPO_TRANSACCION_FACTURA_ID</code>
            @if (! config('gastronomia.tipotransaccion_factura_id'))
                <span class="text-danger">(no configurado)</span>
            @endif
        </div>

        <div class="card card-outline card-primary mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-cutlery"></i> Mesa / cuenta</span>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary active" id="btn-modo-mesa">Mesas</button>
                    <button type="button" class="btn btn-outline-secondary" id="btn-modo-cuenta">Cuentas libres</button>
                </div>
            </div>
            <div class="card-body py-2">
                <div id="panel-mesas" class="row"></div>
                <div id="panel-cuentas" class="row d-none"></div>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-success" id="btn-nueva-cuenta-libre"><i class="fa fa-plus"></i> Nueva cuenta</button>
                    <button type="button" class="btn btn-sm btn-outline-danger d-none" id="btn-cerrar-cuenta"><i class="fa fa-times"></i> Cerrar cuenta</button>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-5">
                <div class="card card-outline card-secondary mb-3">
                    <div class="card-header py-2">
                        <span><i class="fa fa-user"></i> Cuenta seleccionada</span>
                        <span class="badge badge-dark ml-2 d-none" id="badge-cuenta-id"></span>
                    </div>
                    <div class="card-body py-2">
                        <div class="form-row">
                            <div class="form-group col-md-12 mb-2">
                                <label class="small mb-0 requerido">Cliente</label>
                                <div class="d-flex flex-wrap align-items-center" style="gap:6px;">
                                    <input type="text" class="form-control form-control-sm" style="max-width:88px;" id="cliente_id" name="cliente_id" value="" autocomplete="off">
                                    <button type="button" title="Consulta clientes" class="btn-accion-tabla consultacliente tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" class="form-control form-control-sm" style="max-width:100px;" id="codigocliente" name="codigocliente" value="" placeholder="Código" autocomplete="off">
                                    <input type="text" class="form-control form-control-sm flex-grow-1" id="nombrecliente" name="nombrecliente" value="" placeholder="Nombre / razón social" autocomplete="off">
                                </div>
                            </div>
                            <div class="form-group col-md-6 mb-2">
                                <label class="small mb-0">Cubiertos</label>
                                <input type="number" min="0" class="form-control form-control-sm" id="fld-cubiertos" value="0">
                            </div>
                            <div class="form-group col-md-12 mb-2">
                                <label class="small mb-0">Mozo</label>
                                <select class="form-control form-control-sm" id="fld-mozo"></select>
                            </div>
                            <div class="form-group col-md-12 mb-2">
                                <label class="small mb-0">Descuento gastronomía</label>
                                <select class="form-control form-control-sm" id="fld-descuento"></select>
                            </div>
                            <div class="col-md-12">
                                <button type="button" class="btn btn-sm btn-primary" id="btn-guardar-cabecera"><i class="fa fa-save"></i> Guardar datos cuenta</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-outline card-success mb-3">
                    <div class="card-header py-2"><span><i class="fa fa-cutlery"></i> Consumo (catálogo SKU {{ $prefijo_sku }}%)</span></div>
                    <div class="card-body py-2">
                        <p class="small text-muted mb-2 mb-md-1">
                            @if ((int) $sku_catalogo_digitos_sufijo > 0)
                                Ingrese solo los <strong>{{ (int) $sku_catalogo_digitos_sufijo }}</strong> dígitos del código; <kbd>Enter</kbd> agrega cantidad <strong>1</strong> a la cuenta. <kbd>Tab</kbd> busca el artículo y pasa al botón <strong>Agregar</strong> para cargar la cantidad.
                            @else
                                Use la lupa o el SKU; <kbd>Enter</kbd> en el campo SKU agrega con cantidad <strong>1</strong>; <kbd>Tab</kbd> busca y enfoca <strong>Agregar</strong> para ingresar cantidad.
                            @endif
                        </p>
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                            <tr id="tr-gastro-linea-articulo">
                                <td class="align-middle py-1" style="white-space:nowrap;">
                                    <input type="hidden" class="articulo_id" id="gastro_linea_articulo_id" value="">
                                    <input type="hidden" class="categoria_id" value="">
                                    <input type="hidden" class="subcategoria_id" value="">
                                    <input type="hidden" class="unidadmedida_id" value="">
                                    <button type="button" title="Consulta artículos (catálogo SKU {{ $prefijo_sku }})" class="btn-accion-tabla consultaarticulo tooltipsC" data-sku-prefijo-filtro="{{ $prefijo_sku }}">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                    @if ((int) $sku_catalogo_digitos_sufijo > 0)
                                        <div class="input-group input-group-sm d-inline-flex align-middle" style="width:auto;max-width:200px;vertical-align:middle;">
                                            <div class="input-group-prepend"><span class="input-group-text py-0 px-2">{{ $prefijo_sku }}</span></div>
                                            <input type="text" name="gastro_sku_sufijo" class="form-control gastro-sku-sufijo gastro-carga-sku" maxlength="{{ (int) $sku_catalogo_digitos_sufijo }}" inputmode="numeric" pattern="[0-9]*" placeholder="" autocomplete="off" style="min-width:72px;">
                                            <input type="hidden" class="codigoarticulo" value="">
                                        </div>
                                    @else
                                        <input type="text" class="form-control form-control-sm codigoarticulo gastro-carga-sku d-inline-block align-middle" style="width:118px;vertical-align:middle;" placeholder="SKU" autocomplete="off">
                                    @endif
                                </td>
                                <td class="py-1">
                                    <input type="text" class="form-control form-control-sm descripcionarticulo" placeholder="Descripción" readonly autocomplete="off">
                                </td>
                                <td class="align-middle py-1 text-nowrap">
                                    <button type="button" class="btn btn-sm btn-success" id="btn-agregar-consumo"><i class="fa fa-plus"></i> Agregar</button>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="card card-outline card-dark mb-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-list"></i> Consumos / herramientas</span>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-success" id="tool-facturar" title="Facturar"><i class="fa fa-file-invoice-dollar"></i></button>
                            <button type="button" class="btn btn-outline-info" id="tool-asignar-cliente" title="Enfocar cliente"><i class="fa fa-user"></i></button>
                            <button type="button" class="btn btn-outline-secondary" id="tool-descuento" title="Enfocar descuento"><i class="fa fa-percent"></i></button>
                            <a href="{{ route('gastronomia_facturas_dia') }}" class="btn btn-outline-primary" title="Facturas del día"><i class="fa fa-calendar-day"></i></a>
                        </div>
                    </div>
                    <div class="card-body py-2">
                        <div id="panel-detalle-lineas"></div>
                        <hr class="my-2">
                        <div id="panel-cobranza-compacta" class="small border rounded p-2 bg-light">
                            <strong>Cobranza</strong> (después de emitir — debe igualar total factura):
                            <div class="form-row mt-1">
                                <div class="col-md-4 mb-1">
                                    <select class="form-control form-control-sm" id="cb-uso"></select>
                                </div>
                                <div class="col-md-4 mb-1">
                                    <select class="form-control form-control-sm" id="cb-moneda"></select>
                                </div>
                                <div class="col-md-4 mb-1">
                                    <select class="form-control form-control-sm" id="cb-cuentacaja"></select>
                                </div>
                                <div class="col-md-4 mb-1">
                                    <input type="number" step="0.01" class="form-control form-control-sm" id="cb-monto" placeholder="Monto">
                                </div>
                                <div class="col-md-4 mb-1">
                                    <input type="text" readonly class="form-control form-control-sm" id="cb-cotiz" placeholder="Cotización">
                                </div>
                                <div class="col-md-4 mb-1 d-flex align-items-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-block" id="btn-abrir-cobranza-completa">Abrir cobranza…</button>
                                </div>
                            </div>
                            <div class="text-muted mt-1" style="font-size:11px;">
                                La cobranza completa usa el mismo flujo que Caja → Cobranzas (permiso <code>crear-cobranza</code>).
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.ventas.modalconsultacliente')
@include('includes.stock.modalconsultaarticulo')

<!-- Modal opcionales -->
<div class="modal fade" id="modal-opcionales" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2"><h6 class="modal-title">Opcionales del artículo</h6>
                <button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body py-2" id="modal-opcionales-body"></div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="modal-opcionales-confirmar">Agregar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal cantidad -->
<div class="modal fade" id="modal-cantidad" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2"><h6 class="modal-title">Cantidad</h6>
                <button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body py-2">
                <input type="number" step="any" min="0.0001" class="form-control" id="fld-cantidad-linea" value="1">
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-primary" id="modal-cantidad-confirmar">Continuar</button>
            </div>
        </div>
    </div>
</div>
@endsection
