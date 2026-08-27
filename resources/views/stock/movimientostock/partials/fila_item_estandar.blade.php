        <td class="align-middle text-center">
            <span class="abrev-umd text-muted font-weight-bold">{{ $abrevUmd ?? '' }}</span>
            <input type="hidden" class="unidadesxenvase" value="{{ $unidadesxenvase ?? '' }}">
        </td>
        <td class="align-middle">
            <input type="text" name="cantidades[]" class="form-control form-control-sm cantidad-stock text-right" value="{{ $cantidad ?? '' }}" autocomplete="off">
            <input type="hidden" name="cajas[]" class="caja" value="0">
        </td>
        <td class="align-middle text-center">
            <span class="abrev-umd-alter text-muted font-weight-bold">{{ $abrevUmdAlter ?? '' }}</span>
        </td>
        <td class="align-middle">
            <input type="text" name="piezas[]" class="form-control form-control-sm cant-unidad text-right" value="{{ $cantUnidad ?? '' }}" autocomplete="off">
        </td>
        <td class="align-middle col-insumo-dest-celda ms-col-conversion-formula">
            <div class="d-flex align-items-center flex-nowrap">
                <a href="#"
                   class="btn btn-xs btn-link-articulo-destino d-none flex-shrink-0"
                   target="_blank"
                   rel="noopener"
                   title="Consultar artículo destino / insumo">
                    <i class="fa fa-external-link text-primary"></i>
                </a>
                <input type="hidden" class="ms-articulo-destino-id" value="">
                <input type="text"
                       class="form-control form-control-sm ms-insumo-destino-sku flex-grow-1"
                       value="{{ $insumoDestinoSku ?? '' }}"
                       readonly
                       tabindex="-1"
                       title="{{ $insumoDestinoDescripcion ?? '' }}"
                       placeholder="—">
            </div>
        </td>
        <td class="align-middle ms-col-conversion-formula">
            <input type="text"
                   class="form-control form-control-sm ms-cantidad-destino text-right text-monospace"
                   value="{{ $cantidadDestino ?? '' }}"
                   readonly
                   tabindex="-1"
                   placeholder="—">
        </td>
        <td class="align-middle text-center ms-col-conversion-formula">
            <span class="ms-um-destino text-muted font-weight-bold">{{ $umDestino ?? '' }}</span>
        </td>
        <td class="align-middle">
            <input type="text" style="text-align: right;" name="precios[]" class="form-control form-control-sm precio" value="{{ $precio ?? '' }}" autocomplete="off">
        </td>
