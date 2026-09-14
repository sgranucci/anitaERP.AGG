@php
    $desfasajes = $desfasajesReparto ?? collect();
    $puedeEditarPedido = can('editar-pedidos', false) || can('listar-pedidos', false);
@endphp
@if ($desfasajes->isNotEmpty())
    <div class="card card-outline card-warning mt-3">
        <div class="card-header" style="background:#F9E79F;color:#17202A;">
            <h3 class="card-title">
                <i class="fa fa-exchange"></i>
                Cambi&aacute; el reparto en el ERP, no en Anita
            </h3>
        </div>
        <div class="card-body">
            <p class="mb-2">
                Estos pedidos <strong>ya est&aacute;n en el ERP</strong>. El certificado usa el transporte del pedido
                en <strong>Ventas &rarr; Pedido</strong>. Si el expreso se cambi&oacute; solo en Anita, ac&aacute; no entra
                al reparto nuevo y sigue figurando en el del ERP.
            </p>
            <p class="mb-3">
                Abr&iacute; el pedido, cambi&aacute; el transporte y volv&eacute; a <strong>Consultar pedidos</strong>.
            </p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Reparto ERP</th>
                            <th>Reparto Anita</th>
                            <th>ABM</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($desfasajes as $desfasaje)
                            <tr>
                                <td class="text-nowrap">{{ $desfasaje->codigoPedido }}</td>
                                <td>{{ trim($desfasaje->codigoCliente.' '.$desfasaje->clienteNombre) }}</td>
                                <td class="text-nowrap">{{ $desfasaje->repartoErp }}</td>
                                <td class="text-nowrap">{{ $desfasaje->repartoAnita }}</td>
                                <td class="text-nowrap">
                                    @if ($desfasaje->pedidoId && $puedeEditarPedido)
                                        <a class="text-primary"
                                            href="{{ route('editar_pedido', $desfasaje->pedidoId) }}?origen=modal_consulta&amp;vista=consulta"
                                            target="_blank" rel="noopener">
                                            <i class="fa fa-edit"></i> Editar pedido
                                        </a>
                                    @elseif ($desfasaje->pedidoId)
                                        Pedido id {{ $desfasaje->pedidoId }}
                                    @else
                                        —
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
