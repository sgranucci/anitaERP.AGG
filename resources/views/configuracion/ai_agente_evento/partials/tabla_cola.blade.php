@php
    use App\Support\Ai\AiAgenteEventoHitlSupport;
    $coleccion = $coleccion ?? collect();
    $mostrarAcciones = $mostrarAcciones ?? true;
@endphp
<div class="table-responsive">
    <table class="table table-sm table-striped mb-0" id="tabla-ai-agente-evento">
        <thead style="background:#85C1E9;color:#17202A">
            <tr>
                <th style="width:60px">ID</th>
                <th style="width:80px">Sev.</th>
                <th style="width:100px">Estado</th>
                <th style="width:150px">Evento</th>
                <th>Resumen / plan</th>
                <th style="width:120px">Cuándo</th>
                @if ($mostrarAcciones)
                    <th style="width:210px">Acciones HITL</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($coleccion as $ev)
                @php
                    $sev = (string) ($ev->severidad ?? 'media');
                    $badge = $sev === 'alta' ? 'badge-danger' : ($sev === 'baja' ? 'badge-secondary' : 'badge-warning');
                    $pasos = is_array($ev->plan_json['pasos'] ?? null) ? $ev->plan_json['pasos'] : [];
                    $url = AiAgenteEventoHitlSupport::urlEntidad($ev);
                    $cerrado = in_array($ev->estado, ['descartado', 'resuelto'], true);
                @endphp
                <tr data-evento-id="{{ $ev->id }}" data-estado="{{ $ev->estado }}">
                    <td>{{ $ev->id }}</td>
                    <td><span class="badge {{ $badge }}">{{ $sev }}</span></td>
                    <td class="js-estado-evento"><span class="badge badge-info">{{ $ev->estado }}</span></td>
                    <td>
                        <code>{{ $ev->evento }}</code><br>
                        <span class="text-muted small">{{ $ev->origen }}</span>
                        @if ($url)
                            <br><a class="text-primary small" href="{{ $url }}" target="_blank" rel="noopener">Abrir entidad</a>
                        @endif
                    </td>
                    <td>
                        {{ $ev->resumen }}
                        @if ($pasos !== [])
                            <ul class="mb-0 pl-3 small mt-1">
                                @foreach (array_slice($pasos, 0, 4) as $paso)
                                    <li>{{ $paso['etiqueta'] ?? ($paso['frase'] ?? '') }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td class="small">{{ optional($ev->created_at)->format('d/m/Y H:i') }}</td>
                    @if ($mostrarAcciones)
                        <td class="js-acciones-evento">
                            @if (! $cerrado)
                                <div class="btn-group btn-group-sm">
                                    @if ($ev->estado === 'pendiente')
                                        <button type="button" class="btn btn-outline-secondary js-hitl-visto" title="Marcar visto">Visto</button>
                                    @endif
                                    <button type="button" class="btn btn-outline-danger js-hitl-descartar" title="Descartar">Descartar</button>
                                    <button type="button" class="btn btn-outline-success js-hitl-resolver" title="Marcar resuelto">Resuelto</button>
                                </div>
                            @else
                                <span class="text-muted small">Cerrado</span>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $mostrarAcciones ? 7 : 6 }}" class="text-muted">Sin eventos en la cola.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
