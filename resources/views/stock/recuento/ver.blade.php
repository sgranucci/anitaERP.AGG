@extends("theme.$theme.layout")
@section('titulo')
Recuento {{ $recuento->codigo }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-clipboard"></i>
                    Recuento {{ $recuento->codigo }}
                    @include('stock.recuento.partials.estado_badge', ['estado' => $recuento->estado])
                </h3>
                <div class="card-tools">
                    @include('includes.stock.boton-manual')
                    @include('stock.recuento.partials.botones_exportar', ['recuento' => $recuento])
                    @if ($recuento->esEditable() && can('editar-recuento', false))
                    <a href="{{ route('editar_recuento', ['id' => $recuento->id]) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-edit"></i> Editar
                    </a>
                    @endif
                    <a href="{{ route('recuento') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-5">Fecha</dt>
                            <dd class="col-sm-7">{{ optional($recuento->fecha)->format('d/m/Y') }}</dd>
                            <dt class="col-sm-5">Depósito</dt>
                            <dd class="col-sm-7">{{ optional($recuento->deposito)->etiqueta() }}</dd>
                            <dt class="col-sm-5">Empresa</dt>
                            <dd class="col-sm-7">{{ optional($recuento->empresa)->nombre }}</dd>
                            <dt class="col-sm-5">Usuario</dt>
                            <dd class="col-sm-7">{{ optional($recuento->usuario)->nombre }}</dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-5">Tipo</dt>
                            <dd class="col-sm-7">{{ $recuento->tipo }}</dd>
                            <dt class="col-sm-5">Mov. cierre</dt>
                            <dd class="col-sm-7">{{ $recuento->movimientostock_cierre_id ?? '—' }}</dd>
                            <dt class="col-sm-5">Mov. anulación</dt>
                            <dd class="col-sm-7">{{ $recuento->movimientostock_anulacion_id ?? '—' }}</dd>
                            @if ($recuento->modo_cierre)
                            <dt class="col-sm-5">Modo de cierre</dt>
                            <dd class="col-sm-7">{{ \App\Support\Stock\RecuentoModoCierreSupport::etiqueta($recuento->modo_cierre) }}</dd>
                            @endif
                        </dl>
                    </div>
                </div>

                @if (! empty($recuento->comentario))
                    <div class="alert alert-light border">
                        <strong>Comentario:</strong> {{ $recuento->comentario }}
                    </div>
                @endif

                <h4>Líneas de conteo</h4>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Descripción</th>
                            <th>UM</th>
                            <th class="text-right">Saldo sistema</th>
                            <th class="text-right">Contado</th>
                            <th class="text-right">Diferencia a ajustar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recuento->items as $item)
                            @php $dif = $item->diferencia(); @endphp
                            <tr>
                                <td>
                                    @if (can('editar-articulos', false))
                                    <a href="{{ route('editar_articulo', ['id' => $item->articulo_id]) }}" target="_blank">{{ optional($item->articulos)->sku }}</a>
                                    @else
                                    {{ optional($item->articulos)->sku }}
                                    @endif
                                </td>
                                <td>{{ $item->detalle ?: optional($item->articulos)->descripcion }}</td>
                                <td>{{ optional($item->unidadmedida)->abreviatura ?? optional($item->articulos?->unidadesdemedidas)->abreviatura }}</td>
                                <td class="text-right">{{ rtrim(rtrim(number_format((float) $item->saldo_sistema, 6, '.', ''), '0'), '.') }}</td>
                                <td class="text-right">{{ rtrim(rtrim(number_format((float) $item->cantidad_contada, 6, '.', ''), '0'), '.') }}</td>
                                <td class="text-right @if (abs($dif) > 1e-9) text-danger font-weight-bold @endif">
                                    {{ rtrim(rtrim(number_format($dif, 6, '.', ''), '0'), '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="small text-muted">
                    La columna &laquo;Diferencia a ajustar&raquo; usa el saldo guardado al cargar cada l&iacute;nea.
                    Al cerrar, el ajuste real depende del modo elegido abajo (fecha del recuento o saldo actual).
                </p>

                <h4 class="mt-4">Historial de estados</h4>
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr><th>Fecha</th><th>Anterior</th><th>Nuevo</th><th>Usuario</th><th>Observación</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($recuento->estados as $hist)
                            <tr>
                                <td>{{ optional($hist->ocurrio_el)->format('d/m/Y H:i') }}</td>
                                <td>{{ $hist->estado_anterior ? \App\Models\Stock\Recuento::etiquetaEstado($hist->estado_anterior) : '—' }}</td>
                                <td>{{ \App\Models\Stock\Recuento::etiquetaEstado($hist->estado_nuevo) }}</td>
                                <td>{{ optional($hist->usuarios)->nombre }}</td>
                                <td>{{ $hist->observaciones }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($recuento->archivos->count())
                    <h4 class="mt-4">Archivos</h4>
                    @include('stock.recuento.partials.archivos_adjuntos', ['data' => $recuento, 'ocultarInputsConservar' => true])
                @endif

                @include('stock.recuento.partials.opciones_cierre', ['recuento' => $recuento])

                <div class="mt-2">
                    @if ($recuento->estado === 'PENDIENTE' && can('suspender-recuento', false))
                    <form action="{{ route('suspender_recuento', ['id' => $recuento->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Suspender este recuento?');">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="fa fa-pause"></i> Suspender</button>
                    </form>
                    @endif

                    @if ($recuento->estado === 'SUSPENDIDO' && can('reactivar-recuento', false))
                    <form action="{{ route('reactivar_recuento', ['id' => $recuento->id]) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm"><i class="fa fa-play"></i> Reactivar</button>
                    </form>
                    @endif

                    @if (in_array($recuento->estado, ['PENDIENTE', 'SUSPENDIDO']) && can('anular-recuento', false))
                    <form action="{{ route('anular_recuento', ['id' => $recuento->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Anular este recuento?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa fa-ban"></i> Anular</button>
                    </form>
                    @endif

                    @if ($recuento->estaCerrado() && can('anular-cierre-recuento', false))
                    <form action="{{ route('anular_cierre_recuento', ['id' => $recuento->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Anular el cierre? Se revertirán los movimientos de stock y el recuento volverá a PENDIENTE.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning btn-sm"><i class="fa fa-undo"></i> Anular cierre</button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
