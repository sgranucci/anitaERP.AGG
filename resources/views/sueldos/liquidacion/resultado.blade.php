@extends("theme.$theme.layout")
@section('titulo')
    Resultado corrida N&deg; {{ $liq->numero }}
@endsection

@section('contenido')
@php
    $estadoBadge = [
        'borrador' => 'secondary', 'calculada' => 'info', 'revisada' => 'primary',
        'cerrada' => 'success', 'contabilizada' => 'dark', 'pagada' => 'success', 'anulada' => 'danger',
    ];
    $columnaBadge = ['haber' => 'success', 'descuento' => 'danger', 'neto' => 'primary', 'informativo' => 'secondary', 'contribucion' => 'warning'];
@endphp
<input type="hidden" name="_token" value="{{ csrf_token() }}">
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if(session('error'))<div class="alert alert-danger">{!! session('error') !!}</div>@endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Corrida N&deg; {{ $liq->numero }} &mdash; {{ $liq->descripcion }}
                    <span class="badge badge-{{ $estadoBadge[$liq->estado] ?? 'secondary' }} ml-1">{{ $liq->estadoLabel() }}</span>
                </h3>
                <div class="card-tools">
                    @if (can('listar-novedad-sueldos', false))
                        <a href="{{ route('novedades_liquidacion_sueldos', ['id' => $liq->id]) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fa fa-bolt"></i> Novedades
                        </a>
                    @endif
                    <a href="{{ route('consultar_liquidacion_sueldos') }}" class="btn btn-sm btn-secondary">
                        <i class="fa fa-arrow-left"></i> Volver
                    </a>
                    @if (can('editar-liquidacion-sueldos', false) && $liq->esEditable())
                        <form action="{{ route('calcular_liquidacion_sueldos', ['id' => $liq->id]) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-info"><i class="fa fa-calculator"></i> Recalcular</button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row text-center mb-2">
                    <div class="col"><small class="text-muted d-block">Empresa</small>{{ optional($liq->empresa)->nombre }}</div>
                    <div class="col"><small class="text-muted d-block">Alcance</small>{{ $liq->alcanceLabel() }}</div>
                    <div class="col"><small class="text-muted d-block">Per&iacute;odo</small>{{ $liq->periodo_mes ? sprintf('%02d/%04d', $liq->periodo_mes, $liq->periodo_anio) : $liq->periodo }}</div>
                    <div class="col"><small class="text-muted d-block">Recibos</small>{{ $totalesVisibles['cantidad'] ?? $liq->cantidad_recibos }}</div>
                    <div class="col"><small class="text-muted d-block">Remunerativo</small>$ {{ number_format((float) ($totalesVisibles['rem'] ?? $liq->total_remunerativo), 2, ',', '.') }}</div>
                    <div class="col"><small class="text-muted d-block">No remunerativo</small>$ {{ number_format((float) ($totalesVisibles['norem'] ?? $liq->total_no_remunerativo), 2, ',', '.') }}</div>
                    <div class="col"><small class="text-muted d-block">Descuentos</small>$ {{ number_format((float) ($totalesVisibles['desc'] ?? $liq->total_descuentos), 2, ',', '.') }}</div>
                    <div class="col"><small class="text-muted d-block">Neto</small><strong>$ {{ number_format((float) ($totalesVisibles['neto'] ?? $liq->total_neto), 2, ',', '.') }}</strong></div>
                </div>
                <div class="mb-2 d-flex align-items-center flex-wrap">
                    <div class="custom-control custom-checkbox mr-3">
                        <input type="checkbox" class="custom-control-input" id="chk-multiempresa-emision"
                               {{ $liq->esAlcanceMultiempresa() ? 'checked' : '' }}>
                        <label class="custom-control-label" for="chk-multiempresa-emision">
                            Incluir recibos de otras empresas
                            <small class="text-muted">(mismo legajo, per&iacute;odo y tipo en otras empresas)</small>
                        </label>
                    </div>
                    <a href="{{ route('pdf_recibos_liquidacion_sueldos', ['id' => $liq->id]) }}"
                       id="btn-pdf-corrida-completa"
                       class="btn btn-sm btn-outline-danger mr-2"
                       data-base="{{ route('pdf_recibos_liquidacion_sueldos', ['id' => $liq->id]) }}">
                        <i class="fa fa-file-pdf"></i> PDF de toda la corrida
                    </a>
                    @if (! empty($puedeImportarConfidencial))
                        <button type="button" class="btn btn-sm btn-outline-warning" id="btn-analizar-confidencial"
                                data-url="{{ route('analizar_confidencial_liquidacion_sueldos', ['id' => $liq->id]) }}">
                            <i class="fa fa-user-secret"></i> Importar n&oacute;mina confidencial
                        </button>
                    @endif
                </div>

                @include('sueldos.liquidacion.partials.asiento')

                <div class="table-responsive p-0">
                    <table class="table table-sm table-bordered table-hover">
                        <thead>
                            <tr style="background-color:#85C1E9;color:#17202A;">
                                <th style="width:60px">Recibo</th>
                                <th style="width:70px">Legajo</th>
                                <th>Empleado</th>
                                <th style="width:120px">CUIL</th>
                                <th class="text-right" style="width:110px">Remunerativo</th>
                                <th class="text-right" style="width:110px">No remunerativo</th>
                                <th class="text-right" style="width:100px">Descuentos</th>
                                <th class="text-right" style="width:110px">Neto</th>
                                <th style="width:160px">Otras empresas</th>
                                <th class="text-nowrap" style="width:220px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recibos as $rec)
                                @php
                                    $hermanos = $hermanosPorRecibo[$rec->id] ?? collect();
                                @endphp
                                <tr>
                                    <td>{{ $rec->numero_recibo }}</td>
                                    <td>{{ $rec->legajo }}</td>
                                    <td>
                                        {{ $rec->apellido_nombre }}
                                        @if (! empty($rec->confidencial) || ! empty(optional($rec->empleado)->confidencial))
                                            <span class="badge badge-warning ml-1">Conf.</span>
                                        @endif
                                    </td>
                                    <td>{{ $rec->cuil }}</td>
                                    <td class="text-right">{{ number_format((float) $rec->total_remunerativo, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) $rec->total_no_remunerativo, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) $rec->total_descuentos, 2, ',', '.') }}</td>
                                    <td class="text-right"><strong>{{ number_format((float) $rec->neto_a_pagar, 2, ',', '.') }}</strong></td>
                                    <td>
                                        @if ($hermanos->isEmpty())
                                            <span class="text-muted">—</span>
                                        @else
                                            <span class="badge badge-info">+{{ $hermanos->count() }}</span>
                                            @foreach ($hermanos as $h)
                                                <div class="small">
                                                    {{ optional(optional($h->liquidacion)->empresa)->nombre ?? ('Emp. '.$h->liquidacion->empresa_id) }}
                                                    <span class="text-muted">N&deg;{{ optional($h->liquidacion)->numero }} · ${{ number_format((float) $h->neto_a_pagar, 2, ',', '.') }}</span>
                                                </div>
                                            @endforeach
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <button class="btn btn-xs btn-outline-secondary" type="button" data-toggle="collapse" data-target="#det-{{ $rec->id }}">
                                            <i class="fa fa-list"></i>
                                        </button>
                                        <a href="{{ route('preview_recibo_liquidacion_sueldos', ['id' => $liq->id, 'reciboId' => $rec->id, 'multiempresa' => 0]) }}"
                                           class="btn btn-xs btn-outline-primary"
                                           target="_blank" rel="opener" title="Vista previa Anexo III">
                                            <i class="fa fa-file-alt"></i>
                                        </a>
                                        <a href="{{ route('pdf_recibo_liquidacion_sueldos', ['id' => $liq->id, 'reciboId' => $rec->id, 'multiempresa' => 0]) }}"
                                           class="btn btn-xs btn-outline-danger"
                                           target="_blank" rel="noopener" title="PDF individual">
                                            <i class="fa fa-file-pdf"></i>
                                        </a>
                                        @if ($hermanos->isNotEmpty())
                                            <a href="{{ route('pdf_recibo_liquidacion_sueldos', ['id' => $liq->id, 'reciboId' => $rec->id, 'multiempresa' => 1]) }}"
                                               class="btn btn-xs btn-outline-warning"
                                               target="_blank" rel="noopener" title="PDF con otras empresas del legajo">
                                                <i class="fa fa-building"></i>
                                            </a>
                                            <a href="{{ route('preview_recibo_liquidacion_sueldos', ['id' => $liq->id, 'reciboId' => $rec->id, 'multiempresa' => 1]) }}"
                                               class="btn btn-xs btn-outline-info"
                                               target="_blank" rel="opener" title="Vista previa multiempresa">
                                                <i class="fa fa-layer-group"></i>
                                            </a>
                                        @endif
                                        <a href="{{ route('trazar_liquidacion_sueldos', ['id' => $liq->id, 'empleadoId' => $rec->empleado_id]) }}"
                                           class="btn btn-xs btn-outline-secondary" target="_blank" rel="noopener" title="Depurar cálculo paso a paso">
                                            <i class="fa fa-bug"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr class="collapse" id="det-{{ $rec->id }}">
                                    <td colspan="10" class="p-0">
                                        <table class="table table-sm mb-0" style="background:#fbfcfd;">
                                            <thead>
                                                <tr class="text-muted">
                                                    <th style="width:70px">C&oacute;d.</th>
                                                    <th>Concepto</th>
                                                    <th style="width:110px">Columna</th>
                                                    <th class="text-right" style="width:90px">Cant.</th>
                                                    <th class="text-right" style="width:110px">Valor</th>
                                                    <th class="text-right" style="width:120px">Importe</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($rec->detalles as $d)
                                                    <tr>
                                                        <td>{{ $d->concepto_codigo }}</td>
                                                        <td>{{ $d->concepto_descripcion }}</td>
                                                        <td><span class="badge badge-{{ $columnaBadge[$d->columna] ?? 'secondary' }}">{{ \App\Models\Sueldos\Liquidacion_Detalle_Sueldos::COLUMNAS[$d->columna] ?? $d->columna }}</span></td>
                                                        <td class="text-right">{{ rtrim(rtrim(number_format((float) $d->cantidad, 4, ',', '.'), '0'), ',') }}</td>
                                                        <td class="text-right">{{ number_format((float) $d->valor, 2, ',', '.') }}</td>
                                                        <td class="text-right">{{ number_format((float) $d->importe, 2, ',', '.') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-center text-muted">La corrida no tiene recibos calculados. Use el bot&oacute;n Calcular.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $recibos->links() }}
            </div>
        </div>
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'liq-recibos-overlay',
    'tituloId' => 'liq-recibos-overlay-titulo',
    'subtituloId' => 'liq-recibos-overlay-subtitulo',
    'titulo' => 'Generando PDF…',
    'subtitulo' => 'Puede demorar según la cantidad de recibos. No cierre la página.',
])

@include('sueldos.liquidacion.partials.modal_import_confidencial')

<script src="{{ asset('assets/pages/scripts/sueldos/liquidacion/resultado.js') }}"></script>
@endsection
