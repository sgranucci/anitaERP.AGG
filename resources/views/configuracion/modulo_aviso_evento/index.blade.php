@extends("theme.$theme.layout")
@section('titulo')
Suscripciones de eventos
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fa fa-bolt"></i> Evento de dominio → tipo de aviso</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_modulo_aviso') }}" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-reply-all"></i> Avisos
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Cuando el ERP emite un evento (recepción confirmada, préstamo, etc.), se disparan los avisos suscriptos.
                    No reemplaza los envíos directos ya cableados: suma avisos extra.
                </p>

                <form method="post" action="{{ route('guardar_modulo_aviso_evento') }}" class="mb-4" data-enter-campos="1">
                    @csrf
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4">
                            <label for="evento">Evento</label>
                            <select name="evento" id="evento" class="form-control" required>
                                @foreach ($catalogo as $clave => $etiqueta)
                                    <option value="{{ $clave }}" @if(old('evento')===$clave) selected @endif>{{ $etiqueta }} ({{ $clave }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="modulo_aviso_tipo_id">Tipo de aviso</label>
                            <select name="modulo_aviso_tipo_id" id="modulo_aviso_tipo_id" class="form-control" required>
                                <option value="">—</option>
                                @foreach ($tipos as $tipo)
                                    <option value="{{ $tipo->id }}" @if((int)old('modulo_aviso_tipo_id')===$tipo->id) selected @endif>
                                        {{ $tipo->modulo }}/{{ $tipo->codigo }} — {{ $tipo->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <div class="form-check mt-4">
                                <input type="hidden" name="activo" value="0">
                                <input type="checkbox" class="form-check-input" name="activo" id="activo" value="1" checked>
                                <label class="form-check-label" for="activo">Activa</label>
                            </div>
                        </div>
                        <div class="form-group col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm btn-block">Agregar</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="condiciones_json">Condiciones JSON (opcional)</label>
                        <input type="text" class="form-control" name="condiciones_json" id="condiciones_json"
                            value="{{ old('condiciones_json') }}" placeholder='{"empresa_id":1}'>
                    </div>
                </form>

                <table class="table table-sm table-bordered">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Evento</th>
                            <th>Aviso</th>
                            <th>Activo</th>
                            <th>Condiciones</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subs as $sub)
                            <tr>
                                <td><code>{{ $sub->evento }}</code></td>
                                <td>
                                    @if ($sub->tipo)
                                        {{ $sub->tipo->modulo }}/{{ $sub->tipo->codigo }}
                                        <span class="text-muted small">— {{ $sub->tipo->nombre }}</span>
                                    @else
                                        #{{ $sub->modulo_aviso_tipo_id }}
                                    @endif
                                </td>
                                <td>{{ $sub->activo ? 'Sí' : 'No' }}</td>
                                <td class="small"><code>{{ $sub->condiciones ? json_encode($sub->condiciones) : '—' }}</code></td>
                                <td class="text-right">
                                    <form method="post" action="{{ route('eliminar_modulo_aviso_evento', $sub->id) }}" class="d-inline"
                                        onsubmit="return confirm('¿Eliminar suscripción?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-accion-tabla" title="Eliminar">
                                            <i class="fas fa-times-circle text-danger"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">Sin suscripciones.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <hr class="my-4">
                <h5>Cobertura: quién recibe qué</h5>
                <p class="text-muted small">
                    Eventos del catálogo vs emisión en código vs suscripciones activas.
                    Los envíos directos del módulo siguen existiendo; el bus suma avisos extra.
                </p>
                <table class="table table-sm table-bordered">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Evento</th>
                            <th>Emit en código</th>
                            <th class="text-right">Suscripciones</th>
                            <th>Tipos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($cobertura_eventos ?? []) as $c)
                            <tr>
                                <td>
                                    <strong>{{ $c['etiqueta'] }}</strong><br>
                                    <code class="small">{{ $c['evento'] }}</code>
                                </td>
                                <td>{{ $c['emit_codigo'] ? 'Sí' : 'No / solo cron' }}</td>
                                <td class="text-right">{{ $c['suscripciones_activas'] }}</td>
                                <td class="small">{{ $c['tipos'] ? implode(', ', $c['tipos']) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <h5 class="mt-4">Envíos directos documentados</h5>
                <ul class="small text-muted">
                    @foreach (($envios_directos ?? []) as $d)
                        <li><strong>{{ $d['modulo'] }}</strong> — {{ $d['nota'] }}</li>
                    @endforeach
                </ul>

                <h5 class="mt-4">Tipos activos y destinatarios</h5>
                <table class="table table-sm table-bordered">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Tipo</th>
                            <th>Nombre</th>
                            <th class="text-right">Destinatarios</th>
                            <th>Mail</th>
                            <th>In-app</th>
                            <th>WA</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($resumen_tipos ?? []) as $t)
                            <tr class="{{ $t['destinatarios'] === 0 ? 'table-warning' : '' }}">
                                <td><code>{{ $t['clave'] }}</code></td>
                                <td>{{ $t['nombre'] }}</td>
                                <td class="text-right">{{ $t['destinatarios'] }}</td>
                                <td>{{ $t['mail'] ? 'Sí' : '—' }}</td>
                                <td>{{ $t['in_app'] ? 'Sí' : '—' }}</td>
                                <td>{{ $t['whatsapp'] ? 'Sí' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">Sin tipos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
