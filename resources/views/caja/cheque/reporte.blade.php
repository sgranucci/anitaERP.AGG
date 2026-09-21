@extends("theme.$theme.layout")
@section('titulo')
    Reporte de cheques
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/caja/cheque/reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cheque/reporte.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'cheque-reporte-overlay',
    'tituloId' => 'cheque-reporte-titulo',
    'subtituloId' => 'cheque-reporte-subtitulo',
    'titulo' => 'Armando el reporte…',
    'subtitulo' => 'Puede demorar según la cantidad de cheques. No cierre la página.',
])
@php
    use App\Support\Caja\ChequeDepositoComprobanteSupport;
    use App\Support\Caja\ChequeReporteFiltros;
    use App\Support\Caja\ChequeReporteSupport;
    $f = $filtros ?? [];
    $tipo = ($f['tipo'] ?? 'E') === 'R' ? 'R' : 'E';
    $consultado = ! empty($consultado);
    $multiEmpresa = ($empresa_query ?? collect())->count() > 1;
    $colspan = $multiEmpresa ? 14 : 13;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Reporte de cheques emitidos y recibidos</h3>
                <div class="card-tools">
                    <a href="{{ route('cheque') }}" class="btn btn-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a cheques
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('reporte_cheque') }}" id="form-reporte-cheque" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body border-bottom">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3 mb-2">
                            <label class="small mb-1 d-block">Tipo</label>
                            <div class="btn-group btn-group-sm btn-group-toggle" data-toggle="buttons" id="grp-tipo-cheque">
                                <label class="btn btn-outline-primary {{ $tipo === 'E' ? 'active' : '' }}">
                                    <input type="radio" name="tipo" value="E" autocomplete="off" {{ $tipo === 'E' ? 'checked' : '' }}> Emitidos
                                </label>
                                <label class="btn btn-outline-primary {{ $tipo === 'R' ? 'active' : '' }}">
                                    <input type="radio" name="tipo" value="R" autocomplete="off" {{ $tipo === 'R' ? 'checked' : '' }}> Recibidos
                                </label>
                            </div>
                        </div>
                        <div class="form-group col-md-3 mb-2">
                            <label class="small mb-1" for="empresa_id">Empresa</label>
                            @include('includes.form-empresa-asignada-control', [
                                'empresa_query' => $empresa_query,
                                'empresa_id' => $f['empresa_id'] ?? '',
                                'required' => false,
                                'permite_vacio' => ($empresa_query ?? collect())->count() > 1,
                                'opcion_vacia' => 'Todas mis empresas',
                                'select_class' => 'form-control-sm',
                            ])
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-1" for="orden">Ordenar por</label>
                            <select name="orden" id="orden" class="form-control form-control-sm">
                                @foreach (ChequeReporteFiltros::ORDENES as $clave => $etiqueta)
                                    <option value="{{ $clave }}" @selected(($f['orden'] ?? 'fechapago') === $clave)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-1" for="orden_dir">Dirección</label>
                            <select name="orden_dir" id="orden_dir" class="form-control form-control-sm">
                                <option value="asc" @selected(($f['orden_dir'] ?? 'asc') === 'asc')>Ascendente (viejo → nuevo)</option>
                                <option value="desc" @selected(($f['orden_dir'] ?? 'asc') === 'desc')>Descendente</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-1" id="etiqueta-fecha-doc" for="fecha_doc_desde">Fecha de emisión</label>
                            <input type="date" name="fecha_doc_desde" id="fecha_doc_desde" class="form-control form-control-sm" value="{{ $f['fecha_doc_desde'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-1" for="fecha_doc_hasta">Hasta</label>
                            <input type="date" name="fecha_doc_hasta" id="fecha_doc_hasta" class="form-control form-control-sm" value="{{ $f['fecha_doc_hasta'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-1" for="fecha_cheque_desde">Fecha de cheque desde</label>
                            <input type="date" name="fecha_cheque_desde" id="fecha_cheque_desde" class="form-control form-control-sm" value="{{ $f['fecha_cheque_desde'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-1" for="fecha_cheque_hasta">Fecha de cheque hasta</label>
                            <input type="date" name="fecha_cheque_hasta" id="fecha_cheque_hasta" class="form-control form-control-sm" value="{{ $f['fecha_cheque_hasta'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-3 mb-2" id="criterios-emitidos">
                            <label class="small mb-1" for="estado-emitido">Estado (propios)</label>
                            <select name="estado" id="estado-emitido" class="form-control form-control-sm">
                                @foreach (ChequeReporteFiltros::ESTADOS_EMITIDO as $clave => $etiqueta)
                                    <option value="{{ $clave }}" @selected((string) ($f['estado'] ?? '') === (string) $clave)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3 mb-2" id="criterios-recibidos" style="display:none;">
                            <label class="small mb-1" for="situacion-recibido">Situación (terceros)</label>
                            <select name="situacion" id="situacion-recibido" class="form-control form-control-sm">
                                @foreach (ChequeReporteFiltros::SITUACIONES_RECIBIDO as $clave => $etiqueta)
                                    <option value="{{ $clave }}" @selected((string) ($f['situacion'] ?? '') === (string) $clave)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-1 mb-2">
                            <button type="submit" class="btn btn-info btn-sm">Consultar</button>
                        </div>
                    </div>
                    <p class="small text-muted mb-0">
                        Rangos desde/hasta de ingreso o emisión y de fecha de cheque.
                        El Excel/PDF incluyen suma por día (como Anita) e importe numérico.
                        Clic en el ID o Int. abre el detalle del cheque.
                    </p>
                </div>
            </form>
            @if ($consultado)
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_reporte_cheque',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                @if (!empty($subtitulo))
                    <p class="small text-muted px-3 pt-2 mb-1">{{ $subtitulo }}</p>
                @endif
                @if (($totales ?? collect())->isNotEmpty())
                    <p class="small px-3 mb-2">
                        @foreach ($totales as $tot)
                            <span class="badge badge-light border mr-1">
                                {{ $tot->moneda !== '' ? $tot->moneda : 'Moneda' }}
                                {{ number_format((float) $tot->monto, 2, ',', '.') }}
                                ({{ (int) $tot->cantidad }})
                            </span>
                        @endforeach
                    </p>
                @endif
                @if (($totalesPorDia ?? collect())->isNotEmpty())
                    <div class="px-3 pb-2">
                        <p class="small font-weight-bold mb-1">Totales por día (fecha de cheque)</p>
                        <table class="table table-sm table-bordered mb-0" style="max-width: 420px;">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Día</th>
                                    <th class="text-right">Cant.</th>
                                    <th class="text-right">Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($totalesPorDia as $dia)
                                    <tr>
                                        <td>{{ $dia->fecha ? ChequeDepositoComprobanteSupport::fechaDmy($dia->fecha) : '(sin fecha)' }}</td>
                                        <td class="text-right">{{ (int) $dia->cantidad }}</td>
                                        <td class="text-right">{{ number_format((float) $dia->monto, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>ID</th>
                            <th>Int.</th>
                            <th>{{ $tipo === 'R' ? 'Fec.Ing.' : 'Fec.Emis.' }}</th>
                            <th>Fec.Che.</th>
                            <th class="text-right">Importe</th>
                            <th>N.Cli.</th>
                            <th>Cliente</th>
                            <th>Destino</th>
                            <th>Nro. cheque</th>
                            <th>{{ $tipo === 'E' ? 'Cuenta' : 'Banco' }}</th>
                            <th>Suc</th>
                            <th>Cta.libr.</th>
                            <th>{{ $tipo === 'R' ? 'N.rec.' : 'N.OP' }}</th>
                            @if ($multiEmpresa)
                            <th>Empresa</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        <tr>
                            <td>
                                <a href="{{ route('editar_cheque', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}" class="text-primary" target="_blank" rel="noopener" title="Detalle de cheque">{{ $data->id }}</a>
                            </td>
                            <td>
                                <a href="{{ route('editar_cheque', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}" class="text-primary" target="_blank" rel="noopener" title="Detalle de cheque">{{ $data->nro_interno_anita }}</a>
                            </td>
                            <td>{{ ChequeDepositoComprobanteSupport::fechaDmy($data->fechaemision) }}</td>
                            <td>{{ ChequeDepositoComprobanteSupport::fechaDmy($data->fechapago) }}</td>
                            <td class="text-right">{{ number_format((float) $data->monto, 2, ',', '.') }}</td>
                            <td>{{ ChequeReporteSupport::codigoCliente($data) }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit(ChequeReporteSupport::nombreCliente($data), 28) }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit(ChequeReporteSupport::destino($data), 28) }}</td>
                            <td>{{ $data->numerocheque }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit(ChequeReporteSupport::bancoOCuenta($data), 24) }}</td>
                            <td>{{ $data->sucursalpago }}</td>
                            <td class="small">{{ $data->cuentalibradora }}</td>
                            <td>{{ ChequeReporteSupport::nroDocumentoOrigen($data) }}</td>
                            @if ($multiEmpresa)
                            <td class="small">{{ $data->empresas->nombre ?? '' }}</td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $colspan }}" class="text-center text-muted py-3">No hay cheques para esos criterios.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>
@if ($consultado && $datas)
    {{ $datas->appends($filtrosQuery ?? [])->links() }}
@endif
@endsection
