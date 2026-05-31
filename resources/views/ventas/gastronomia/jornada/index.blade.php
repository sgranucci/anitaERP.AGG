@extends("theme.$theme.layout")
@section('titulo')
    Jornada gastronomía
@endsection

@section("scripts")
<script>
    window.JORNADA_GASTRONOMIA = {
        csrf: @json(csrf_token()),
        urlComprobanteTotemBase: @json(route('gastronomia_jornada_comprobante_cierre_totem', ['jornadaId' => '__JORNADA_ID__', 'inline' => 1])),
        urlInformeZDatosBase: @json(url('ventas/gastronomia/jornada/api/informe-z/__JORNADA_ID__')),
        urlInformeZGuardar: @json(route('gastronomia_jornada_api_informe_z_guardar')),
        toleranciaInformeZ: @json((float) config('gastronomia.cierre_totem_informe_z_tolerancia', 0.02)),
    };
</script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/jornada.js') }}?v={{ filemtime(public_path('assets/pages/scripts/ventas/gastronomia/jornada.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="jornada-gastronomia-app"
     data-api-estado="{{ url('ventas/gastronomia/jornada/api/estado') }}"
     data-api-abrir="{{ url('ventas/gastronomia/jornada/api/abrir') }}"
     data-api-cerrar="{{ url('ventas/gastronomia/jornada/api/cerrar') }}"
     data-api-eliminar="{{ url('ventas/gastronomia/jornada/api/eliminar') }}"
     data-cierre-totem-habilitado="{{ ! empty($cierre_totem_habilitado) ? '1' : '0' }}"
     data-csrf="{{ csrf_token() }}"
     data-puede-abrir="{{ $puede_abrir ? '1' : '0' }}"
     data-puede-cerrar="{{ $puede_cerrar ? '1' : '0' }}"
     data-puede-eliminar="{{ ($puede_eliminar ?? false) ? '1' : '0' }}">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Apertura y cierre de jornada</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    La <strong>fecha de factura</strong> de cada comprobante es siempre el día calendario real.
                    La <strong>fecha de jornada</strong> es la del turno abierto y se graba en <code>venta.fechajornada</code>
                    para todas las terminales de la empresa.
                </p>
                @if (! empty($cierre_totem_habilitado))
                    <p class="text-muted small mb-3">
                        Al <strong>cerrar la jornada</strong> se consultan órdenes Waitry desde la
                        <strong>fecha y hora de apertura</strong> hasta la <strong>fecha y hora de cierre</strong>
                        (ej. jornada 30/05 cerrada el 31/05: comandas del 30/05 18:00 al 31/05 07:00),
                        se calculan <strong>totales por medio de pago por tótem</strong>.
                        Luego cargá el <strong>Informe Z</strong> de cada tótem para comparar con el sistema.
                        El detalle de auditoría lista <strong>solo discrepancias</strong>, si las hay.
                        @if ((int) ($ultimo_waitry_order_id ?? 0) > 0)
                            Último ID Waitry incluido en cierres anteriores:
                            <strong>#{{ (int) $ultimo_waitry_order_id }}</strong>
                            (el próximo cierre tomará órdenes con ID &gt; {{ (int) $ultimo_waitry_order_id }}).
                        @else
                            Aún no hay cierres Waitry registrados para esta empresa.
                        @endif
                    </p>
                @endif

                <form method="get" action="{{ url('ventas/gastronomia/jornada') }}" class="form-inline mb-4">
                    <label class="mr-2" for="empresa_id">Empresa</label>
                    <select name="empresa_id" id="empresa_id" class="form-control mr-2">
                        @foreach ($empresas as $emp)
                            <option value="{{ $emp->id }}" @selected((int) $empresa_id === (int) $emp->id)>
                                {{ $emp->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-secondary btn-sm">Consultar</button>
                </form>

                @if ($estado)
                    <div id="jornada-estado-panel" class="mb-4">
                        @if ($estado['jornada_abierta'])
                            <div class="alert alert-success">
                                <strong>Jornada abierta</strong>
                                — Fecha jornada:
                                <span id="lbl-fecha-jornada">{{ $estado['fecha_jornada_fmt'] ?? $estado['fecha_jornada'] }}</span>
                                · Facturas de hoy usan fecha
                                <span id="lbl-fecha-factura">{{ $estado['fecha_factura_hoy_fmt'] ?? $estado['fecha_factura_hoy'] }}</span>
                                @if (! empty($estado['usuario_apertura']))
                                    <br>Abierta por {{ $estado['usuario_apertura'] }}
                                    @if (! empty($estado['apertura_en']))
                                        ({{ $estado['apertura_en'] }})
                                    @endif
                                @endif
                                @if (! empty($estado['observacion_apertura']))
                                    <br><em>{{ $estado['observacion_apertura'] }}</em>
                                @endif
                            </div>
                        @else
                            <div class="alert alert-warning">
                                <strong>Sin jornada abierta</strong> para esta empresa.
                                Las terminales de facturación no podrán emitir comprobantes hasta abrir la jornada.
                            </div>
                        @endif
                    </div>

                    <div class="row mb-4">
                        @if ($puede_abrir)
                            <div class="col-md-6">
                                <div class="card card-outline card-success @if ($estado['jornada_abierta']) opacity-50 @endif">
                                    <div class="card-header">Abrir jornada</div>
                                    <div class="card-body">
                                        @if (! empty($estado['motivo_no_puede_abrir']))
                                            <p class="text-muted small mb-2">{{ $estado['motivo_no_puede_abrir'] }}</p>
                                        @endif
                                        <div class="form-group">
                                            <label for="fecha_jornada_abrir">Fecha de jornada (turno)</label>
                                            <input type="date" class="form-control" id="fecha_jornada_abrir"
                                                   value="{{ $fecha_hoy }}"
                                                   @if (! empty($fecha_jornada_minima)) min="{{ $fecha_jornada_minima }}" @endif
                                                   @disabled($estado['jornada_abierta'])>
                                        </div>
                                        <div class="form-group">
                                            <label for="observacion_abrir">Observación</label>
                                            <textarea class="form-control" id="observacion_abrir" rows="2"
                                                      @disabled($estado['jornada_abierta'])></textarea>
                                        </div>
                                        <button type="button" class="btn btn-success" id="btn-abrir-jornada"
                                                @disabled($estado['jornada_abierta'])>
                                            Abrir jornada
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($puede_cerrar)
                            <div class="col-md-6">
                                <div class="card card-outline card-danger @if (! $estado['jornada_abierta']) opacity-50 @endif">
                                    <div class="card-header">Cerrar jornada</div>
                                    <div class="card-body">
                                        @if (! empty($estado['errores_cierre']))
                                            @php
                                                $mostrarLinkSaneamiento = false;
                                                foreach ($estado['errores_cierre'] as $err) {
                                                    if (stripos($err, 'Saneamiento') !== false) {
                                                        $mostrarLinkSaneamiento = true;
                                                        break;
                                                    }
                                                }
                                            @endphp
                                            <ul class="text-danger small" id="lista-errores-cierre">
                                                @foreach ($estado['errores_cierre'] as $err)
                                                    <li>{{ $err }}</li>
                                                @endforeach
                                            </ul>
                                            @if ($mostrarLinkSaneamiento)
                                                <div class="mb-2">
                                                    <a href="{{ url('ventas/gastronomia/saneamiento-turno') }}"
                                                       class="btn btn-warning btn-sm"
                                                       target="_blank" rel="noopener">
                                                        <i class="fa fa-external-link"></i>
                                                        Ir a Saneamiento de turnos
                                                    </a>
                                                </div>
                                            @endif
                                        @endif
                                        @if (! empty($estado['nota_politica_turnos']))
                                            <p class="text-muted small mb-2">{{ $estado['nota_politica_turnos'] }}</p>
                                        @endif
                                        @if (! empty($estado['turnos_habilitados']))
                                            <div class="small mb-2">
                                                <strong>Turnos habilitados sin cerrar:</strong>
                                                <ul class="mb-1 pl-3">
                                                    @foreach ($estado['turnos_habilitados'] as $th)
                                                        <li>
                                                            {{ $th['identificador_pc'] }} — {{ $th['turno_nombre'] }}
                                                            <span class="badge badge-danger">sin cerrar</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                                <a href="{{ $url_saneamiento_turno ?? url('ventas/gastronomia/saneamiento-turno') }}?empresa_id={{ $empresa_id }}"
                                                   class="btn btn-outline-warning btn-sm" target="_blank" rel="noopener">
                                                    Cierre remoto de turnos
                                                </a>
                                            </div>
                                        @endif
                                        @if (! empty($estado['cuentas_abiertas_vacias']))
                                            <div class="alert alert-info small py-2 mb-2">
                                                {{ $estado['cuentas_abiertas_vacias'] }} cuenta(s) abierta(s) sin ítems se
                                                <strong>descartarán automáticamente</strong> al cerrar la jornada
                                                (no requieren saneamiento).
                                            </div>
                                        @endif
                                        <div class="form-group">
                                            <label for="observacion_cerrar">Observación de cierre</label>
                                            <textarea class="form-control" id="observacion_cerrar" rows="2"
                                                      @disabled(! $estado['jornada_abierta'])></textarea>
                                        </div>
                                        <button type="button" class="btn btn-danger" id="btn-cerrar-jornada"
                                                @disabled(! $estado['jornada_abierta'] || ! empty($estado['errores_cierre']))>
                                            Cerrar jornada
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                <h5>Historial de cierres de jornada</h5>
                <p class="text-muted small mb-2">
                    Reimprima el comprobante de ingresos tótem desde la columna <strong>Comprobante</strong> de cualquier jornada cerrada.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha jornada</th>
                                <th>Estado</th>
                                <th>Apertura</th>
                                <th>Cierre</th>
                                <th>Usuario apertura</th>
                                <th>Usuario cierre</th>
                                @if (! empty($cierre_totem_habilitado))
                                    <th>Órdenes Waitry</th>
                                    <th>Comprobante</th>
                                @endif
                                @if ($puede_eliminar ?? false)
                                    <th>Acciones</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($historial as $j)
                                @php
                                    $elim = $eliminacion_por_jornada[$j->id] ?? null;
                                @endphp
                                <tr>
                                    <td>{{ $j->id }}</td>
                                    <td>{{ $j->fecha_jornada->format('d/m/Y') }}</td>
                                    <td>{{ ucfirst($j->estado) }}</td>
                                    <td>{{ $j->apertura_en?->format('d/m/Y H:i') }}</td>
                                    <td>{{ $j->cierre_en?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td>{{ $j->usuarioApertura->nombre ?? '—' }}</td>
                                    <td>{{ $j->usuarioCierre->nombre ?? '—' }}</td>
                                    @if (! empty($cierre_totem_habilitado))
                                        <td class="small">
                                            @if ($j->cierreTotem)
                                                @php $ct = $j->cierreTotem; @endphp
                                                @if ($ct->waitry_order_id_desde)
                                                    #{{ $ct->waitry_order_id_desde }}
                                                    @if ($ct->waitry_order_id_hasta && $ct->waitry_order_id_hasta !== $ct->waitry_order_id_desde)
                                                        — #{{ $ct->waitry_order_id_hasta }}
                                                    @endif
                                                    ({{ $ct->cantidad_lineas }} órdenes)
                                                @else
                                                    Sin órdenes nuevas (último ID #{{ $ct->waitry_order_id_hasta }})
                                                @endif
                                            @elseif ($j->estado === 'cerrada')
                                                —
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            @if ($j->cierreTotem)
                                                <a href="{{ route('gastronomia_jornada_comprobante_cierre_totem', ['jornadaId' => $j->id, 'inline' => 1]) }}"
                                                   class="btn btn-outline-secondary btn-xs js-ver-cierre-totem"
                                                   target="_blank" rel="noopener" title="Ver PDF">
                                                    <i class="fa fa-file-pdf-o"></i>
                                                </a>
                                                <button type="button"
                                                        class="btn btn-outline-info btn-xs js-informe-z"
                                                        data-jornada-id="{{ $j->id }}"
                                                        title="Informe Z / conciliación">
                                                    <i class="fa fa-balance-scale"></i>
                                                </button>
                                                <button type="button"
                                                        class="btn btn-outline-primary btn-xs js-imprimir-cierre-totem"
                                                        data-jornada-id="{{ $j->id }}"
                                                        title="Reimprimir comprobante">
                                                    <i class="fa fa-print"></i>
                                                </button>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endif
                                    @if ($puede_eliminar ?? false)
                                        <td class="text-nowrap">
                                            @if (! empty($elim['puede_eliminar']))
                                                <button type="button"
                                                        class="btn btn-outline-danger btn-xs js-eliminar-jornada"
                                                        data-jornada-id="{{ $j->id }}"
                                                        data-fecha-jornada="{{ $j->fecha_jornada->format('d/m/Y') }}"
                                                        title="Eliminar jornada sin movimientos">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            @else
                                                <span class="text-muted small"
                                                      title="{{ $elim['motivo_no_eliminar'] ?? 'Tiene movimientos' }}">—</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 7 + (! empty($cierre_totem_habilitado) ? 2 : 0) + (($puede_eliminar ?? false) ? 1 : 0) }}" class="text-center text-muted">Sin registros.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@if (! empty($cierre_totem_habilitado))
<div class="modal fade" id="modal-informe-z-totem" tabindex="-1" role="dialog" aria-labelledby="modal-informe-z-totem-label" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-informe-z-totem-label">Conciliación Informe Z — tótems Waitry</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small" id="informe-z-subtitulo"></p>
                <div id="informe-z-resultado" class="d-none mb-3"></div>
                <div id="informe-z-contenido" class="text-muted">Cargando…</div>
            </div>
            <div class="modal-footer flex-wrap">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-outline-secondary d-none" id="btn-informe-z-omitir-imprimir">
                    Omitir e imprimir
                </button>
                <button type="button" class="btn btn-primary" id="btn-informe-z-guardar" disabled>
                    Guardar Informe Z y conciliar
                </button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
