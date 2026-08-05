@php
    use App\Support\Compras\ListaprecioProveedorConsultaDesdeModal;
    $lineasProveedor = $articulo_proveedor_lineas ?? collect();
    $puedeActualizarComprasArticulo = can('actualizar-compras-articulos', false);
    $soloLecturaProveedores = ! $puedeActualizarComprasArticulo;
    $preferidoProveedorId = (int) old('ap_preferido_proveedor_id', $lineasProveedor->firstWhere('preferido', true)?->proveedor_id ?? 0);
@endphp
<div id="tab8" class="card form8 tab-content" style="display: none">
    <div class="card-body">
        <p class="text-muted small mb-2">
            Cat&aacute;logo de compra por proveedor (c&oacute;digos, UM de compra, conversi&oacute;n). Puede cargar varias l&iacute;neas del mismo proveedor con distinto c&oacute;digo de art&iacute;culo (marcas distintas que resuelven al mismo art&iacute;culo ERP).
            La clave del cat&aacute;logo es <strong>proveedor + c&oacute;digo art&iacute;culo proveedor</strong>.
            El precio y la moneda se consultan de la lista de precios activa y vigente del proveedor; no se guardan en esta tabla.
            El c&oacute;digo de art&iacute;culo proveedor se sincroniza con la l&iacute;nea vigente de la lista activa cuando existe en ambos lados.
        </p>
        @if ($soloLecturaProveedores && can('editar-compras-articulos', false))
            <p class="text-info small mb-2">Solo consulta: necesita permiso <em>actualizar-compras-articulos</em> para modificar proveedores.</p>
        @endif
        <style>
            #tabla-articulo-proveedor .col-proveedor { width: 20%; min-width: 12rem; }
            #tabla-articulo-proveedor .col-nombre-art { width: 12%; }
            #tabla-articulo-proveedor .col-codbarra { width: 7%; min-width: 6.5rem; }
            #tabla-articulo-proveedor .col-cod-art-prov { width: 8%; }
            #tabla-articulo-proveedor .col-moneda { width: 5%; }
            #tabla-articulo-proveedor .col-precio { width: 7%; }
            #tabla-articulo-proveedor .col-vigencia { width: 7%; }
            #tabla-articulo-proveedor .col-um-compra { width: 10%; }
            #tabla-articulo-proveedor .col-coef { width: 6%; }
            #tabla-articulo-proveedor .col-activo { width: 4%; }
            #tabla-articulo-proveedor .col-preferido { width: 4%; }
            #tabla-articulo-proveedor .col-lista { width: 4%; min-width: 2.5rem; }
            #tabla-articulo-proveedor .col-accion { width: 3%; }
            #tabla-articulo-proveedor .nombreproveedor { flex: 1 1 auto; min-width: 0; }
            #tabla-articulo-proveedor .ap-codigobarra { max-width: 7.5rem; }
        </style>
        <div class="table-responsive">
            <table class="table table-sm" id="tabla-articulo-proveedor"
                data-puede-consultar-lista="{{ ListaprecioProveedorConsultaDesdeModal::puedeConsultar() ? '1' : '0' }}">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th class="col-proveedor">Proveedor</th>
                        <th class="col-nombre-art">Nombre art. proveedor</th>
                        <th class="col-codbarra">C&oacute;d. barras</th>
                        <th class="col-cod-art-prov">C&oacute;d. art. proveedor</th>
                        <th class="col-moneda">Moneda</th>
                        <th class="col-precio">Precio vigente</th>
                        <th class="col-vigencia">Vigencia</th>
                        <th class="col-um-compra">UM compra</th>
                        <th class="col-coef">Coef. conv.</th>
                        <th class="col-activo text-center" title="Proveedor activo para compras">Activo</th>
                        <th class="col-preferido text-center" title="Proveedor preferido del art&iacute;culo">Pref.</th>
                        <th class="col-lista text-center" title="Lista de precios vigente (consulta)">Lista</th>
                        @if (! $soloLecturaProveedores)
                            <th class="col-accion"></th>
                        @endif
                    </tr>
                </thead>
                <tbody id="tbody-articulo-proveedor">
                    @foreach ($lineasProveedor as $idx => $linea)
                        @php
                            $listaId = (int) ($linea->getAttribute('listaprecio_proveedor_id_resuelto') ?? 0);
                            $tienePrecio = (bool) $linea->getAttribute('tiene_precio_vigente');
                            $precioVigente = $linea->getAttribute('precio_vigente');
                            $monedaVigente = (string) ($linea->getAttribute('moneda_vigente_abreviatura') ?? '');
                            $fechaVigente = (string) ($linea->getAttribute('fechavigencia_lista') ?? '');
                            $listaNombre = (string) ($linea->getAttribute('lista_nombre_resuelta') ?? 'Lista de precios proveedor');
                            $tituloLista = $listaNombre;
                            if ($fechaVigente !== '') {
                                $tituloLista .= ' (vig. '.$fechaVigente.')';
                            }
                            $proveedorIdLinea = (int) ($linea->proveedor_id ?? 0);
                            $activoLinea = (bool) old('ap_activos.'.$idx, $linea->activo ?? true);
                        @endphp
                        <tr class="item-articulo-proveedor">
                            <td class="col-proveedor">
                                <input type="hidden" class="ap_linea_id" name="ap_linea_ids[]" value="{{ old('ap_linea_ids.'.$idx, $linea->id ?? '') }}">
                                <input type="hidden" class="proveedor_id" name="ap_proveedor_ids[]" value="{{ old('ap_proveedor_ids.'.$idx, $linea->proveedor_id ?? '') }}">
                                <div class="d-flex align-items-center flex-nowrap">
                                    <input type="text" class="form-control form-control-sm codigoproveedor mr-1" style="width: 4.5rem; flex-shrink: 0;" value="{{ old('ap_codigos_proveedor.'.$idx, optional($linea->proveedores)->codigo ?? '') }}" {{ $soloLecturaProveedores ? 'readonly' : '' }}>
                                    @if (! $soloLecturaProveedores)
                                        <button type="button" title="Consulta proveedores" class="btn-accion-tabla consultaproveedor tooltipsC mr-1">
                                            <i class="fa fa-search text-primary"></i>
                                        </button>
                                    @endif
                                    <input type="text" class="form-control form-control-sm nombreproveedor" title="{{ old('ap_nombres_proveedor.'.$idx, optional($linea->proveedores)->nombre ?? '') }}" value="{{ old('ap_nombres_proveedor.'.$idx, optional($linea->proveedores)->nombre ?? '') }}" readonly>
                                </div>
                            </td>
                            <td>
                                <input type="text" name="ap_nombres_articulo_proveedor[]" class="form-control form-control-sm" maxlength="255" value="{{ old('ap_nombres_articulo_proveedor.'.$idx, $linea->nombre_articulo_proveedor ?? '') }}" {{ $soloLecturaProveedores ? 'readonly' : '' }}>
                            </td>
                            <td class="col-codbarra">
                                <input type="text" name="ap_codigosbarra[]" class="form-control form-control-sm ap-codigobarra" maxlength="13" inputmode="numeric" pattern="[0-9]*" value="{{ old('ap_codigosbarra.'.$idx, $linea->codigobarra ?? '') }}" {{ $soloLecturaProveedores ? 'readonly' : '' }}>
                            </td>
                            <td>
                                <input type="text" name="ap_codigos_articulo_proveedor[]" class="form-control form-control-sm" maxlength="100" value="{{ old('ap_codigos_articulo_proveedor.'.$idx, $linea->codigo_articulo_proveedor ?? '') }}" {{ $soloLecturaProveedores ? 'readonly' : '' }}>
                            </td>
                            <td class="col-moneda">
                                <input type="text" class="form-control form-control-sm ap-moneda-vigente" value="{{ $monedaVigente !== '' ? $monedaVigente : '—' }}" readonly tabindex="-1">
                            </td>
                            <td class="col-precio">
                                <input type="text" class="form-control form-control-sm ap-precio-vigente" readonly tabindex="-1"
                                    value="{{ $tienePrecio && $precioVigente !== null && $precioVigente !== '' ? rtrim(rtrim(number_format((float) $precioVigente, 6, '.', ''), '0'), '.') : '—' }}"
                                    title="{{ $tienePrecio && $fechaVigente !== '' ? 'Precio neto vigente al '.$fechaVigente : 'Sin precio en lista activa' }}">
                            </td>
                            <td class="col-vigencia">
                                <input type="text" class="form-control form-control-sm ap-vigencia-lista" readonly tabindex="-1" value="{{ $fechaVigente !== '' ? $fechaVigente : '—' }}">
                            </td>
                            <td>
                                <select name="ap_unidadmedida_compra_ids[]" class="form-control form-control-sm" {{ $soloLecturaProveedores ? 'disabled' : '' }}>
                                    <option value="">—</option>
                                    @foreach ($unidadmedida as $um)
                                        <option value="{{ $um->id }}" {{ (int) old('ap_unidadmedida_compra_ids.'.$idx, $linea->unidadmedida_compra_id ?? ($producto->unidadmedida_id ?? 0)) === (int) $um->id ? 'selected' : '' }}>
                                            {{ $um->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.000001" min="0.000001" name="ap_coeficientes_conversion[]" class="form-control form-control-sm" value="{{ old('ap_coeficientes_conversion.'.$idx, $linea->coeficiente_conversion ?? 1) }}" {{ $soloLecturaProveedores ? 'readonly' : '' }}>
                            </td>
                            <td class="col-activo text-center align-middle">
                                <input type="hidden" name="ap_activos[]" class="ap-activo-val" value="{{ $activoLinea ? '1' : '0' }}">
                                <input type="checkbox" class="ap-activo-check" value="1" {{ $activoLinea ? 'checked' : '' }} {{ $soloLecturaProveedores ? 'disabled' : '' }}>
                            </td>
                            <td class="col-preferido text-center align-middle">
                                <input type="radio" name="ap_preferido_proveedor_id" class="ap-preferido" value="{{ $proveedorIdLinea }}" {{ $preferidoProveedorId > 0 && $preferidoProveedorId === $proveedorIdLinea ? 'checked' : '' }} {{ $soloLecturaProveedores ? 'disabled' : '' }}>
                            </td>
                            <td class="col-lista text-center align-middle px-1 ap-celda-lista">
                                @if ($listaId > 0 && ListaprecioProveedorConsultaDesdeModal::puedeConsultar())
                                    <a href="{{ ListaprecioProveedorConsultaDesdeModal::urlEditar($listaId) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="btn-accion-tabla tooltipsC ap-link-lista"
                                        title="Abrir lista: {{ $tituloLista }}">
                                        <span class="badge badge-success px-1 ap-badge-lista"><i class="fa fa-list"></i></span>
                                    </a>
                                @elseif ($listaId > 0)
                                    <span class="badge badge-success px-1 tooltipsC ap-badge-lista" title="{{ $tituloLista }}">
                                        <i class="fa fa-list"></i>
                                    </span>
                                @else
                                    <span class="badge badge-secondary px-1 tooltipsC ap-badge-lista" title="Sin lista de precios activa con este art&iacute;culo">
                                        <i class="fa fa-minus"></i>
                                    </span>
                                @endif
                            </td>
                            @if (! $soloLecturaProveedores)
                                <td class="col-accion text-center align-middle px-1">
                                    <button type="button" title="Eliminar l&iacute;nea" class="btn-accion-tabla eliminar_articulo_proveedor tooltipsC">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (! $soloLecturaProveedores)
            <button type="button" class="pull-right btn btn-danger btn-sm" id="agrega_renglon_articulo_proveedor">+ Agregar proveedor</button>
        @endif
    </div>
</div>
@include('stock.articulo.template8')
