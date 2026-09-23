@extends("theme.$theme.layout")
@section('titulo')
    Historial depósitos CHT
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cuentacaja/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cheque/historial_depositos.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cheque/historial_depositos.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $filtrosQuery = $filtrosQuery ?? [];
    $puedeEditarCheque = can('editar-cheque', false);
    $puedeEditarCliente = can('editar-clientes', false) || can('editar-cliente', false);
    $cuentaFiltroId = (int) ($filtros['cuentacaja_id'] ?? 0);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Historial de depósitos — boletas CHT</h3>
                <div class="card-tools d-flex flex-wrap align-items-center">
                    <a href="{{ route('conciliacion_deposito_cheque') }}" class="btn btn-outline-secondary btn-sm mr-2" title="Conciliación tránsito / acreditados">
                        <i class="fa fa-balance-scale"></i> Conciliación
                    </a>
                    <a href="{{ route('cheque') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a cheques
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('historial_deposito_cheque') }}" class="mb-3" id="form-historial-deposito-cheque">
                    <div class="form-row align-items-end">
                        @if (($empresa_query ?? collect())->count() > 1)
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Empresa</label>
                            <select name="empresa_id" class="form-control form-control-sm">
                                <option value="">Todas</option>
                                @foreach ($empresa_query as $emp)
                                    <option value="{{ $emp->id }}" @selected((int)($filtros['empresa_id'] ?? 0) === (int)$emp->id)>{{ $emp->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        @elseif (($empresa_query ?? collect())->count() === 1)
                            <input type="hidden" name="empresa_id" value="{{ $empresa_query->first()->id }}">
                        @endif
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Desde dep.</label>
                            <input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Hasta dep.</label>
                            <input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Boleta</label>
                            <input type="text" name="boleta" value="{{ $filtros['boleta'] ?? '' }}" class="form-control form-control-sm" placeholder="Nro. boleta">
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Buscar</label>
                            <input type="text" name="texto" value="{{ $filtros['texto'] ?? '' }}" class="form-control form-control-sm" placeholder="Nro / cliente…">
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <button type="submit" class="btn btn-info btn-sm">Consultar</button>
                            <a href="{{ route('historial_deposito_cheque') }}" class="btn btn-outline-secondary btn-sm" title="Volver al mes en curso">Mes actual</a>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6 mb-2">
                            @include('caja.partials.campo_consulta_cuentacaja', [
                                'prefix' => 'hist_dep',
                                'layout' => 'inline',
                                'label' => 'Cuenta depósito',
                                'inputName' => 'cuentacaja_id',
                                'inputId' => 'cuentacaja_id',
                                'cuentacajaId' => $cuentaFiltroId > 0 ? $cuentaFiltroId : '',
                                'codigo' => $cuentaFiltro->codigo ?? '',
                                'nombre' => $cuentaFiltro->nombre ?? '',
                                'required' => false,
                                'ayuda' => 'Vacío = todas. F1 o lupa abre la consulta.',
                            ])
                        </div>
                    </div>
                    <input type="hidden" name="estado" id="hist-estado" value="{{ $filtros['estado'] ?? '' }}">
                    <div class="mb-2">
                        <button type="submit" class="btn btn-sm mr-1 mb-1 {{ ($filtros['estado'] ?? '') === '' ? 'btn-primary' : 'btn-outline-secondary' }}" onclick="document.getElementById('hist-estado').value='';">
                            Todas
                        </button>
                        @foreach ($resumen['buckets'] as $key => $b)
                            <button type="submit" class="btn btn-sm mr-1 mb-1 {{ ($filtros['estado'] ?? '') === $key ? 'btn-primary' : 'btn-outline-secondary' }}" onclick="document.getElementById('hist-estado').value='{{ $key }}';">
                                {{ $b['label'] }} ({{ $b['cantidad'] }})
                            </button>
                        @endforeach
                    </div>
                </form>

                @include('includes.caja.modalconsultacuentacaja')

                <div class="row mb-3">
                    @foreach ($resumen['buckets'] as $key => $b)
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="border rounded p-2 h-100 {{ ($filtros['estado'] ?? '') === $key ? 'border-primary' : '' }}">
                                <div class="small text-muted">{{ $b['label'] }}</div>
                                <div><strong>{{ $b['cantidad'] }}</strong> boletas</div>
                                <div>{{ number_format($b['monto'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                    <p class="mb-0">
                        <strong>{{ $resumen['total_grupos'] }}</strong> boletas ·
                        <strong>{{ $resumen['total_cheques'] }}</strong> cheques —
                        @forelse ($resumen['totales_moneda'] as $tm)
                            {{ $tm['moneda'] }} {{ number_format($tm['monto'], 2, ',', '.') }}
                            @if (! $loop->last)
                                ·
                            @endif
                        @empty
                            {{ number_format($resumen['total_monto'], 2, ',', '.') }}
                        @endforelse
                    </p>
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'lista_historial_deposito_cheque',
                        'queryparams' => $filtrosQuery,
                        'variant' => 'compact',
                    ])
                </div>

                @include('includes.proceso_overlay_aviso', [
                    'overlayId' => 'historial-deposito-export-overlay',
                    'tituloId' => 'historial-deposito-export-titulo',
                    'subtituloId' => 'historial-deposito-export-subtitulo',
                    'titulo' => 'Exportando…',
                    'subtitulo' => 'Generando archivo. Pulse Esc para cerrar este aviso.',
                ])

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:2rem;"></th>
                                <th>Fecha dep.</th>
                                <th>Nro. boleta</th>
                                <th>Cuenta</th>
                                <th>Empresa</th>
                                <th class="text-right">Cheques</th>
                                <th class="text-right">Monto</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paginator as $g)
                                @php
                                    $rowId = 'hist-dep-'.md5($g['key']);
                                    $badge = match ($g['estado']) {
                                        'acreditado' => 'success',
                                        'rechazado' => 'danger',
                                        'mixto' => 'info',
                                        default => 'warning',
                                    };
                                    $monedasTxt = collect($g['totales_moneda'] ?? [])->map(function ($tm) {
                                        return $tm['moneda'].' '.number_format((float) $tm['monto'], 2, ',', '.');
                                    })->implode(' · ');
                                @endphp
                                <tr>
                                    <td class="text-center">
                                        <button type="button"
                                                class="btn-accion-tabla hist-dep-toggle"
                                                data-target="#{{ $rowId }}"
                                                title="Ver cheques de la boleta"
                                                aria-expanded="false">
                                            <i class="fa fa-chevron-right"></i>
                                        </button>
                                    </td>
                                    <td>{{ $g['fecha_deposito_dmy'] }}</td>
                                    <td>
                                        @if ($g['nro_boleta'] !== '')
                                            {{ $g['nro_boleta'] }}
                                        @else
                                            <span class="text-muted">(sin boleta)</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ $g['cuenta'] }}</td>
                                    <td class="small">{{ $g['empresa'] }}</td>
                                    <td class="text-right">{{ $g['cantidad'] }}</td>
                                    <td class="text-right small">{{ $monedasTxt !== '' ? $monedasTxt : number_format($g['monto'], 2, ',', '.') }}</td>
                                    <td>
                                        <span class="badge badge-{{ $badge }}">{{ $g['estado_label'] }}</span>
                                        <div class="small text-muted">
                                            @if (($g['estado_counts']['transito'] ?? 0) > 0)
                                                T:{{ $g['estado_counts']['transito'] }}
                                            @endif
                                            @if (($g['estado_counts']['acreditado'] ?? 0) > 0)
                                                A:{{ $g['estado_counts']['acreditado'] }}
                                            @endif
                                            @if (($g['estado_counts']['rechazado'] ?? 0) > 0)
                                                R:{{ $g['estado_counts']['rechazado'] }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-nowrap text-center">
                                        @if (! empty($g['url_comprobante_pdf']))
                                            <a href="{{ $g['url_comprobante_pdf'] }}"
                                               class="btn-accion-tabla tooltipsC"
                                               title="Reimprimir boleta PDF"
                                               target="_blank" rel="noopener">
                                                <i class="fa fa-file-pdf-o text-danger"></i>
                                            </a>
                                        @endif
                                        <a href="{{ $g['url_conciliacion'] }}"
                                           class="btn-accion-tabla tooltipsC"
                                           title="Ver en conciliación">
                                            <i class="fa fa-balance-scale text-primary"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr id="{{ $rowId }}" class="hist-dep-detalle d-none">
                                    <td colspan="9" class="bg-light p-2">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead style="background:#D6EAF8;color:#17202A;">
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Número</th>
                                                    <th>Int.</th>
                                                    <th class="text-right">Monto</th>
                                                    <th>Banco</th>
                                                    <th>Cliente</th>
                                                    <th>Acreditación</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($g['cheques'] as $ch)
                                                    <tr>
                                                        <td>
                                                            @if ($puedeEditarCheque)
                                                                <a class="text-primary" href="{{ route('editar_cheque', ['id' => $ch['id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}" target="_blank" rel="noopener">{{ $ch['id'] }}</a>
                                                            @else
                                                                {{ $ch['id'] }}
                                                            @endif
                                                        </td>
                                                        <td>{{ $ch['numerocheque'] }}</td>
                                                        <td>{{ $ch['nro_interno_anita'] }}</td>
                                                        <td class="text-right">{{ number_format($ch['monto'], 2, ',', '.') }} {{ $ch['moneda'] }}</td>
                                                        <td>{{ $ch['banco'] }}</td>
                                                        <td>
                                                            @if (! empty($ch['cliente_id']) && $puedeEditarCliente)
                                                                <a class="text-primary" href="{{ route('editar_cliente', $ch['cliente_id']) }}?origen=modal_consulta&amp;vista=consulta" target="_blank" rel="noopener">{{ $ch['cliente'] }}</a>
                                                            @else
                                                                {{ $ch['cliente'] }}
                                                            @endif
                                                        </td>
                                                        <td>{{ $ch['fecha_acreditacion'] }}</td>
                                                        <td>
                                                            <span class="badge badge-{{ $ch['estado'] === 'acreditado' ? 'success' : ($ch['estado'] === 'rechazado' ? 'danger' : 'warning') }}">
                                                                {{ $ch['estado_label'] }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted">Sin boletas de depósito para los filtros.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $paginator->appends($filtrosQuery)->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
