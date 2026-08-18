<form method="post" action="{{ route('guardar_suscripcion_reporte_sueldos_definible', ['id' => $data->id]) }}" class="card card-outline card-info mb-3" id="form-suscripcion-rsd">
    @csrf
    <input type="hidden" name="suscripcion_id" id="suscripcion_id" value="">
    <div class="card-body">
        <div class="form-row">
            <div class="form-group col-md-3"><label>Nombre</label><input name="nombre" id="sus_nombre" class="form-control form-control-sm" maxlength="100" required></div>
            <div class="form-group col-md-3"><label>Email principal</label><input type="email" name="email" id="sus_email" class="form-control form-control-sm" placeholder="email@" required></div>
            <div class="form-group col-md-3"><label>Otros emails</label><input name="destinatarios" id="sus_destinatarios" class="form-control form-control-sm" placeholder="separados por coma"></div>
            <div class="form-group col-md-1"><label>Formato</label><select name="formato" id="sus_formato" class="form-control form-control-sm"><option>PDF</option><option>EXCEL</option><option>CSV</option></select></div>
            <div class="form-group col-md-2"><label>Segmentación</label><select name="burst_dimension" id="sus_burst_dimension" class="form-control form-control-sm">@foreach($dimensionesBurst as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                <small class="form-text text-muted">Empleado: un mail al email del legajo (sin reenviar el email principal). Manager/organigrama no disponible.</small>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-2"><label>Frecuencia</label><select name="periodicidad" id="sus_periodicidad" class="form-control form-control-sm">@foreach($periodicidades as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="form-group col-md-1"><label>Día mes</label><input type="number" name="dia_mes" id="sus_dia_mes" min="1" max="28" value="5" class="form-control form-control-sm"></div>
            <div class="form-group col-md-1"><label>Día sem.</label><input type="number" name="dia_semana" id="sus_dia_semana" min="1" max="7" value="1" class="form-control form-control-sm"></div>
            <div class="form-group col-md-1"><label>Hora</label><input type="time" name="hora" id="sus_hora" value="07:00" class="form-control form-control-sm"></div>
            <div class="form-group col-md-2">
                <label>Período</label>
                <select name="periodo_relativo" id="sus_periodo_relativo" class="form-control form-control-sm periodo-relativo-suscripcion">
                    <option value="ultima_liquidacion">Última liquidación</option>
                    <option value="fijo">Liquidación fija guardada</option>
                </select>
            </div>
            <div class="form-group col-md-3 d-none" data-liquidacion-fija-container>
                @include('sueldos.partials.campo_consulta_liquidacion_sueldos', [
                    'inputName' => 'liquidacion_id',
                    'inputId' => 'suscripcion_liquidacion_id',
                    'label' => 'Liquidación fija',
                ])
            </div>
            <div class="form-group col-md-2"><label>Mensaje</label><input name="mensaje" id="sus_mensaje" class="form-control form-control-sm" maxlength="2000"></div>
        </div>
        <div class="custom-control custom-checkbox custom-control-inline">
            <input type="checkbox" class="custom-control-input" id="sus-publicar" name="publicar" value="1" checked>
            <label class="custom-control-label" for="sus-publicar">Publicar snapshot inmutable</label>
        </div>
        <div class="custom-control custom-checkbox custom-control-inline">
            <input type="checkbox" class="custom-control-input" id="sus-solo-alertas" name="solo_si_alertas" value="1">
            <label class="custom-control-label" for="sus-solo-alertas">Enviar solo si hay alertas</label>
        </div>
        <button type="submit" class="btn btn-outline-primary btn-sm ml-2" id="sus-submit">Guardar distribución</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="sus-cancelar-edicion">Nueva</button>
    </div>
</form>
<p class="small text-muted mb-2">
    Bursting por empleado/manager no está disponible: el ERP no tiene organigrama Workday.
</p>
<ul class="list-group">
    @forelse ($suscripciones as $s)
        @php
            $filtrosSus = is_array($s->filtros_default) ? $s->filtros_default : [];
            $liqFija = (int) ($filtrosSus['liquidacion_id'] ?? 0);
            $liqFijaModel = ($liquidacionesFijas ?? collect())[$liqFija] ?? null;
            $envios = $enviosPorSuscripcion[$s->id] ?? collect();
            $payloadEdicion = [
                'id' => (int) $s->id,
                'nombre' => (string) $s->nombre,
                'email' => (string) $s->email,
                'destinatarios' => (string) $s->destinatarios,
                'formato' => (string) $s->formato,
                'burst_dimension' => (string) $s->burst_dimension,
                'periodicidad' => (string) $s->periodicidad,
                'dia_mes' => (int) $s->dia_mes,
                'dia_semana' => (int) $s->dia_semana,
                'hora' => substr((string) $s->hora, 0, 5),
                'periodo_relativo' => (string) $s->periodo_relativo,
                'liquidacion_id' => $liqFija,
                'liquidacion_numero' => (string) ($liqFijaModel->numero ?? ''),
                'liquidacion_descripcion' => (string) ($liqFijaModel->descripcion ?? ''),
                'mensaje' => (string) ($s->mensaje ?? ''),
                'publicar' => (bool) $s->publicar,
                'solo_si_alertas' => (bool) $s->solo_si_alertas,
            ];
        @endphp
        <li class="list-group-item">
            <div class="d-flex justify-content-between">
                <span>
                    <strong>{{ $s->nombre ?: $s->email }}</strong> · {{ $s->formato }}
                    · {{ $s->periodicidad }} {{ $s->hora }}
                    · {{ $dimensionesBurst[$s->burst_dimension] ?? $s->burst_dimension }}
                    @if(($s->periodo_relativo ?? '') === 'fijo' && $liqFija > 0)
                        <br><small class="text-muted">Liquidación fija {{ $liqFijaModel->numero ?? ('#'.$liqFija) }} {{ $liqFijaModel->descripcion ?? '' }}</small>
                    @endif
                    @if($s->ultimo_estado)<br><small class="text-muted">Última: {{ $s->ultimo_estado }} — {{ $s->ultimo_mensaje }}</small>@endif
                </span>
                <div class="text-right">
                    <button type="button" class="btn btn-sm btn-outline-info mb-1 rsd-editar-suscripcion"
                            data-suscripcion="{{ e(json_encode($payloadEdicion)) }}">Editar</button>
                    <form method="post" class="d-inline" action="{{ route('probar_suscripcion_reporte_sueldos_definible', ['id' => $data->id, 'suscripcionId' => $s->id]) }}">
                        @csrf
                        <input type="hidden" name="dry_run" value="1">
                        <button type="submit" class="btn btn-sm btn-outline-secondary mb-1">Dry-run</button>
                    </form>
                    <form method="post" class="d-inline" action="{{ route('probar_suscripcion_reporte_sueldos_definible', ['id' => $data->id, 'suscripcionId' => $s->id]) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary mb-1">Reintentar</button>
                    </form>
                    <form method="post" class="d-inline" action="{{ route('eliminar_suscripcion_reporte_sueldos_definible', ['id' => $data->id, 'suscripcionId' => $s->id]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger mb-1">Quitar</button>
                    </form>
                </div>
            </div>
            @if($envios->isNotEmpty())
                <div class="table-responsive mt-2">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr><th>Destinatario</th><th>Segmento</th><th>Estado</th><th>Mensaje</th><th>Fecha</th></tr>
                        </thead>
                        <tbody>
                        @foreach($envios as $envio)
                            <tr>
                                <td>{{ $envio->destinatario }}</td>
                                <td>{{ $envio->burst_etiqueta ?: ($envio->burst_clave ?: '—') }}</td>
                                <td>{{ $envio->estado }}</td>
                                <td class="small">{{ $envio->mensaje }}</td>
                                <td>{{ $envio->created_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            @if($s->burst_dimension !== 'ninguna')
                <div class="mt-2 p-2 bg-light border">
                    <form method="post" class="form-inline" action="{{ route('guardar_destinatario_suscripcion_reporte_sueldos_definible', ['id' => $data->id, 'suscripcionId' => $s->id]) }}">
                        @csrf
                        <input name="dimension_clave" class="form-control form-control-sm mr-1" placeholder="Clave, ej. cc:12" required>
                        <input name="dimension_etiqueta" class="form-control form-control-sm mr-1" placeholder="Etiqueta">
                        <input type="email" name="email" class="form-control form-control-sm mr-1" placeholder="destino@" required>
                        <button class="btn btn-outline-info btn-sm">Agregar destino segmentado</button>
                    </form>
                    @foreach($s->destinatariosBurst as $destino)
                        <span class="badge badge-secondary mt-2 mr-1">
                            {{ $destino->dimension_clave }} → {{ $destino->email }}
                            <form method="post" class="d-inline" action="{{ route('eliminar_destinatario_suscripcion_reporte_sueldos_definible', ['id' => $data->id, 'suscripcionId' => $s->id, 'destinatarioId' => $destino->id]) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-link btn-sm text-white p-0 ml-1" title="Quitar">&times;</button>
                            </form>
                        </span>
                    @endforeach
                </div>
            @endif
        </li>
    @empty
        <li class="list-group-item text-muted">Sin suscripciones</li>
    @endforelse
</ul>
