@extends("theme.$theme.layout")
@section('titulo')
Préstamo {{ $prestamo->codigo }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-handshake-o"></i>
                    Préstamo {{ $prestamo->codigo }}
                    @include('stock.prestamo.partials.estado_badge', ['estado' => $prestamo->estado])
                </h3>
                <div class="card-tools">
                    <a href="{{ route('prestamo') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-5">Solicitante</dt>
                            <dd class="col-sm-7">{{ optional($prestamo->solicitante)->nombre }}</dd>
                            <dt class="col-sm-5">Depósito origen</dt>
                            <dd class="col-sm-7">{{ optional($prestamo->depositoOrigen)->nombre }}</dd>
                            <dt class="col-sm-5">Depósito destino</dt>
                            <dd class="col-sm-7">{{ optional($prestamo->depositoDestino)->nombre }}</dd>
                            <dt class="col-sm-5">Aprobador / receptor</dt>
                            <dd class="col-sm-7">{{ optional($prestamo->aprobador)->nombre ?? '—' }}</dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-5">Fecha del préstamo</dt>
                            <dd class="col-sm-7">{{ optional($prestamo->fecha_prestamo)->format('d/m/Y') }}</dd>
                            <dt class="col-sm-5">Devolución prometida</dt>
                            <dd class="col-sm-7">
                                @php $vencido = $prestamo->estaVencido(); @endphp
                                <span @if ($vencido) class="text-danger" @endif>
                                    {{ optional($prestamo->fecha_devolucion_prometida)->format('d/m/Y') }}
                                    @if ($vencido)
                                        <i class="fa fa-exclamation-circle"></i> Vencido
                                    @endif
                                </span>
                            </dd>
                            <dt class="col-sm-5">Fecha aprobación</dt>
                            <dd class="col-sm-7">{{ $prestamo->fecha_aprobacion ? $prestamo->fecha_aprobacion->format('d/m/Y') : '—' }}</dd>
                            <dt class="col-sm-5">Fecha devolución real</dt>
                            <dd class="col-sm-7">{{ $prestamo->fecha_devolucion_real ? $prestamo->fecha_devolucion_real->format('d/m/Y') : '—' }}</dd>
                        </dl>
                    </div>
                </div>

                @if (! empty($prestamo->observaciones))
                    <div class="alert alert-light border">
                        <strong>Observaciones:</strong> {{ $prestamo->observaciones }}
                    </div>
                @endif
                @if (! empty($prestamo->motivo_rechazo))
                    <div class="alert alert-warning">
                        <strong>Motivo rechazo:</strong> {{ $prestamo->motivo_rechazo }}
                    </div>
                @endif

                <h4>Ítems</h4>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Descripción</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Devuelto</th>
                            <th class="text-right">Pendiente</th>
                            <th>Observ.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($prestamo->items as $item)
                            <tr>
                                <td>{{ optional($item->articulos)->sku }}</td>
                                <td>{{ optional($item->articulos)->descripcion }}</td>
                                <td class="text-right">{{ rtrim(rtrim(number_format((float) $item->cantidad, 6, '.', ''), '0'), '.') }}</td>
                                <td class="text-right">{{ rtrim(rtrim(number_format((float) $item->cantidad_devuelta, 6, '.', ''), '0'), '.') }}</td>
                                <td class="text-right">{{ rtrim(rtrim(number_format(max(0, (float) $item->cantidad - (float) $item->cantidad_devuelta), 6, '.', ''), '0'), '.') }}</td>
                                <td>{{ $item->observaciones }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <h4 class="mt-4">Historial</h4>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>De</th>
                            <th>A</th>
                            <th>Usuario</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($prestamo->estados as $log)
                            <tr>
                                <td>{{ $log->ocurrio_el ? $log->ocurrio_el->format('d/m/Y H:i') : '' }}</td>
                                <td>{{ $log->estado_anterior ?? '—' }}</td>
                                <td>{{ $log->estado_nuevo }}</td>
                                <td>{{ optional($log->usuarios)->nombre }}</td>
                                <td>{{ $log->observaciones }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center">Sin movimientos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                @if ($prestamo->estado === 'PENDIENTE_APROBACION')
                    @if (can('aprobar-recepcion-prestamo', false))
                        <form action="{{ route('aprobar_prestamo', ['id' => $prestamo->id]) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-check"></i> Aprobar recepción
                            </button>
                        </form>
                    @endif
                    @if (can('rechazar-recepcion-prestamo', false))
                        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#modal-rechazo">
                            <i class="fa fa-times"></i> Rechazar
                        </button>
                    @endif
                    @if (can('reenviar-correo-prestamo', false))
                        <form action="{{ route('reenviar_correo_prestamo', ['id' => $prestamo->id]) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">
                                <i class="fa fa-paper-plane"></i> Reenviar correo
                            </button>
                        </form>
                    @endif
                @endif

                @if ($prestamo->estaPendienteDevolucion() && can('devolver-prestamo', false))
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modal-devolucion">
                        <i class="fa fa-undo"></i> Registrar devolución
                    </button>
                @endif

                @if (in_array($prestamo->estado, ['BORRADOR', 'PENDIENTE_APROBACION'], true) && can('cancelar-prestamo', false))
                    <button type="button" class="btn btn-outline-dark" data-toggle="modal" data-target="#modal-cancelar">
                        <i class="fa fa-ban"></i> Cancelar préstamo
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

@if (can('rechazar-recepcion-prestamo', false))
<div class="modal fade" id="modal-rechazo" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <form action="{{ route('rechazar_prestamo', ['id' => $prestamo->id]) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rechazar recepción</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Al rechazar, se reversa la salida del depósito origen.</p>
                    <textarea name="motivo_rechazo" class="form-control" rows="3" placeholder="Motivo del rechazo (opcional)"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Rechazar</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

@if ($prestamo->estaPendienteDevolucion() && can('devolver-prestamo', false))
<div class="modal fade" id="modal-devolucion" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('devolver_prestamo', ['id' => $prestamo->id]) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar devolución</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Descripción</th>
                                <th class="text-right">Pendiente</th>
                                <th class="text-right">A devolver</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($prestamo->items as $idx => $item)
                                @php $pendiente = max(0, (float) $item->cantidad - (float) $item->cantidad_devuelta); @endphp
                                @if ($pendiente > 0)
                                    <tr>
                                        <td>{{ optional($item->articulos)->sku }}</td>
                                        <td>{{ optional($item->articulos)->descripcion }}</td>
                                        <td class="text-right">{{ rtrim(rtrim(number_format($pendiente, 6, '.', ''), '0'), '.') }}</td>
                                        <td>
                                            <input type="hidden" name="devoluciones[{{ $idx }}][prestamo_item_id]" value="{{ $item->id }}">
                                            <input type="number" step="0.000001" min="0" max="{{ $pendiente }}"
                                                name="devoluciones[{{ $idx }}][cantidad]" class="form-control text-right" value="0">
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                    <textarea name="observaciones" class="form-control" rows="2" placeholder="Observaciones de la devolución"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar devolución</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

@if (in_array($prestamo->estado, ['BORRADOR', 'PENDIENTE_APROBACION'], true) && can('cancelar-prestamo', false))
<div class="modal fade" id="modal-cancelar" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <form action="{{ route('cancelar_prestamo', ['id' => $prestamo->id]) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancelar préstamo</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Si el préstamo ya generó la salida del depósito origen, será revertida automáticamente.</p>
                    <textarea name="motivo" class="form-control" rows="2" placeholder="Motivo (opcional)"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-warning">Cancelar préstamo</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
