@php
    use App\Support\Solicitudpago\SolicitudpagoEstados;
    $pagos = $pagos_caja ?? ($data->cajaMovimientosPago ?? collect());
    $puedeVerIe = can('listar-ingresos-egresos-caja', false) || can('editar-ingresos-egresos-caja', false);
@endphp
@if (($data->estado ?? '') === SolicitudpagoEstados::PAGADA || $pagos->count() > 0)
    <div class="card card-outline card-info mb-3">
        <div class="card-header py-2">
            <h3 class="card-title mb-0">
                <i class="fa fa-money mr-1"></i> Pagos (órdenes de caja)
            </h3>
            @if ($puedeVerIe && $pagos->count() > 0)
                <div class="card-tools">
                    <a href="{{ route('ingresoegreso', ['solicitudpago_id' => $data->id, 'empresa_todas' => 1]) }}"
                       class="btn btn-outline-info btn-sm"
                       target="_blank" rel="noopener"
                       title="Abrir listado de ingresos/egresos filtrado por esta SP">
                        <i class="fa fa-list"></i> Listar en caja
                    </a>
                </div>
            @endif
        </div>
        <div class="card-body p-0">
            @if ($pagos->isEmpty())
                <p class="text-muted mb-0 p-3">
                    Estado PAGADA sin orden de pago vinculada (pudo marcarse manualmente).
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>ID</th>
                                <th>Tipo</th>
                                <th>Número</th>
                                <th>Fecha</th>
                                <th>Detalle</th>
                                <th class="width80"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pagos as $pago)
                                <tr>
                                    <td>{{ $pago->id }}</td>
                                    <td>
                                        {{ $pago->tipotransaccioncajas->abreviatura ?? '' }}
                                        {{ $pago->tipotransaccioncajas->nombre ?? '' }}
                                    </td>
                                    <td>{{ $pago->numerotransaccion }}</td>
                                    <td>{{ $pago->fecha ? date('d/m/Y', strtotime($pago->fecha)) : '' }}</td>
                                    <td>{{ $pago->detalle }}</td>
                                    <td class="text-nowrap">
                                        @if ($puedeVerIe)
                                            <a href="{{ route('editar_ingresoegreso', ['id' => $pago->id, 'origen' => 'solicitudpago']) }}"
                                               class="btn-accion-tabla tooltipsC text-primary"
                                               title="Abrir orden de pago"
                                               target="_blank" rel="noopener">
                                                <i class="fa fa-external-link-alt"></i>
                                            </a>
                                            <a href="{{ route('imprimir_ingresoegreso', $pago->id) }}"
                                               class="btn-accion-tabla tooltipsC"
                                               title="PDF orden de pago"
                                               target="_blank" rel="noopener">
                                                <i class="fa fa-print"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endif
