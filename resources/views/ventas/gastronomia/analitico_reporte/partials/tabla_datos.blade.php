@php
    $links = $con_links ?? true;
@endphp
<thead>
    <tr>
        <th>Id</th>
        <th>Fecha jornada</th>
        <th>Fecha real</th>
        <th>Sala</th>
        <th>Tipo comp.</th>
        <th>P.Vta</th>
        <th>Nº comp.</th>
        <th>Mozo Id</th>
        <th>Mozo</th>
        <th>Legajo</th>
        <th>C&oacute;d. art.</th>
        <th>Descripci&oacute;n</th>
        <th>Tipo venta</th>
        <th class="text-right">Cant.</th>
        <th class="text-right">P. unit.</th>
        <th class="text-right">Total</th>
        <th class="text-right">Costo</th>
        <th>Tipo desc.</th>
        <th>Categor&iacute;a</th>
        <th>Cliente</th>
        <th>A&ntilde;o</th>
        <th>Hora</th>
        <th>Mes</th>
        <th>D&iacute;a</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas ?? [] as $f)
        <tr>
            <td class="text-nowrap">
                @if ($links && ($puede_ver_factura ?? false) && (int) ($f->venta_id ?? 0) > 0)
                    <a href="{{ route('gastronomia_facturas_dia_ver', ['ventaId' => $f->venta_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       target="_blank" rel="noopener" class="text-primary" title="Ver factura">
                        {{ $f->id ?? '—' }}
                    </a>
                @else
                    {{ $f->id ?? '—' }}
                @endif
            </td>
            <td class="text-nowrap">{{ $f->fecha_jornada_fmt ?? '—' }}</td>
            <td class="text-nowrap">{{ $f->fecha_real_fmt ?? '—' }}</td>
            <td>
                @if ($links && ($puede_ver_empresa ?? false) && (int) ($f->empresa_id ?? 0) > 0)
                    <a href="{{ route('editar_empresa', ['id' => $f->empresa_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $f->sala !== '' ? $f->sala : '—' }}
                    </a>
                @else
                    {{ ($f->sala ?? '') !== '' ? $f->sala : '—' }}
                @endif
            </td>
            <td>
                @if ($links && ($puede_ver_tipotransaccion ?? false) && (int) ($f->tipotransaccion_id ?? 0) > 0)
                    <a href="{{ route('editar_tipotransaccion', ['id' => $f->tipotransaccion_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $f->tipo_comprobante ?? '—' }}
                    </a>
                @else
                    {{ $f->tipo_comprobante ?? '—' }}
                @endif
            </td>
            <td>
                @if ($links && ($puede_ver_puntoventa ?? false) && (int) ($f->puntoventa_id ?? 0) > 0)
                    <a href="{{ route('editar_puntoventa', ['id' => $f->puntoventa_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $f->punto_venta ?? '—' }}
                    </a>
                @else
                    {{ $f->punto_venta ?? '—' }}
                @endif
            </td>
            <td class="text-nowrap">
                @if ($links && ($puede_ver_factura ?? false) && (int) ($f->venta_id ?? 0) > 0)
                    <a href="{{ route('gastronomia_facturas_dia_ver', ['ventaId' => $f->venta_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $f->numero_comprobante ?? '—' }}
                    </a>
                @else
                    {{ $f->numero_comprobante ?? '—' }}
                @endif
            </td>
            <td>
                @if ($links && ($puede_ver_mozo ?? false) && (int) ($f->mozo_id ?? 0) > 0)
                    <a href="{{ route('editar_mozo_gastronomia', ['id' => $f->mozo_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $f->mozo_id }}
                    </a>
                @else
                    {{ (int) ($f->mozo_id ?? 0) > 0 ? $f->mozo_id : '—' }}
                @endif
            </td>
            <td>
                @if ($links && ($puede_ver_mozo ?? false) && (int) ($f->mozo_id ?? 0) > 0)
                    <a href="{{ route('editar_mozo_gastronomia', ['id' => $f->mozo_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ ($f->nombre_mozo ?? '') !== '' ? $f->nombre_mozo : '—' }}
                    </a>
                @else
                    {{ ($f->nombre_mozo ?? '') !== '' ? $f->nombre_mozo : '—' }}
                @endif
            </td>
            <td>{{ ($f->legajo_mozo ?? '') !== '' ? $f->legajo_mozo : '—' }}</td>
            <td class="text-nowrap">
                @if ($links && ($puede_ver_articulo ?? false) && (int) ($f->articulo_id ?? 0) > 0)
                    <a href="{{ route('editar_articulo', ['id' => $f->articulo_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $f->codigo_articulo ?? '—' }}
                    </a>
                @else
                    {{ $f->codigo_articulo ?? '—' }}
                @endif
            </td>
            <td>
                @if ($links && ($puede_ver_articulo ?? false) && (int) ($f->articulo_id ?? 0) > 0)
                    <a href="{{ route('editar_articulo', ['id' => $f->articulo_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $f->descripcion_articulo ?? '—' }}
                    </a>
                @else
                    {{ $f->descripcion_articulo ?? '—' }}
                @endif
            </td>
            <td>{{ $f->tipo_venta ?? '—' }}</td>
            <td class="text-right">{{ number_format((float) ($f->cantidad ?? 0), 3, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($f->precio_unitario ?? 0), 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($f->total ?? 0), 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($f->costo ?? 0), 2, ',', '.') }}</td>
            <td>
                @if ($links && ($puede_ver_descuento ?? false) && (int) ($f->descuento_gastronomia_id ?? 0) > 0)
                    <a href="{{ route('editar_descuento_gastronomia', ['id' => $f->descuento_gastronomia_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ ($f->tipo_descuento ?? '') !== '' ? $f->tipo_descuento : '—' }}
                    </a>
                @else
                    {{ ($f->tipo_descuento ?? '') !== '' ? $f->tipo_descuento : '—' }}
                @endif
            </td>
            <td>
                @if ($links && ($puede_ver_categoria ?? false) && (int) ($f->categoria_id ?? 0) > 0)
                    <a href="{{ route('editar_categoria', ['id' => $f->categoria_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ ($f->categoria_articulo ?? '') !== '' ? $f->categoria_articulo : '—' }}
                    </a>
                @else
                    {{ ($f->categoria_articulo ?? '') !== '' ? $f->categoria_articulo : '—' }}
                @endif
            </td>
            <td>
                @if ($links && ($puede_ver_cliente ?? false) && (int) ($f->cliente_id ?? 0) > 0)
                    <a href="{{ route('editar_cliente', ['id' => $f->cliente_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ ($f->cliente ?? '') !== '' ? $f->cliente : '—' }}
                    </a>
                @elseif ($links && ($puede_ver_cliente ?? false) && (int) ($f->cliente_interno_descuento_id ?? 0) > 0)
                    <a href="{{ route('editar_cliente', ['id' => $f->cliente_interno_descuento_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ ($f->cliente ?? '') !== '' ? $f->cliente : '—' }}
                    </a>
                @else
                    {{ ($f->cliente ?? '') !== '' ? $f->cliente : '—' }}
                @endif
            </td>
            <td>{{ $f->anio ?? '—' }}</td>
            <td>{{ ($f->hora ?? '') !== '' ? $f->hora : '—' }}</td>
            <td>{{ $f->mes ?? '—' }}</td>
            <td>{{ $f->dia ?? '—' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="24" class="text-center text-muted py-4">Sin movimientos para los filtros indicados.</td>
        </tr>
    @endforelse
</tbody>
