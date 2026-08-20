@php
    $omitidos = $omitidosSinSenasa ?? collect();
    $puedeAbrirArticulo = \App\Support\Stock\ArticuloConsultaDesdeModal::puedeConsultar();
@endphp
@if ($omitidos->isNotEmpty())
    <div class="card card-outline card-danger mt-3">
        <div class="card-header bg-danger">
            <h3 class="card-title">
                <i class="fa fa-exclamation-triangle"></i>
                No genere el certificado: hay art&iacute;culos sin c&oacute;digo SENASA
            </h3>
        </div>
        <div class="card-body">
            <p class="mb-2">
                Estos art&iacute;culos est&aacute;n en los pedidos consultados pero <strong>no van a entrar</strong> al certificado
                porque no tienen c&oacute;digo SENASA cargado. Si genera ahora, el XML queda incompleto.
            </p>
            <p class="mb-3">
                Abra el ABM de cada art&iacute;culo, cargue el c&oacute;digo SENASA y vuelva a <strong>Consultar pedidos</strong>.
            </p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>SKU</th>
                            <th>Art&iacute;culo</th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Origen</th>
                            <th>ABM</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($omitidos as $omitido)
                            <tr>
                                <td class="text-nowrap">{{ $omitido->sku }}</td>
                                <td>{{ $omitido->articuloNombre !== '' ? $omitido->articuloNombre : '—' }}</td>
                                <td class="text-nowrap">{{ $omitido->codigoPedido }}</td>
                                <td>{{ trim($omitido->codigoCliente.' '.$omitido->clienteNombre) }}</td>
                                <td>{{ strtoupper($omitido->origen) }}</td>
                                <td class="text-nowrap">
                                    @if ($omitido->articuloId && $puedeAbrirArticulo)
                                        <a class="text-primary"
                                            href="{{ \App\Support\Stock\ArticuloConsultaDesdeModal::urlEditar((int) $omitido->articuloId) }}"
                                            target="_blank" rel="noopener">
                                            <i class="fa fa-edit"></i> Cargar c&oacute;digo SENASA
                                        </a>
                                    @elseif ($omitido->articuloId)
                                        Art&iacute;culo id {{ $omitido->articuloId }} (sin permiso para abrir el ABM)
                                    @else
                                        No est&aacute; en el ERP: d&eacute; de alta el art&iacute;culo y asigne SENASA
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
