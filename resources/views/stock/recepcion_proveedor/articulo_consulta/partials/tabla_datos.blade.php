@php
    $sufijoUm = $sufijoUm ?? '';
    $puedeVerRecepcion = $puedeVerRecepcion ?? false;
@endphp
<table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
    <thead>
        <tr style="background:#85C1E9;color:#17202A;">
            <th style="width:8%">N&ordm; recep.</th>
            <th style="width:7%">Fecha</th>
            <th style="width:6%">Tipo</th>
            <th style="width:6%">OC</th>
            <th style="width:10%">SKU l&iacute;nea</th>
            <th class="text-right" style="width:8%">Cant.{!! $sufijoUm !!}</th>
            <th class="text-right" style="width:8%">Cant. stock</th>
            <th class="text-right" style="width:8%">Precio</th>
            <th style="width:14%">Proveedor</th>
            <th style="width:10%">Empresa</th>
            <th style="width:8%">Estado</th>
            @if ($puedeVerRecepcion)
            <th style="width:5%" data-orderable="false"></th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            @php
                $esBorrador = ($fila->estado_recepcion ?? '') === 'BORRADOR';
                $precioPendiente = ! empty($fila->fl_precio_pendiente_aprobacion);
            @endphp
            <tr class="@if($precioPendiente) table-info @elseif($esBorrador) table-secondary @elseif($fila->tiene_diff ?? false) table-warning @endif">
                <td class="text-monospace">
                    @if ($puedeVerRecepcion && ! empty($fila->url_consulta_recepcion))
                        <a href="{{ $fila->url_consulta_recepcion }}" class="text-primary" target="_blank" rel="noopener" title="Consultar recepci&oacute;n">
                            {{ $fila->numerorecepcion }}
                        </a>
                    @else
                        {{ $fila->numerorecepcion }}
                    @endif
                </td>
                <td>{{ $fila->fecha_fmt ?? '—' }}</td>
                <td>{{ $fila->tipo ?? '—' }}</td>
                <td class="text-monospace">{{ $fila->numeroordencompra ?? '—' }}</td>
                <td class="text-monospace small">{{ $fila->sku_linea ?? '—' }}</td>
                <td class="text-right text-monospace">{{ $fila->cantidad_fmt ?: '—' }}</td>
                <td class="text-right text-monospace">{{ $fila->cantidad_stock_fmt ?: '—' }}</td>
                <td class="text-right text-monospace">{{ $fila->precio_fmt ?: '—' }}</td>
                <td>{{ $fila->nombreproveedor ?? '—' }}</td>
                <td>{{ $fila->nombreempresa ?? '—' }}</td>
                <td>
                    @include('stock.recepcion_proveedor.partials.estado_badge', ['estado' => $fila->estado_recepcion ?? ''])
                </td>
                @if ($puedeVerRecepcion)
                <td class="text-nowrap">
                    @if (! empty($fila->url_consulta_recepcion))
                    <a href="{{ $fila->url_consulta_recepcion }}" class="btn-accion-tabla tooltipsC" title="Consultar recepci&oacute;n" target="_blank" rel="noopener">
                        <i class="fa fa-edit"></i>
                    </a>
                    @endif
                </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ $puedeVerRecepcion ? 12 : 11 }}" class="text-muted text-center">
                    Sin recepciones registradas para este art&iacute;culo.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
