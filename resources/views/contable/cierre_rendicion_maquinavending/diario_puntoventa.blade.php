@extends("theme.$theme.layout")
@section('titulo')
    Diario vending por PV (Contable)
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Diario por punto de venta y medios de pago (vending)</h3>
                <div class="card-tools ml-auto">
                    <a href="{{ route('cierre_rendicion_maquinavending_contable', $retornoListadoQuery ?? []) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info py-2 small mb-3">
                    <strong>C&oacute;mo leer el reporte</strong>
                    <ul class="mb-0 pl-3">
                        <li>Una secci&oacute;n por <strong>jornada</strong>; dentro, una fila por <strong>punto de venta</strong> (m&aacute;quina vending).</li>
                        <li><strong>Venta total</strong> = ventas del punto de venta (vending no maneja notas de cr&eacute;dito).</li>
                        <li>Expand&iacute; cada PV para ver <strong>medios de pago</strong> desde los movimientos de caja de la rendici&oacute;n.</li>
                        <li>Fuente: rendiciones vending presentadas en caja (ERP).</li>
                    </ul>
                </div>

                <form method="get" action="{{ route('cierre_rendicion_maquinavending_diario_puntoventa') }}" class="mb-4">
                    @foreach ($retornoListadoQuery ?? [] as $retornoKey => $retornoVal)
                        <input type="hidden" name="retorno[{{ $retornoKey }}]" value="{{ $retornoVal }}">
                    @endforeach
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4">
                            <label for="empresa_id">Empresa</label>
                            <select name="empresa_id" id="empresa_id" class="form-control" required>
                                <option value="">— Seleccione —</option>
                                @foreach ($empresa_query as $emp)
                                    <option value="{{ $emp->id }}" @selected((int) ($empresa_id ?? 0) === (int) $emp->id)>
                                        {{ $emp->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="fecha_desde">Jornada desde</label>
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                   value="{{ $fecha_desde ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="fecha_hasta">Jornada hasta</label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                   value="{{ $fecha_hasta ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-2">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </form>

                @if (! empty($error_reporte))
                    <div class="alert alert-danger">{{ $error_reporte }}</div>
                @endif

                @if ($consultar && empty($error_reporte) && $resultado !== null)
                    @php
                        $resumen = $resultado['resumen'] ?? [];
                        $dias = $resultado['dias'] ?? [];
                        $mediosGlobal = $resultado['medios_global'] ?? [];
                    @endphp

                    <div class="d-flex flex-wrap align-items-start justify-content-between mb-3">
                        <div>
                            <strong>{{ $resultado['empresa_nombre'] ?? '' }}</strong>
                            — {{ \Carbon\Carbon::parse($resultado['fecha_desde'])->format('d/m/Y') }}
                            al {{ \Carbon\Carbon::parse($resultado['fecha_hasta'])->format('d/m/Y') }}
                            <br>
                            <span class="text-muted">
                                {{ (int) ($resumen['cantidad_dias'] ?? 0) }} jornada(s) —
                                {{ (int) ($resumen['cantidad_filas_pv'] ?? 0) }} punto(s) de venta —
                                venta total {{ number_format((float) ($resumen['venta_bruta'] ?? 0), 2, ',', '.') }}
                            </span>
                        </div>
                        @if (can('exportar-cierre-rendicion-maquinavending-contable', false))
                            <div class="mr-2 mb-1">
                                @include('includes.exportar-tabla-queryparams', [
                                    'ruta' => 'listar_cierre_rendicion_maquinavending_diario_puntoventa',
                                    'queryparams' => $filtrosQuery ?? [],
                                ])
                            </div>
                        @endif
                    </div>

                    @if ($dias === [])
                        <p class="text-muted text-center py-4">Sin rendiciones vending en el rango indicado.</p>
                    @else
                        @foreach ($dias as $idxDia => $dia)
                            @php
                                $totDia = $dia['totales'] ?? [];
                            @endphp
                            <div class="card mb-3 border">
                                <div class="card-header py-2" style="background:#d6eaf8;color:#17202A;">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center">
                                        <strong>Jornada {{ $dia['fecha_jornada_fmt'] ?? '' }}</strong>
                                        <span class="small">
                                            {{ (int) ($dia['cantidad_pv'] ?? 0) }} PV —
                                            Venta total <strong>{{ number_format((float) ($totDia['venta_bruta'] ?? 0), 2, ',', '.') }}</strong>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover mb-0" style="font-size: 0.95rem;">
                                            <thead style="background:#85C1E9;color:#17202A;">
                                                <tr>
                                                    <th style="width:2.5rem;"></th>
                                                    <th>Punto de venta</th>
                                                    <th class="text-center">Rend.</th>
                                                    <th class="text-right">Venta total</th>
                                                    <th class="text-right">Cobrado</th>
                                                    <th class="text-right">Invit.</th>
                                                    <th class="text-right">Dif. cobro</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($dia['puntoventas'] ?? [] as $idxPv => $pv)
                                                    @php
                                                        $collapseId = 'pv-'.$idxDia.'-'.$idxPv;
                                                        $dif = (float) ($pv['diferencia_cobranza'] ?? 0);
                                                    @endphp
                                                    <tr class="{{ empty($pv['cuadre_ok']) ? 'table-warning' : '' }}">
                                                        <td class="text-center align-middle p-1">
                                                            <button class="btn btn-sm btn-outline-secondary collapsed"
                                                                    type="button"
                                                                    data-toggle="collapse"
                                                                    data-target="#{{ $collapseId }}"
                                                                    title="Medios de pago">
                                                                <i class="fa fa-chevron-down"></i>
                                                            </button>
                                                        </td>
                                                        <td class="align-middle">
                                                            <strong>{{ $pv['pv_codigo'] ?? '' }}</strong>
                                                            @if (! empty($pv['pv_nombre']) && ($pv['pv_nombre'] ?? '') !== ($pv['pv_codigo'] ?? ''))
                                                                — {{ $pv['pv_nombre'] }}
                                                            @endif
                                                        </td>
                                                        <td class="text-center align-middle">{{ (int) ($pv['cantidad_rendiciones'] ?? 0) }}</td>
                                                        <td class="text-right align-middle font-weight-bold">{{ number_format((float) ($pv['venta_bruta'] ?? 0), 2, ',', '.') }}</td>
                                                        <td class="text-right align-middle">{{ number_format((float) ($pv['total_cobrado'] ?? 0), 2, ',', '.') }}</td>
                                                        <td class="text-right align-middle">{{ number_format((float) ($pv['total_invitaciones'] ?? 0), 2, ',', '.') }}</td>
                                                        <td class="text-right align-middle {{ abs($dif) > 0.02 ? 'text-danger font-weight-bold' : 'text-success' }}">
                                                            {{ number_format($dif, 2, ',', '.') }}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="7" class="p-0 border-0">
                                                            <div id="{{ $collapseId }}" class="collapse">
                                                                <div class="bg-light border-left border-right border-bottom px-3 py-2">
                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm table-striped table-bordered mb-0 bg-white">
                                                                            <thead style="background:#85C1E9;color:#17202A;">
                                                                                <tr>
                                                                                    <th>Medio de pago</th>
                                                                                    <th class="text-right">Cobros</th>
                                                                                    <th class="text-right">Neto medio</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                @forelse ($pv['medios'] ?? [] as $medio)
                                                                                    <tr>
                                                                                        <td>
                                                                                            <strong>{{ $medio['codigo'] ?? '' }}</strong>
                                                                                            @if (! empty($medio['nombre']))
                                                                                                — {{ $medio['nombre'] }}
                                                                                            @endif
                                                                                        </td>
                                                                        <td class="text-right">{{ number_format((float) ($medio['cobros'] ?? 0), 2, ',', '.') }}</td>
                                                                        <td class="text-right font-weight-bold">{{ number_format((float) ($medio['neto'] ?? 0), 2, ',', '.') }}</td>
                                                                    </tr>
                                                                @empty
                                                                    <tr>
                                                                        <td colspan="3" class="text-center text-muted py-2">Sin medios de pago registrados en este PV.</td>
                                                                                    </tr>
                                                                                @endforelse
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr style="background:#eaf2f8;font-weight:bold;">
                                                    <td></td>
                                                    <td>Total jornada</td>
                                                    <td class="text-center">{{ (int) ($totDia['cantidad_rendiciones'] ?? 0) }}</td>
                                                    <td class="text-right">{{ number_format((float) ($totDia['venta_bruta'] ?? 0), 2, ',', '.') }}</td>
                                                    <td class="text-right">{{ number_format((float) ($totDia['total_cobrado'] ?? 0), 2, ',', '.') }}</td>
                                                    <td class="text-right">{{ number_format((float) ($totDia['total_invitaciones'] ?? 0), 2, ',', '.') }}</td>
                                                    <td class="text-right">{{ number_format((float) ($totDia['diferencia_cobranza'] ?? 0), 2, ',', '.') }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="card border-info">
                            <div class="card-header py-2" style="background:#85C1E9;color:#17202A;">
                                <strong>Totales del rango</strong>
                            </div>
                            <div class="card-body py-2">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <td>Rendiciones</td>
                                                <td class="text-right">{{ (int) ($resumen['cantidad_rendiciones'] ?? 0) }}</td>
                                            </tr>
                                            <tr>
                                                <td>Venta total</td>
                                                <td class="text-right font-weight-bold">{{ number_format((float) ($resumen['venta_bruta'] ?? 0), 2, ',', '.') }}</td>
                                            </tr>
                                            <tr>
                                                <td>Cobrado</td>
                                                <td class="text-right">{{ number_format((float) ($resumen['total_cobrado'] ?? 0), 2, ',', '.') }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <strong class="d-block mb-1">Medios de pago (rango)</strong>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped table-bordered mb-0">
                                                <thead style="background:#85C1E9;color:#17202A;">
                                                    <tr>
                                                        <th>Medio</th>
                                                        <th class="text-right">Cobros</th>
                                                        <th class="text-right">Neto</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($mediosGlobal as $medio)
                                                        <tr>
                                                            <td>{{ $medio['codigo'] ?? '' }} — {{ $medio['nombre'] ?? '' }}</td>
                                                            <td class="text-right">{{ number_format((float) ($medio['cobros'] ?? 0), 2, ',', '.') }}</td>
                                                            <td class="text-right font-weight-bold">{{ number_format((float) ($medio['neto'] ?? 0), 2, ',', '.') }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="3" class="text-center text-muted">Sin medios</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
