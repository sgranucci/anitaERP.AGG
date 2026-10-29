@extends("theme.$theme.layout")

@section('titulo')
    Conciliación ventas de artículos
@endsection

@section('contenido')
@php
    $fmt = static function ($v) {
        return number_format((float) $v, 2, ',', '.');
    };
    $r = $resultado ?? null;
    $informe = $r['informe'] ?? [];
    $contable = $r['contable'] ?? [];
    $cuentas = $r['cuentas'] ?? [];
    $vending = $r['vending'] ?? [];
    $etiquetaCuenta = static function (array $cuenta): string {
        $codigo = trim((string) ($cuenta['codigo'] ?? ''));
        $nombre = trim((string) ($cuenta['nombre'] ?? ''));
        if ($codigo === '' && $nombre === '') {
            return 'Sin cuenta configurada';
        }

        return trim($codigo.' '.$nombre);
    };
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-info">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Conciliación ventas de artículos vs contabilidad</h3>
                <div class="card-tools ml-auto">
                    <a href="{{ route('gastronomia_ventas_articulos_reporte', $filtrosQuery ?? []) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al informe
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('gastronomia_ventas_articulos_conciliacion') }}" id="form-conciliacion-ventas-articulos" class="mb-0">
                <div class="card-body pb-2">
                    <div class="alert alert-info py-2 small mb-3">
                        El importe a cruzar es <strong>Importe total vta. externa</strong> del informe.
                        En el Excel del 30/09/2026 esa columna es la F. Si el archivo trae «Costo unit.» antes del precio,
                        el importe queda una columna más a la derecha. La columna de venta externa a precio costo
                        no va a la contabilidad: es el costo de esa cantidad.
                        <ul class="mb-0 pl-3 mt-1">
                            <li>Ese importe es el total facturado, <strong>con IVA</strong>, de las ventas sin descuento de gastronomía. La nota de crédito resta.</li>
                            <li>Las cuentas de alimentos y bebidas y de tabaco están <strong>sin IVA</strong>. El IVA va a débito fiscal, solo el de esos asientos.</li>
                            <li>Invitación y staff no están en esa columna. Entran en las mismas cuentas por el total del comprobante, no por el precio de lista.</li>
                            <li>Las expendedoras no salen en este informe. Su cuenta queda aparte y no se suma.</li>
                        </ul>
                    </div>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => true,
                        'col_label' => 'col-lg-2 control-label text-right pr-2',
                        'col_input' => 'col-lg-4',
                    ])

                    <div class="form-group row">
                        <label for="fecha_desde" class="col-lg-2 control-label text-right pr-2 requerido">Desde jornada</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? '' }}" required>
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 control-label text-right pr-2 requerido">Hasta jornada</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}" required>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado && $r !== null)
                <div class="card-body border-top">
                    <p class="small mb-3">
                        <strong>Empresa:</strong> {{ $empresa_texto ?? '' }}
                        · <strong>Período:</strong> {{ $periodo_texto ?? '' }}
                    </p>

                    @if ($r['cierra'])
                        <div class="alert alert-success py-2">
                            Cierra. La diferencia es ${{ $fmt($r['diferencia']) }}
                            (tolerancia ${{ $fmt($r['tolerancia']) }}).
                        </div>
                    @else
                        <div class="alert alert-warning py-2">
                            No cierra. Diferencia ${{ $fmt($r['diferencia']) }}.
                            El desglose por día muestra dónde se abre.
                        </div>
                    @endif

                    <h5 class="mb-2">Puente</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr style="background:#85C1E9;color:#17202A;">
                                    <th>Concepto</th>
                                    <th class="text-right">Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Importe total vta. externa (informe)</td>
                                    <td class="text-right">{{ $fmt($informe['importe_externa'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <td>{{ $etiquetaCuenta($cuentas['alimentos'] ?? []) }}</td>
                                    <td class="text-right">{{ $fmt($contable['alimentos'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <td>{{ $etiquetaCuenta($cuentas['tabaco'] ?? []) }}</td>
                                    <td class="text-right">{{ $fmt($contable['tabaco'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <td>{{ $etiquetaCuenta($cuentas['iva'] ?? []) }} (solo asientos de esas ventas)</td>
                                    <td class="text-right">{{ $fmt($contable['iva'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <td>Suma contable con IVA</td>
                                    <td class="text-right">{{ $fmt($contable['bruto'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <td>Menos facturas de invitación (total del comprobante)</td>
                                    <td class="text-right">− {{ $fmt($informe['facturas_invitacion'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <td>Menos facturas de staff (total del comprobante)</td>
                                    <td class="text-right">− {{ $fmt($informe['facturas_staff'] ?? 0) }}</td>
                                </tr>
                                <tr class="font-weight-bold">
                                    <td>Contabilidad comparable</td>
                                    <td class="text-right">{{ $fmt($contable['comparable'] ?? 0) }}</td>
                                </tr>
                                <tr class="{{ $r['cierra'] ? 'table-success' : 'table-warning' }} font-weight-bold">
                                    <td>Diferencia (informe − contabilidad)</td>
                                    <td class="text-right">{{ $fmt($r['diferencia']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h5 class="mb-2">Cómo está armado el importe del informe</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr style="background:#85C1E9;color:#17202A;">
                                    <th>Parte</th>
                                    <th class="text-right">Importe</th>
                                    <th>Dónde va</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Venta externa — tabaco (líneas de cigarrillos)</td>
                                    <td class="text-right">{{ $fmt($informe['importe_externa_tabaco'] ?? 0) }}</td>
                                    <td>Dentro del importe del informe. En la cuenta de tabaco entra sin el IVA.</td>
                                </tr>
                                <tr>
                                    <td>Venta externa — bar (alimentos, bebidas y el resto)</td>
                                    <td class="text-right">{{ $fmt($informe['importe_externa_bar'] ?? 0) }}</td>
                                    <td>Dentro del importe del informe. En la cuenta de alimentos y bebidas entra sin el IVA.</td>
                                </tr>
                                <tr class="font-weight-bold">
                                    <td>Importe total vta. externa</td>
                                    <td class="text-right">{{ $fmt($informe['importe_externa'] ?? 0) }}</td>
                                    <td>Facturas sin descuento menos notas de crédito: {{ $fmt($informe['facturas_externa'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <td>Invitación</td>
                                    <td class="text-right">{{ $fmt($informe['facturas_invitacion'] ?? 0) }}</td>
                                    <td>No está en el importe del informe. Las columnas de venta interna valúan al costo o a precio de lista; la contabilidad toma este total facturado.</td>
                                </tr>
                                <tr>
                                    <td>Staff</td>
                                    <td class="text-right">{{ $fmt($informe['facturas_staff'] ?? 0) }}</td>
                                    <td>No está en el importe del informe. Entra en las cuentas por este total (cortesía, en general $0,01).</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    @if (abs((float) ($informe['notas_credito_en_positivo'] ?? 0)) > 0.05)
                        <div class="alert alert-warning py-2 small">
                            El informe está sumando notas de crédito en positivo
                            (${{ $fmt($informe['notas_credito_en_positivo']) }}).
                            Tienen que restar, igual que en la contabilidad.
                        </div>
                    @endif

                    <h5 class="mb-2">Expendedoras</h5>
                    @if (! empty($vending['incluido_en_informe']))
                        <div class="alert alert-warning py-2 small">
                            Hay {{ (int) ($vending['ventas'] ?? 0) }} comprobantes del informe emitidos en un punto de venta de expendedora.
                            La cuenta {{ $etiquetaCuenta($cuentas['vending'] ?? []) }} del período suma ${{ $fmt($vending['saldo'] ?? 0) }}.
                        </div>
                    @else
                        <p class="small">
                            Este informe no trae ítems de expendedoras.
                            {{ $etiquetaCuenta($cuentas['vending'] ?? []) }}
                            tiene ${{ $fmt($vending['saldo'] ?? 0) }} en el período y no se suma al puente.
                        </p>
                    @endif

                    @if (($contable['observaciones'] ?? []) !== [])
                        <p class="small text-muted mb-3">
                            Asientos tomados (haber neto de las tres cuentas):
                            @foreach ($contable['observaciones'] as $obs)
                                {{ $obs['observacion'] }} ${{ $fmt($obs['neto']) }}{{ $loop->last ? '' : ',' }}
                            @endforeach
                        </p>
                    @endif

                    <h5 class="mb-2">Por día</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover mb-0">
                            <thead>
                                <tr style="background:#85C1E9;color:#17202A;">
                                    <th>Jornada</th>
                                    <th class="text-right">Vta. externa</th>
                                    <th class="text-right">Invitación</th>
                                    <th class="text-right">Staff</th>
                                    <th class="text-right">Alimentos y bebidas</th>
                                    <th class="text-right">Tabaco</th>
                                    <th class="text-right">IVA</th>
                                    <th class="text-right">Comparable</th>
                                    <th class="text-right">Diferencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($r['dias'] as $dia)
                                    @php
                                        $abierta = abs((float) $dia['diferencia']) > (float) $r['tolerancia'];
                                    @endphp
                                    <tr class="{{ $abierta ? 'table-warning' : '' }}">
                                        <td>{{ \Carbon\Carbon::parse($dia['dia'])->format('d/m/Y') }}</td>
                                        <td class="text-right">{{ $fmt($dia['importe_externa']) }}</td>
                                        <td class="text-right">{{ $fmt($dia['facturas_invitacion']) }}</td>
                                        <td class="text-right">{{ $fmt($dia['facturas_staff']) }}</td>
                                        <td class="text-right">{{ $fmt($dia['alimentos']) }}</td>
                                        <td class="text-right">{{ $fmt($dia['tabaco']) }}</td>
                                        <td class="text-right">{{ $fmt($dia['iva']) }}</td>
                                        <td class="text-right">{{ $fmt($dia['comparable']) }}</td>
                                        <td class="text-right">{{ $fmt($dia['diferencia']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No hay movimientos en el período.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'conciliacion-ventas-articulos-overlay',
    'tituloId' => 'conciliacion-ventas-articulos-titulo',
    'subtituloId' => 'conciliacion-ventas-articulos-subtitulo',
    'titulo' => 'Conciliando…',
    'subtitulo' => 'Cruza el informe contra las cuentas del período. Puede demorar.',
])
@endsection

@section('scripts')
<script>
(function () {
    var form = document.getElementById('form-conciliacion-ventas-articulos');
    var overlay = document.getElementById('conciliacion-ventas-articulos-overlay');
    if (!form || !overlay) {
        return;
    }
    function ocultar() {
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }
    form.addEventListener('submit', function () {
        if (!form.checkValidity()) {
            return;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    });
    window.addEventListener('pageshow', ocultar);
})();
</script>
@endsection
