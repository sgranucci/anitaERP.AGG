@php
    $puedeContabilizar = can(\App\Support\Sueldos\SueldosAsientoSupport::PERMISO_CONTABILIZAR, false);
    $puedeCerrar = can('cerrar-liquidacion-sueldos', false);
    $puedeSp = can(\App\Support\Sueldos\SueldosAsientoSupport::PERMISO_GENERAR_SP, false);
    $preview = $previewAsiento ?? null;
    $errorPrev = $errorAsiento ?? null;
    $estadosPreview = ['calculada', 'revisada', 'cerrada', 'contabilizada', 'pagada'];
    $muestraAsiento = in_array((string) $liq->estado, $estadosPreview, true) && empty($liq->simulacion);
    $sp = $liq->solicitudpago ?? null;
    $modoPreview = is_array($preview)
        ? \App\Support\Sueldos\SueldosAsientoModoSupport::normalizar((string) ($preview['modo'] ?? ''))
        : \App\Support\Sueldos\SueldosAsientoModoSupport::ERP;
    $esModoAnita = $modoPreview === \App\Support\Sueldos\SueldosAsientoModoSupport::ANITA;
    $asientosHijos = $liq->asientosSueldos ?? collect();
    $confirmContabilizar = $esModoAnita
        ? '¿Contabilizar esta corrida? Se genera un asiento PER por centro de costo (pasivos en CC 0) y se escribe ctamov en Anita.'
        : '¿Contabilizar esta corrida? Se genera el asiento SUEL en el ERP y se escribe ctamov en Anita.';
@endphp
@if ($muestraAsiento)
<div class="card card-outline card-info mt-3">
    <div class="card-header">
        <h3 class="card-title">
            Asiento de devengamiento
            @if ($esModoAnita)
                <span class="badge badge-secondary ml-1">Modo Anita</span>
            @else
                <span class="badge badge-info ml-1">Modo ERP</span>
            @endif
        </h3>
        <div class="card-tools">
            @if ($puedeCerrar && in_array((string) $liq->estado, ['calculada', 'revisada'], true))
                <form action="{{ route('estado_liquidacion_sueldos', ['id' => $liq->id]) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('¿Cerrar esta corrida? El asiento debe cuadrar contra el neto.');">
                    @csrf
                    <input type="hidden" name="estado" value="cerrada">
                    <button type="submit" class="btn btn-sm btn-outline-success" @if (! empty($preview) && empty($preview['ok'])) disabled @endif>
                        <i class="fa fa-lock"></i> Cerrar corrida
                    </button>
                </form>
            @endif
            @if ($asientosHijos->count() > 1 && can('editar-asiento', false))
                @foreach ($asientosHijos as $hijo)
                    @if ($hijo->asiento)
                        <a href="{{ route('editar_asiento', ['id' => $hijo->asiento_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
                            <i class="fa fa-external-link-alt"></i>
                            Asiento {{ $hijo->asiento->numeroasiento }}
                        </a>
                    @endif
                @endforeach
            @elseif ((int) ($liq->asiento_id ?? 0) > 0 && can('editar-asiento', false))
                <a href="{{ route('editar_asiento', ['id' => $liq->asiento_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                   class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
                    <i class="fa fa-external-link-alt"></i>
                    Asiento {{ optional($liq->asiento)->numeroasiento ?? $liq->asiento_id }}
                </a>
            @endif
            @if ($puedeContabilizar && $liq->estado === 'cerrada' && ! $liq->contabilizado)
                <form action="{{ route('contabilizar_liquidacion_sueldos', ['id' => $liq->id]) }}" method="POST" class="d-inline"
                      onsubmit="return confirm(@json($confirmContabilizar));">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary" @if (! empty($preview) && empty($preview['ok'])) disabled @endif>
                        <i class="fa fa-balance-scale"></i> Contabilizar
                    </button>
                </form>
            @endif
            @if ($puedeSp && $liq->estado === 'contabilizada' && empty($liq->solicitudpago_id))
                <form action="{{ route('solicitudpago_liquidacion_sueldos', ['id' => $liq->id]) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('¿Generar la solicitud de pago del neto? Queda autorizada para pagar desde Caja.');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="fa fa-file-invoice-dollar"></i> Solicitud de pago
                    </button>
                </form>
            @endif
            @if ($sp)
                <a href="{{ route('editar_solicitudpago', ['id' => $sp->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                   class="btn btn-sm btn-outline-info" target="_blank" rel="noopener">
                    SP {{ $sp->codigo }} {{ $sp->estado }}
                </a>
                @if ($sp->estado === \App\Support\Solicitudpago\SolicitudpagoEstados::AUTORIZADA && can('actualizar-solicitud-pago', false) && can('crear-ingresos-egresos-caja', false))
                    <a href="{{ route('ir_a_pago_solicitudpago', ['id' => $sp->id]) }}" class="btn btn-sm btn-primary">
                        <i class="fa fa-money-bill-wave"></i> Ir a pago
                    </a>
                @endif
            @endif
            @if ($puedeContabilizar && ($liq->estado === 'contabilizada' || $liq->contabilizado) && $liq->estado !== 'pagada')
                <form action="{{ route('descontabilizar_liquidacion_sueldos', ['id' => $liq->id]) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('¿Descontabilizar? Se genera un contra-asiento en ERP y ctamov. La corrida vuelve a cerrada.');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="fa fa-undo"></i> Descontabilizar
                    </button>
                </form>
            @endif
        </div>
    </div>
    <div class="card-body p-2">
        @if ($errorPrev)
            <div class="alert alert-danger mb-2">{{ $errorPrev }}</div>
        @endif
        @if (is_array($preview))
            @foreach ($preview['errores'] ?? [] as $err)
                <div class="alert alert-danger mb-1 py-1">{{ $err }}</div>
            @endforeach
            @foreach ($preview['advertencias'] ?? [] as $adv)
                <div class="alert alert-warning mb-1 py-1">{{ $adv }}</div>
            @endforeach
            <p class="small text-muted mb-2">
                Conceptos AS usados: {{ $preview['conceptos_usados'] ?? 0 }}
                · renglones no imputados: {{ $preview['renglones_omitidos'] ?? 0 }}
                · Neto corrida $ {{ number_format((float) ($preview['total_neto_cabecera'] ?? 0), 2, ',', '.') }}
                · Haber a pagar $ {{ number_format((float) ($preview['haber_a_pagar'] ?? 0), 2, ',', '.') }}
            </p>
            @include('sueldos.liquidacion.partials.asiento_calidad', ['preview' => $preview])
            @php
                $tablasAsiento = ($esModoAnita && count($preview['grupos'] ?? []) > 0)
                    ? $preview['grupos']
                    : [[
                        'etiqueta' => null,
                        'lineas' => $preview['lineas'] ?? [],
                        'total_debe' => $preview['total_debe'] ?? 0,
                        'total_haber' => $preview['total_haber'] ?? 0,
                    ]];
            @endphp
            @foreach ($tablasAsiento as $grupoVista)
                @if (! empty($grupoVista['etiqueta']) && $esModoAnita)
                    <p class="small font-weight-bold mb-1 mt-2">{{ $grupoVista['etiqueta'] }}</p>
                @endif
                <div class="table-responsive {{ ! $loop->last ? 'mb-2' : '' }}">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Cuenta</th>
                                <th>Nombre</th>
                                <th>Centro de costo</th>
                                <th class="text-right">Debe</th>
                                <th class="text-right">Haber</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($grupoVista['lineas'] ?? [] as $linea)
                                <tr>
                                    <td>{{ $linea['cuenta_codigo'] }}</td>
                                    <td>{{ $linea['cuenta_nombre'] }}</td>
                                    <td>
                                        @if (($linea['centrocosto_codigo'] ?? '') !== '')
                                            {{ $linea['centrocosto_codigo'] }} {{ $linea['centrocosto_nombre'] }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ $linea['debe'] > 0 ? number_format($linea['debe'], 2, ',', '.') : '' }}</td>
                                    <td class="text-right">{{ $linea['haber'] > 0 ? number_format($linea['haber'], 2, ',', '.') : '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">
                                        No hay conceptos AS con importe en esta corrida. Recalcule o verifique el mapeo.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="font-weight-bold">
                                <td colspan="3" class="text-right">Totales</td>
                                <td class="text-right">{{ number_format((float) ($grupoVista['total_debe'] ?? 0), 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) ($grupoVista['total_haber'] ?? 0), 2, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endif
