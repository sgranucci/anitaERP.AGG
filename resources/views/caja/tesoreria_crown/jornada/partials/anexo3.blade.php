@php
    $pesos = function ($importe) {
        if ($importe === null || $importe === '' || abs((float) $importe) < 0.001) {
            return '';
        }

        return number_format((float) $importe, 2, ',', '.');
    };
    $cols = 22;
    $celdasAudit = [
        'cierre_x', 'egresos', 'efectivo', 'tarjeta_credito', 'tarjeta_debito', 'total_qr',
        'recargo_fin', 'ctacte', 'ingresos', 'hab_fichas', 'rend_fichas', 'fichas_casino',
        'pago_cashless', 'vta_cashless', 'pago_online', 'venta_online', 'online', 'apuestas',
        'resultado', 'diferencia',
    ];
    $auditLink = function (float $valor, string $columna, string $scope, ?int $asignacionId = null, ?string $tipo = null) use ($pesos) {
        $texto = $pesos($valor);
        if ($texto === '') {
            return '';
        }
        $attrs = 'type="button" class="btn btn-link btn-sm p-0 text-primary js-anexo3-audit"';
        $attrs .= ' data-columna="'.e($columna).'" data-scope="'.e($scope).'"';
        if ($asignacionId) {
            $attrs .= ' data-asignacion-id="'.(int) $asignacionId.'"';
        }
        if ($tipo) {
            $attrs .= ' data-tipo="'.e($tipo).'"';
        }
        $attrs .= ' title="Auditar movimientos de '.e($columna).'"';

        return '<button '.$attrs.'>'.$texto.'</button>';
    };
@endphp
<div class="card card-outline card-info mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-list-alt"></i> Anexo III — Cajeros ampliado</h3>
    </div>
    <div class="card-body pb-2">
        @include('caja.tesoreria_crown.jornada.partials.anexos_tabs')
        <p class="text-muted small mb-2">
            Formato de <code>l-anexo3new.c</code> (ENCAB1). Cada importe azul abre el detalle de los movimientos que lo componen.
        </p>
        @if ($anexo3 === null)
            <div class="alert alert-warning mb-0">No se pudo armar el anexo III. {{ $anexo3Error }}</div>
        @else
            @foreach ($anexo3['avisos'] as $aviso)
                <div class="alert alert-warning py-2">{{ $aviso }}</div>
            @endforeach
            <p class="small text-muted mb-1">
                Tabla ancha: usá la <strong>barra de scroll de arriba</strong>, o Shift + rueda del mouse / deslizá el trackpad.
            </p>
            <div class="tc-orden-wrap">
                @include('caja.tesoreria_crown.jornada.partials.anexo_orden_barra')
                <div class="tabla-ancha-grilla mb-3" data-tabla-ancha
                     style="--tabla-ancha-c1: 12rem; --tabla-ancha-c2: 14rem;">
                    <div class="tabla-ancha-scroll-top" hidden>
                        <div class="tabla-ancha-scroll-top-inner"></div>
                    </div>
                    <div class="tabla-ancha-wrap">
                        <table class="table table-sm table-bordered mb-0 tabla-ancha tc-tabla-ordenable" id="tabla-paginada">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th class="col-fija-1">Caja</th>
                                    <th class="col-fija-2">Cajero</th>
                                    <th class="text-right">Cierre X</th>
                                    <th class="text-right">Egresos</th>
                                    <th class="text-right">Efectivo</th>
                                    <th class="text-right">T. crédito</th>
                                    <th class="text-right">T. débito</th>
                                    <th class="text-right">QR</th>
                                    <th class="text-right">Recargo</th>
                                    <th class="text-right">Cta.cte.</th>
                                    <th class="text-right">Ingresos</th>
                                    <th class="text-right">Hab. fichas</th>
                                    <th class="text-right">Rend. fichas</th>
                                    <th class="text-right">Fichas Casino</th>
                                    <th class="text-right">Pago cashless</th>
                                    <th class="text-right">Vta. cashless</th>
                                    <th class="text-right">Pagos online</th>
                                    <th class="text-right">Ventas online</th>
                                    <th class="text-right">Del Rio ONLINE</th>
                                    <th class="text-right">Apuestas Dep.</th>
                                    <th class="text-right">Resultado</th>
                                    <th class="text-right">Dif. cajero</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($anexo3['grupos'] as $grupo)
                                    <tr style="background:#D6EAF8;font-weight:bold;" data-tc-fija="1">
                                        <td class="col-fija-1" colspan="2">{{ $grupo['titulo'] }}</td>
                                        <td colspan="{{ $cols - 2 }}"></td>
                                    </tr>
                                    @foreach ($grupo['filas'] as $fila)
                                        @php
                                            $scopeFila = !empty($fila['propina']) ? 'propina' : 'fila';
                                            $asignacionId = (int) ($fila['asignacion_id'] ?? 0) ?: null;
                                        @endphp
                                        <tr>
                                            <td class="col-fija-1">
                                                @if ($asignacionId && empty($fila['propina']))
                                                    <a class="text-primary" target="_blank" rel="noopener"
                                                       href="{{ route('ver_tesoreria_crown_cierre', $asignacionId) }}">{{ $fila['caja'] }}</a>
                                                @else
                                                    {{ $fila['caja'] }}
                                                @endif
                                            </td>
                                            <td class="col-fija-2">{{ $fila['cajero'] }}</td>
                                            @foreach ($celdasAudit as $columna)
                                                <td class="text-right">{!! $auditLink((float) ($fila[$columna] ?? 0), $columna, $scopeFila, $asignacionId) !!}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                    @php $tg = $grupo['totales']; @endphp
                                    <tr style="font-weight:bold;background:#f5f5f5;" data-tc-fija="1">
                                        <td class="col-fija-1" colspan="2">Total {{ $grupo['titulo'] }}</td>
                                        @foreach ($celdasAudit as $columna)
                                            <td class="text-right">{!! $auditLink((float) ($tg[$columna] ?? 0), $columna, 'grupo', null, (string) $grupo['tipo']) !!}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr data-tc-fija="1"><td colspan="{{ $cols }}" class="text-center text-muted">Sin filas.</td></tr>
                                @endforelse
                                @if (($anexo3['grupos'] ?? []) !== [])
                                    @php $t = $anexo3['totales']; @endphp
                                    <tr style="font-weight:bold;background:#D5F5E3;" data-tc-fija="1">
                                        <td class="col-fija-1" colspan="2">Total general</td>
                                        @foreach ($celdasAudit as $columna)
                                            <td class="text-right">{!! $auditLink((float) ($t[$columna] ?? 0), $columna, 'total') !!}</td>
                                        @endforeach
                                    </tr>
                                    <tr style="font-weight:bold;" data-tc-fija="1">
                                        <td class="col-fija-1" colspan="2">Total de Tarj. Credito + Tarj. Debito + Total QR + Recargo fin.</td>
                                        <td colspan="{{ $cols - 2 }}" class="text-right">
                                            {!! $auditLink((float) ($anexo3['total_tarjetas'] ?? 0), 'pie_tarjetas', 'pie_tarjetas') !!}
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="modal-anexo3-auditoria" tabindex="-1" role="dialog" aria-labelledby="anexo3-auditoria-titulo"
     data-url="{{ route('auditoria_anexo3_tesoreria_crown_jornada', $jornada->id) }}">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div>
                    <h5 class="modal-title mb-0" id="anexo3-auditoria-titulo">Auditoría</h5>
                    <div class="small text-muted" id="anexo3-auditoria-subtitulo"></div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="anexo3-auditoria-formula" hidden></p>
                <div id="anexo3-auditoria-avisos"></div>
                <p class="text-muted mb-2" id="anexo3-auditoria-cargando" hidden>Cargando movimientos…</p>
                <p class="text-muted mb-0" id="anexo3-auditoria-vacio" hidden>Ningún movimiento aporta a este importe.</p>
                <div id="anexo3-auditoria-lista"></div>
            </div>
            <div class="modal-footer py-2">
                <a href="#" class="btn btn-outline-info btn-sm" id="anexo3-auditoria-cierre" target="_blank" rel="noopener" hidden>
                    Abrir cierre de caja
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/anexo3_auditoria.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/anexo3_auditoria.js')) }}"></script>
