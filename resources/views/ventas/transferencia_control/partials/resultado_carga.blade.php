@php
    $resumen = $resumenCarga ?? null;
@endphp
@if (is_array($resumen))
    @php
        $c = $resumen['contadores'] ?? [];
        $modoConsulta = ($resumen['modo'] ?? '') === 'consulta';
        $puedeMayor = ! empty($resumen['puede_mayor']) && can('listar-mayor-plano-cuenta', false);
    @endphp
    <div class="card card-outline card-success mb-3" id="resumen-post-import">
        <div class="card-header d-flex align-items-center flex-wrap" style="gap:.5rem;">
            <h3 class="card-title mb-0">
                <i class="fa fa-check-circle"></i> {{ $resumen['titulo'] ?? 'Resultado de la importación' }}
            </h3>
            <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:.35rem;">
                @if ($puedeMayor && ! empty($resumen['url_mayor_cuentas']))
                    <a href="{{ $resumen['url_mayor_cuentas'] }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                        <i class="fa fa-book"></i> Mayor de cuentas usadas
                    </a>
                @endif
                @if ($puedeMayor && ! empty($resumen['url_mayor_centros']) && ($resumen['centros_costo'] ?? []) !== [])
                    <a href="{{ $resumen['url_mayor_centros'] }}" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener">
                        <i class="fa fa-sitemap"></i> Mayor por centros
                    </a>
                @endif
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                @if (! empty($resumen['sala']))
                    Sala <strong>{{ $resumen['sala'] }}</strong> ·
                @endif
                Desde {{ \Carbon\Carbon::parse($resumen['desde'])->format('d/m/Y') }}
                hasta {{ \Carbon\Carbon::parse($resumen['hasta'])->format('d/m/Y') }}.
                @if ($modoConsulta)
                    Consulta de lo ya transferido en el ERP.
                @else
                    Solo ERP (no escribe Anita).
                @endif
            </p>

            <div class="row mb-2">
                <div class="col-md-2 col-6">
                    <div class="small-box bg-info mb-2">
                        <div class="inner">
                            <h3>{{ (int) ($c['leidos'] ?? 0) }}</h3>
                            <p>{{ $modoConsulta ? 'Comprobantes' : 'Leídos' }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <div class="small-box bg-success mb-2">
                        <div class="inner">
                            <h3>{{ (int) ($c['grabados'] ?? 0) }}</h3>
                            <p>{{ $modoConsulta ? 'Con asiento' : 'Facturas nuevas' }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <div class="small-box bg-primary mb-2">
                        <div class="inner">
                            <h3>{{ (int) ($modoConsulta ? ($c['asientos'] ?? 0) : ($c['enganchados'] ?? 0)) }}</h3>
                            <p>{{ $modoConsulta ? 'Asientos' : 'Asientos enganchados' }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <div class="small-box bg-secondary mb-2">
                        <div class="inner">
                            <h3>{{ (int) ($modoConsulta ? ($c['sin_asiento'] ?? 0) : ($c['asientos'] ?? 0)) }}</h3>
                            <p>{{ $modoConsulta ? 'Sin asiento' : 'Asientos totales' }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <div class="small-box bg-dark mb-2">
                        <div class="inner">
                            <h3>{{ (int) ($c['existen'] ?? 0) }}</h3>
                            <p>Ya estaban OK</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <div class="small-box {{ (int) ($c['errores_confirm'] ?? 0) > 0 ? 'bg-danger' : 'bg-warning' }} mb-2">
                        <div class="inner">
                            <h3>{{ (int) ($c['errores_confirm'] ?? 0) }}</h3>
                            <p>Errores al grabar</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4 col-6">
                    <div class="border rounded p-2 h-100 bg-light">
                        <div class="small text-muted">{{ $modoConsulta ? 'Total con asiento' : 'Total facturas nuevas' }}</div>
                        <div class="h4 mb-0">{{ number_format((float) ($c['total_grabado'] ?? 0), 2, ',', '.') }}</div>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="border rounded p-2 h-100 bg-light">
                        <div class="small text-muted">{{ $modoConsulta ? 'Total sin asiento' : 'Total enganchados' }}</div>
                        <div class="h4 mb-0">{{ number_format((float) ($modoConsulta ? ($c['total_sin_asiento'] ?? 0) : ($c['total_enganchado'] ?? 0)), 2, ',', '.') }}</div>
                    </div>
                </div>
                <div class="col-md-4 col-12">
                    <div class="border rounded p-2 h-100" style="background:#e8f8f0;">
                        <div class="small text-muted">{{ $modoConsulta ? 'Total transferido' : 'Total cargado / enganchado' }}</div>
                        <div class="h4 mb-0 text-success">{{ number_format((float) ($c['total_ok'] ?? 0), 2, ',', '.') }}</div>
                    </div>
                </div>
            </div>

            @if (($resumen['errores'] ?? []) !== [])
                <div class="alert alert-danger small">
                    <strong>Errores al grabar (muestra):</strong>
                    <ul class="mb-0 pl-3">
                        @foreach ($resumen['errores'] as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row">
                <div class="col-lg-4 mb-3">
                    <h5 class="mb-2" style="font-size:1rem;">Puntos de venta</h5>
                    <div class="table-responsive" style="max-height:16rem;">
                        <table class="table table-sm table-bordered mb-0" style="font-size:.82rem;">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>PV</th>
                                    <th class="text-right">Comp.</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($resumen['puntos_venta'] ?? []) as $pv)
                                    <tr>
                                        <td>
                                            <strong>{{ $pv['codigo'] }}</strong>
                                            <div class="text-muted text-truncate" style="max-width:10rem;" title="{{ $pv['nombre'] }}">{{ $pv['nombre'] }}</div>
                                        </td>
                                        <td class="text-right">{{ (int) $pv['comprobantes'] }}</td>
                                        <td class="text-right">{{ number_format((float) $pv['total'], 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted">Sin PV en lo cargado.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-lg-4 mb-3">
                    <h5 class="mb-2" style="font-size:1rem;">Centros de costo</h5>
                    <div class="table-responsive" style="max-height:16rem;">
                        <table class="table table-sm table-bordered mb-0" style="font-size:.82rem;">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Centro</th>
                                    <th class="text-right">Comp.</th>
                                    <th class="text-right">Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($resumen['centros_costo'] ?? []) as $cc)
                                    <tr>
                                        <td>
                                            <strong>{{ $cc['codigo'] }}</strong>
                                            <div class="text-muted text-truncate" style="max-width:9rem;" title="{{ $cc['nombre'] }}">{{ $cc['nombre'] }}</div>
                                        </td>
                                        <td class="text-right">{{ (int) $cc['comprobantes'] }}</td>
                                        <td class="text-right">{{ number_format((float) $cc['total'], 2, ',', '.') }}</td>
                                        <td class="text-center">
                                            @if ($puedeMayor && ! empty($cc['url_mayor']))
                                                <a class="btn btn-outline-primary btn-xs px-1 py-0" href="{{ $cc['url_mayor'] }}" target="_blank" rel="noopener" title="Mayor">
                                                    <i class="fa fa-book"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">Sin centros en lo cargado.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-lg-4 mb-3">
                    <h5 class="mb-2" style="font-size:1rem;">Cuentas contables</h5>
                    <div class="table-responsive" style="max-height:16rem;">
                        <table class="table table-sm table-bordered mb-0" style="font-size:.82rem;">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Cuenta</th>
                                    <th class="text-right">Debe</th>
                                    <th class="text-right">Haber</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($resumen['cuentas'] ?? []) as $cta)
                                    <tr>
                                        <td>
                                            <strong>{{ $cta['codigo'] }}</strong>
                                            <div class="text-muted text-truncate" style="max-width:9rem;" title="{{ $cta['nombre'] }}">{{ $cta['nombre'] }}</div>
                                        </td>
                                        <td class="text-right">{{ number_format((float) $cta['debe'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $cta['haber'], 2, ',', '.') }}</td>
                                        <td class="text-center">
                                            @if ($puedeMayor && ! empty($cta['url_mayor']))
                                                <a class="btn btn-outline-primary btn-xs px-1 py-0" href="{{ $cta['url_mayor'] }}" target="_blank" rel="noopener" title="Mayor">
                                                    <i class="fa fa-book"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">Sin cuentas en lo cargado.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
