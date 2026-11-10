@if (!empty($bloqueosCierre) && is_array($bloqueosCierre))
    @php
        $auditoriasPendientes = collect($bloqueosCierre)->filter(fn ($b) => !empty($b['sanear_auditoria']))->values();
        $otrosBloqueos = collect($bloqueosCierre)->reject(fn ($b) => !empty($b['sanear_auditoria']))->values();
        $puedeAuditarMov = can('editar-tesoreria-crown-movimiento', false);
        $abrirBloqueos = session()->has('bloqueos_cierre_jornada')
            || old('firma_usuario') !== null
            || old('firma_password') !== null
            || old('observacion') !== null;
    @endphp
    <div id="resolver-cierre-jornada" class="card card-outline card-warning mb-3"
         data-tc-bloqueos-cierre="1"
         data-tc-bloqueos-count="{{ count($bloqueosCierre) }}">
        <div class="card-header py-2 d-flex align-items-center tc-bloque-toggle{{ $abrirBloqueos ? '' : ' collapsed' }}"
             data-toggle="collapse"
             data-target="#resolver-cierre-jornada-body"
             role="button"
             aria-expanded="{{ $abrirBloqueos ? 'true' : 'false' }}"
             aria-controls="resolver-cierre-jornada-body"
             title="{{ $abrirBloqueos ? 'Ocultar' : 'Mostrar' }}">
            <i class="fa fa-chevron-down mr-2 tc-bloque-chevron"></i>
            <h3 class="card-title mb-0">
                <i class="fa fa-wrench"></i>
                Resolver para cerrar la jornada
                <span class="badge badge-warning ml-1">{{ count($bloqueosCierre) }}</span>
            </h3>
        </div>
        <div id="resolver-cierre-jornada-body" class="collapse{{ $abrirBloqueos ? ' show' : '' }}">
            <div class="card-body pb-2">
                <p class="small text-muted mb-2">
                    Antes de <strong>Cerrar jornada</strong>, cada caja con movimientos tiene que quedar
                    en conforme, revisada o cierre de auditoría. Resolvé acá lo pendiente.
                </p>

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Caja</th>
                                <th>Cajero</th>
                                <th>Estado</th>
                                <th class="text-right">Diferencia</th>
                                <th>Qué hacer</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bloqueosCierre as $b)
                                <tr>
                                    <td>{{ $b['caja'] }}</td>
                                    <td>{{ $b['cajero'] }}</td>
                                    <td>{{ $b['estado_txt'] }}</td>
                                    <td class="text-right">
                                        @if ($b['diferencia'] === null)
                                            —
                                        @else
                                            {{ number_format((float) $b['diferencia'], 2, ',', '.') }}
                                        @endif
                                    </td>
                                    <td class="small">{{ $b['accion'] }}</td>
                                    <td class="text-nowrap">
                                        @if (!empty($b['url_cierre']))
                                            <a class="btn btn-sm btn-outline-primary"
                                               href="{{ $b['url_cierre'] }}">
                                                @if (empty($b['estado']))
                                                    Cerrar caja
                                                @elseif (!empty($b['sanear_auditoria']))
                                                    Ver cierre
                                                @else
                                                    Resolver en cierre
                                                @endif
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($auditoriasPendientes->isNotEmpty() && $puedeAuditarMov)
                    <div class="border rounded p-3 mb-2" style="max-width:40rem;background:#fff;">
                        <h4 class="h6 mb-1">
                            Cerrar auditorías pendientes
                            <span class="badge badge-secondary">{{ $auditoriasPendientes->count() }}</span>
                        </h4>
                        <p class="small text-muted mb-2">
                            Firma de <strong>otra persona</strong> (con permiso de auditar caja), distinta
                            de quien cerró cada caja o la pasó a auditoría. Con una sola firma se cierran
                            todas las que pueda.
                        </p>
                        <ul class="small mb-2 pl-3">
                            @foreach ($auditoriasPendientes as $a)
                                <li>
                                    Caja {{ $a['caja'] }}
                                    @if ($a['diferencia'] !== null)
                                        — dif. {{ number_format((float) $a['diferencia'], 2, ',', '.') }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <form method="POST"
                              action="{{ route('sanear_auditorias_tesoreria_crown_jornada', $jornada->id) }}"
                              class="mb-0"
                              data-enter-campos="1"
                              onsubmit="return confirm('¿Cerrar las auditorías listadas con esta firma?');">
                            @csrf
                            <div class="form-row">
                                <div class="col-md-12 mb-2">
                                    <input type="text" name="observacion" class="form-control form-control-sm"
                                           maxlength="500" placeholder="Observación (opcional)"
                                           value="{{ old('observacion') }}">
                                </div>
                                <div class="col-md-5 mb-2">
                                    <input type="text" name="firma_usuario" class="form-control form-control-sm"
                                           placeholder="Usuario auditor" autocomplete="username" required
                                           value="{{ old('firma_usuario') }}">
                                </div>
                                <div class="col-md-5 mb-2">
                                    <input type="password" name="firma_password" class="form-control form-control-sm"
                                           placeholder="Clave" autocomplete="current-password" required>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <button type="submit" class="btn btn-warning btn-sm btn-block">
                                        Cerrar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                @elseif ($auditoriasPendientes->isNotEmpty())
                    <p class="small text-muted mb-2">
                        Hay auditorías pendientes: pedile a quien tenga permiso de auditar caja que firme
                        el cierre desde este panel o desde cada pantalla de cierre.
                    </p>
                @endif

                @if ($otrosBloqueos->isNotEmpty())
                    <p class="small mb-0">
                        Las cajas que no están en auditoría se resuelven en su pantalla de cierre
                        (cerrar caja, marcar revisada, etc.).
                    </p>
                @endif
            </div>
        </div>
    </div>
@endif
