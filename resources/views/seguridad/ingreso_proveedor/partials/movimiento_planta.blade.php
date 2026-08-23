@php
    $ticket = $data ?? null;
    $personasMov = $ticket?->personas ?? collect();
    $hayIngreso = (bool) ($ticket?->fecha_ingreso)
        || $personasMov->contains(fn ($p) => $p->fecha_ingreso);
    $fmtHora = static function ($hora): string {
        $v = trim((string) $hora);

        return $v === '' ? '' : substr($v, 0, 5);
    };
    $fmtMinutos = static function ($minutos): string {
        if ($minutos === null || $minutos === '') {
            return '';
        }
        $m = (int) $minutos;
        $h = intdiv($m, 60);
        $r = $m % 60;

        return $h > 0 ? $h.' h '.$r.' min' : $r.' min';
    };
@endphp
@if ($ticket && $ticket->id)
    <div class="card card-outline card-info mb-3 ingreso-movimiento-planta">
        <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between">
            <h5 class="mb-0"><i class="fa fa-clock"></i> Movimiento en planta</h5>
            @php $cantArchivosTicket = $ticket->archivos?->count() ?? 0; @endphp
            @if ($cantArchivosTicket > 0)
                <button type="button" class="btn btn-outline-info btn-sm" data-toggle="collapse" data-target="#ingreso-ver-archivos" aria-expanded="false" aria-controls="ingreso-ver-archivos">
                    <i class="fa fa-paperclip"></i> Ver archivos ({{ $cantArchivosTicket }})
                </button>
            @endif
        </div>
        <div class="card-body py-3">
            @if (! $hayIngreso)
                <p class="text-muted mb-0">A&uacute;n no registr&oacute; ingreso en porter&iacute;a.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Persona</th>
                                <th>DNI</th>
                                <th>Situaci&oacute;n</th>
                                <th>Ingreso</th>
                                <th>Registr&oacute; ENTRO</th>
                                <th>Egreso</th>
                                <th>Registr&oacute; SALIO</th>
                                <th>Tiempo en planta</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($personasMov as $persona)
                                @php
                                    $entro = (bool) $persona->fecha_ingreso;
                                    $salio = (bool) $persona->fecha_egreso;
                                    if ($entro && ! $salio) {
                                        $situacion = 'En planta';
                                        $badge = 'info';
                                    } elseif ($entro && $salio) {
                                        $situacion = 'Ingresó y salió';
                                        $badge = 'secondary';
                                    } else {
                                        $situacion = 'Pendiente de ingreso';
                                        $badge = 'warning';
                                    }
                                    $ingresoTxt = $entro
                                        ? trim(optional($persona->fecha_ingreso)->format('d/m/Y').' '.$fmtHora($persona->hora_ingreso))
                                        : '';
                                    $egresoTxt = $salio
                                        ? trim(optional($persona->fecha_egreso)->format('d/m/Y').' '.$fmtHora($persona->hora_egreso))
                                        : '';
                                @endphp
                                <tr>
                                    <td>{{ $persona->nombre }}</td>
                                    <td>{{ $persona->documento }}</td>
                                    <td><span class="badge badge-{{ $badge }}">{{ $situacion }}</span></td>
                                    <td>{{ $ingresoTxt }}</td>
                                    <td>{{ $persona->usuarioIngreso->nombre ?? '' }}</td>
                                    <td>{{ $egresoTxt }}</td>
                                    <td>{{ $persona->usuarioEgreso->nombre ?? '' }}</td>
                                    <td>{{ $fmtMinutos($persona->minutos_en_planta) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        Ingreso: {{ optional($ticket->fecha_ingreso)->format('d/m/Y') }} {{ $fmtHora($ticket->hora_ingreso) }}
                                        @if ($ticket->fecha_egreso)
                                            · Egreso: {{ optional($ticket->fecha_egreso)->format('d/m/Y') }} {{ $fmtHora($ticket->hora_egreso) }}
                                            · Tiempo: {{ $fmtMinutos($ticket->minutos_en_planta) }}
                                        @else
                                            · En planta
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    @if (($ticket->archivos?->count() ?? 0) > 0)
        <div class="collapse mb-3" id="ingreso-ver-archivos">
            <div class="card card-outline card-secondary">
                <div class="card-header py-2">
                    <h5 class="mb-0"><i class="fa fa-paperclip"></i> Archivos asociados</h5>
                </div>
                <div class="card-body">
                    @include('seguridad.ingreso_proveedor.partials.archivos_adjuntos', [
                        'data' => $ticket,
                        'ocultarInputsConservar' => true,
                    ])
                </div>
            </div>
        </div>
    @endif
@endif
