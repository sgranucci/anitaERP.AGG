@php
    $req = $requisicion_sala;
    $mov = $movimiento;
@endphp
<div class="card portal-card mb-3">
    <div class="card-header bg-info">
        <h1 class="card-title mb-0 h5 text-white">Requisición de sala {{ $req->numerorequisicion ?? '—' }}</h1>
        <small class="text-white-50 d-block pl-3 mt-1">Nivel de aprobación: {{ $mov->nivel ?? '—' }}</small>
    </div>
    <div class="card-body p-0">
        <dl class="row kv mb-0 px-3 py-3">
            <dt class="col-sm-4">Empresa</dt>
            <dd class="col-sm-8">{{ $req->empresas->nombre ?? '—' }}</dd>
            <dt class="col-sm-4">Solicitante</dt>
            <dd class="col-sm-8">{{ optional($req->solicitante)->nombre ?? optional($req->usuarios)->nombre ?? '—' }}</dd>
            <dt class="col-sm-4">Centro de costo</dt>
            <dd class="col-sm-8">{{ trim(($req->centrocostos->codigo ?? '').' '.($req->centrocostos->nombre ?? '')) ?: '—' }}</dd>
            <dt class="col-sm-4">Depósito</dt>
            <dd class="col-sm-8">{{ optional($req->depositos)->nombre ?? '—' }}</dd>
            <dt class="col-sm-4">Zona de sala</dt>
            <dd class="col-sm-8">{{ optional($req->zona_salas)->nombre ?? '—' }}</dd>
            <dt class="col-sm-4">Prioridad</dt>
            <dd class="col-sm-8">{{ optional($req->prioridad_salas)->nombre ?? '—' }}</dd>
            <dt class="col-sm-4">Fecha / entrega</dt>
            <dd class="col-sm-8">
                {{ $req->fecha ? date('d/m/Y', strtotime($req->fecha)) : '—' }}
                /
                {{ $req->fecha_entrega ? date('d/m/Y', strtotime($req->fecha_entrega)) : '—' }}
            </dd>
            <dt class="col-sm-4">Estado actual</dt>
            <dd class="col-sm-8">{{ $req->estado ?? '—' }}</dd>
            <dt class="col-sm-4">Monto ítems</dt>
            <dd class="col-sm-8">{{ number_format((float) ($monto_items ?? 0), 2, ',', '.') }}</dd>
            @if(($modoPortal ?? '') !== 'rechazo')
            <dt class="col-sm-4">Tras aprobar este paso</dt>
            <dd class="col-sm-8">@if(!empty($estado_tras_aprobar))<strong>{{ $estado_tras_aprobar }}</strong>@else<span class="text-muted">Sin cambio de estado definido en el árbol (solo avanza el flujo).</span>@endif</dd>
            @endif
            <dt class="col-sm-4">Comentario</dt>
            <dd class="col-sm-8">{{ $req->comentario !== null && $req->comentario !== '' ? $req->comentario : '—' }}</dd>
            <dt class="col-sm-4">Detalle</dt>
            <dd class="col-sm-8 text-break">{{ $req->detalle !== null && $req->detalle !== '' ? $req->detalle : '—' }}</dd>
        </dl>
        <div class="px-3 pb-3">
            <a href="{{ route('visualizar_requisicion_sala', ['id' => $req->id, 'hash' => $mov->hashvisualizar]) }}" class="btn btn-outline-secondary btn-sm btn-block mb-2" target="_blank" rel="noopener noreferrer">
                <i class="fa fa-eye"></i> Ver requisición completa (solo lectura)
            </a>
        </div>
        <div class="table-responsive border-top">
            <table class="table table-sm table-striped mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Artículo</th>
                        <th class="text-right">Cant.</th>
                        <th>F/S</th>
                        <th>UID</th>
                        <th>Nº parte</th>
                        <th>Destino</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($req->requisicion_sala_articulos ?? [] as $linea)
                    <tr>
                        <td>
                            <small>{{ optional($linea->articulos)->sku ?? '' }}</small>
                            <div class="text-break small">{{ optional($linea->articulos)->descripcion ?? '' }}</div>
                        </td>
                        <td class="text-right text-nowrap">{{ $linea->cantidad ?? '' }}</td>
                        <td>{{ $linea->fueradeservicio ?? 'N' }}</td>
                        <td><small>{{ $linea->uid ?? '' }}</small></td>
                        <td><small>{{ $linea->numeroparte ?? '' }}</small></td>
                        <td><small>{{ \App\Models\Sala\RequisicionSalaArticulo::destinoNombrePorValor(trim($linea->destino ?? '')) }}</small></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted small">Sin ítems</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
