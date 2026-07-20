@php
    use App\Models\Sueldos\Prenda_Agrupamiento_Sueldos;
@endphp
<div id="indumentaria-panel"
     data-empleado="{{ $empleado->id }}"
     data-url="{{ route('indumentaria_empleado_sueldos', ['empleado' => $empleado->id]) }}"
     data-url-entregar="{{ route('entregar_prenda_sueldos', ['empleado' => $empleado->id]) }}"
     data-url-talles="{{ route('talles_empleado_sueldos', ['empleado' => $empleado->id]) }}"
     data-url-solicitud="{{ route('crear_solicitud_prenda_sueldos', ['empleado' => $empleado->id]) }}"
     data-url-variantes="{{ url('sueldos/indumentaria/prenda') }}">

    @if (! $config->estaCompleta())
        <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i>
            Falta configurar el depósito de origen y el tipo de transacción de indumentaria.
            @can('editar-configuracion-indumentaria')
                <a href="{{ route('config_indumentaria') }}" target="_blank" rel="noopener">Configurar ahora</a>.
            @endcan
        </div>
    @endif

    {{-- plantilla compartida de prendas para los selects (entrega y solicitud) --}}
    <select id="tpl-prendas" class="d-none">
        <option value="">— Prenda —</option>
        @foreach ($prendas as $pr)
            <option value="{{ $pr->id }}">{{ $pr->codigo }} - {{ $pr->descripcion }}</option>
        @endforeach
    </select>

    <div class="row">
        {{-- Dotación / saldos --}}
        <div class="col-lg-5">
            <div class="card card-outline card-info">
                <div class="card-header py-2">
                    <h3 class="card-title"><i class="fa fa-list-check"></i> Dotación {{ $resumen['anio'] }} —
                        {{ Prenda_Agrupamiento_Sueldos::etiquetaSexo($resumen['sexo']) }}</h3>
                </div>
                <div class="card-body p-2">
                    @if ($resumen['sin_agrupamiento'])
                        <p class="text-muted mb-0">El empleado no tiene agrupamiento asignado.</p>
                    @elseif (count($resumen['prendas']) === 0)
                        <p class="text-muted mb-0">Sin dotación configurada para el agrupamiento/sexo.</p>
                    @else
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#85C1E9;color:#17202A">
                                <tr><th>Prenda</th><th class="text-right">Cupo</th><th class="text-right">Entreg.</th><th class="text-right">Saldo</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($resumen['prendas'] as $p)
                                    @php
                                        $venc = $p['proximo_vencimiento'] ?? null;
                                        $vencClase = 'text-muted';
                                        if ($venc) {
                                            $dias = \Carbon\Carbon::parse($venc)->diffInDays(\Carbon\Carbon::today(), false);
                                            $vencClase = $dias >= 0 ? 'text-danger' : (\Carbon\Carbon::parse($venc)->diffInDays(\Carbon\Carbon::today()) <= 30 ? 'text-warning' : 'text-muted');
                                        }
                                    @endphp
                                    <tr>
                                        <td>
                                            {{ $p['codigo'] }} - {{ $p['descripcion'] }}@if($p['color'])<small class="text-muted"> ({{ $p['color'] }})</small>@endif
                                            @if (! empty($p['es_seguridad']))<span class="badge badge-warning" title="{{ $p['norma'] ?? 'EPP' }}">EPP</span>@endif
                                            @if (($p['modo'] ?? 'anual') === 'vencimiento')
                                                <small class="d-block {{ $vencClase }}">
                                                    <i class="fa fa-clock"></i>
                                                    @if($venc) vence {{ \Carbon\Carbon::parse($venc)->format('d/m/Y') }} @else vida útil {{ $p['vida_util_meses'] }}m @endif
                                                </small>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ rtrim(rtrim(number_format($p['limite'],2,',','.'),'0'),',') }}</td>
                                        <td class="text-right">{{ rtrim(rtrim(number_format($p['entregado'],2,',','.'),'0'),',') }}</td>
                                        <td class="text-right">
                                            <span class="badge badge-{{ $p['saldo'] > 0 ? 'success' : 'secondary' }}">
                                                {{ rtrim(rtrim(number_format($p['saldo'],2,',','.'),'0'),',') }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            {{-- Perfil de talles --}}
            @if (! $resumen['sin_agrupamiento'] && count($resumen['prendas']) > 0)
                <div class="card card-outline card-secondary">
                    <div class="card-header py-2"><h3 class="card-title"><i class="fa fa-ruler"></i> Perfil de talles</h3></div>
                    <div class="card-body p-2">
                        <form id="form-talles-indumentaria">
                            <table class="table table-sm mb-2">
                                <tbody>
                                    @foreach ($resumen['prendas'] as $p)
                                        <tr>
                                            <td>{{ $p['descripcion'] }}</td>
                                            <td style="width:45%">
                                                <select name="talles[{{ $p['prenda_id'] }}]" class="form-control form-control-sm" {{ ($puedeTalles ?? $puedeEntregar) ? '' : 'disabled' }}>
                                                    <option value="">—</option>
                                                    @foreach ($talles as $t)
                                                        <option value="{{ $t->id }}" {{ (int) ($tallesEmpleado[$p['prenda_id']] ?? 0) === (int) $t->id ? 'selected' : '' }}>{{ $t->nombre }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if ($puedeTalles ?? $puedeEntregar)
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fa fa-save"></i> Guardar talles</button>
                            @endif
                        </form>
                    </div>
                </div>
            @endif
        </div>

        {{-- Nueva entrega --}}
        <div class="col-lg-7">
            @if ($puedeEntregar)
                <div class="card card-outline card-success">
                    <div class="card-header py-2"><h3 class="card-title"><i class="fa fa-truck"></i> Nueva entrega</h3></div>
                    <div class="card-body p-2">
                        <form id="form-entrega-indumentaria">
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label class="mb-1">Fecha</label>
                                    <input type="date" id="entrega_fecha" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                                </div>
                                <div class="form-group col-md-8">
                                    <label class="mb-1">Observación</label>
                                    <input type="text" id="entrega_obs" class="form-control form-control-sm" maxlength="255">
                                </div>
                            </div>
                            <table class="table table-sm table-bordered" id="tabla-entrega-lineas">
                                <thead style="background:#85C1E9;color:#17202A">
                                    <tr><th style="width:35%">Prenda</th><th style="width:40%">Color / Talle (SKU)</th><th style="width:12%">Cant.</th><th style="width:13%"></th></tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-agregar-linea-entrega"><i class="fa fa-plus"></i> Agregar prenda</button>
                                <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-check"></i> Registrar entrega</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Nueva solicitud (con árbol de aprobación opcional) --}}
            @if (! empty($puedeSolicitar))
                <div class="card card-outline card-warning">
                    <div class="card-header py-2">
                        <h3 class="card-title"><i class="fa fa-clipboard-list"></i> Nueva solicitud</h3>
                        <span class="float-right small">
                            @if (! empty($tieneAprobacion))
                                <span class="badge badge-info" title="La solicitud pasa por el árbol de aprobación configurado">Con aprobación</span>
                            @else
                                <span class="badge badge-secondary" title="Sin árbol: la solicitud queda aprobada y lista para entregar">Aprobación automática</span>
                            @endif
                        </span>
                    </div>
                    <div class="card-body p-2">
                        <form id="form-solicitud-indumentaria">
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label class="mb-1">Fecha</label>
                                    <input type="date" id="solicitud_fecha" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                                </div>
                                <div class="form-group col-md-8">
                                    <label class="mb-1">Observación</label>
                                    <input type="text" id="solicitud_obs" class="form-control form-control-sm" maxlength="255">
                                </div>
                            </div>
                            <table class="table table-sm table-bordered" id="tabla-solicitud-lineas">
                                <thead style="background:#85C1E9;color:#17202A">
                                    <tr><th style="width:35%">Prenda</th><th style="width:40%">Color / Talle (SKU)</th><th style="width:12%">Cant.</th><th style="width:13%"></th></tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-agregar-linea-solicitud"><i class="fa fa-plus"></i> Agregar prenda</button>
                                <button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-paper-plane"></i> Enviar solicitud</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Historial --}}
            <div class="card card-outline card-primary">
                <div class="card-header py-2"><h3 class="card-title"><i class="fa fa-history"></i> Historial de entregas</h3></div>
                <div class="card-body p-2">
                    @if ($entregas->isEmpty())
                        <p class="text-muted mb-0">Sin entregas registradas.</p>
                    @else
                        <table class="table table-sm table-hover mb-0">
                            <thead style="background:#85C1E9;color:#17202A">
                                <tr><th>#</th><th>Fecha</th><th>Prendas</th><th>Usuario</th><th>TuLegajo</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($entregas as $e)
                                    <tr>
                                        <td>{{ $e->id }}</td>
                                        <td>{{ optional($e->fecha)->format('d/m/Y') }}</td>
                                        <td>
                                            @foreach ($e->articulos as $a)
                                                <div>{{ optional($a->prenda)->descripcion }}
                                                    <small class="text-muted">{{ optional($a->color)->nombre }} {{ optional($a->talle)->nombre }}</small>
                                                    × {{ rtrim(rtrim(number_format($a->cantidad,2,',','.'),'0'),',') }}
                                                </div>
                                            @endforeach
                                            @if ($e->observacion)<small class="text-muted d-block">{{ $e->observacion }}</small>@endif
                                        </td>
                                        <td><small>{{ optional($e->usuario)->nombre }}</small></td>
                                        <td>
                                            @if ($e->tulegajo_estado === 'ENVIADO')
                                                <span class="badge badge-success" title="{{ $e->tulegajo_mensaje }}">Enviado</span>
                                                <small class="text-muted d-block">{{ optional($e->tulegajo_enviado_at)->format('d/m/Y H:i') }}</small>
                                            @elseif ($e->tulegajo_estado === 'ERROR')
                                                <span class="badge badge-danger" title="{{ $e->tulegajo_mensaje }}">Error</span>
                                            @else
                                                <span class="badge badge-secondary">Pendiente</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            <a href="{{ route('comprobante_entrega_prenda', ['entrega' => $e->id]) }}" target="_blank" rel="noopener" class="btn btn-xs btn-info" title="Comprobante"><i class="fa fa-file-pdf"></i></a>
                                            @if (!empty($puedeTulegajo) && !empty($tulegajoHabilitado) && $e->tulegajo_estado !== 'ENVIADO')
                                                <button type="button" class="btn btn-xs btn-primary btn-tulegajo-entrega" data-url="{{ route('tulegajo_entrega_prenda_sueldos', ['entrega' => $e->id]) }}" title="Enviar a TuLegajo"><i class="fa fa-cloud-upload-alt"></i></button>
                                            @endif
                                            @if ($puedeAnular)
                                                <button type="button" class="btn btn-xs btn-danger btn-anular-entrega" data-url="{{ route('anular_entrega_prenda_sueldos', ['entrega' => $e->id]) }}" title="Anular"><i class="fa fa-times"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            {{-- Solicitudes --}}
            <div class="card card-outline card-warning">
                <div class="card-header py-2"><h3 class="card-title"><i class="fa fa-clipboard-check"></i> Solicitudes</h3></div>
                <div class="card-body p-2">
                    @if ($solicitudes->isEmpty())
                        <p class="text-muted mb-0">Sin solicitudes registradas.</p>
                    @else
                        <table class="table table-sm table-hover mb-0">
                            <thead style="background:#85C1E9;color:#17202A">
                                <tr><th>#</th><th>Fecha</th><th>Prendas</th><th>Estado</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($solicitudes as $s)
                                    <tr>
                                        <td>{{ $s->id }}</td>
                                        <td>{{ optional($s->fecha)->format('d/m/Y') }}</td>
                                        <td>
                                            @foreach ($s->articulos as $a)
                                                <div>{{ optional($a->prenda)->descripcion }}
                                                    <small class="text-muted">{{ optional($a->color)->nombre }} {{ optional($a->talle)->nombre }}</small>
                                                    × {{ rtrim(rtrim(number_format($a->cantidad,2,',','.'),'0'),',') }}
                                                </div>
                                            @endforeach
                                            @if ($s->observacion)<small class="text-muted d-block">{{ $s->observacion }}</small>@endif
                                            @if ($s->estado === \App\Models\Sueldos\Solicitud_Prenda_Sueldos::PENDIENTE)
                                                <small class="text-info d-block"><i class="fa fa-hourglass-half"></i> Nivel {{ $s->nivel_actual }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $badge = [
                                                    \App\Models\Sueldos\Solicitud_Prenda_Sueldos::BORRADOR => 'secondary',
                                                    \App\Models\Sueldos\Solicitud_Prenda_Sueldos::PENDIENTE => 'info',
                                                    \App\Models\Sueldos\Solicitud_Prenda_Sueldos::APROBADA => 'primary',
                                                    \App\Models\Sueldos\Solicitud_Prenda_Sueldos::RECHAZADA => 'danger',
                                                    \App\Models\Sueldos\Solicitud_Prenda_Sueldos::ENTREGADA => 'success',
                                                    \App\Models\Sueldos\Solicitud_Prenda_Sueldos::ANULADA => 'dark',
                                                ][$s->estado] ?? 'secondary';
                                            @endphp
                                            <span class="badge badge-{{ $badge }}">{{ \App\Models\Sueldos\Solicitud_Prenda_Sueldos::ESTADOS[$s->estado] ?? $s->estado }}</span>
                                        </td>
                                        <td class="text-nowrap">
                                            @if (! empty($puedeAprobarMap[$s->id]))
                                                <button type="button" class="btn btn-xs btn-success btn-aprobar-solicitud" data-url="{{ route('aprobar_solicitud_prenda_sueldos', ['solicitud' => $s->id]) }}" title="Aprobar"><i class="fa fa-check"></i></button>
                                                <button type="button" class="btn btn-xs btn-danger btn-rechazar-solicitud" data-url="{{ route('rechazar_solicitud_prenda_sueldos', ['solicitud' => $s->id]) }}" title="Rechazar"><i class="fa fa-ban"></i></button>
                                            @endif
                                            @if ($s->estado === \App\Models\Sueldos\Solicitud_Prenda_Sueldos::APROBADA && $puedeEntregar)
                                                <button type="button" class="btn btn-xs btn-primary btn-entregar-solicitud" data-url="{{ route('entregar_solicitud_prenda_sueldos', ['solicitud' => $s->id]) }}" title="Entregar"><i class="fa fa-truck"></i></button>
                                            @endif
                                            @if ($puedeSolicitar && in_array($s->estado, [\App\Models\Sueldos\Solicitud_Prenda_Sueldos::BORRADOR, \App\Models\Sueldos\Solicitud_Prenda_Sueldos::PENDIENTE, \App\Models\Sueldos\Solicitud_Prenda_Sueldos::APROBADA, \App\Models\Sueldos\Solicitud_Prenda_Sueldos::RECHAZADA], true))
                                                <button type="button" class="btn btn-xs btn-outline-dark btn-anular-solicitud" data-url="{{ route('anular_solicitud_prenda_sueldos', ['solicitud' => $s->id]) }}" title="Anular"><i class="fa fa-times"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
