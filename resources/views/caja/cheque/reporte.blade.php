@extends("theme.$theme.layout")
@section('titulo')
    Reporte de cheques
@endsection

@section('scripts')
<script>
(function () {
    var overlay = document.getElementById('cheque-reporte-overlay');
    function mostrar(titulo, subtitulo) {
        if (!overlay) return;
        if (titulo) document.getElementById('cheque-reporte-titulo').textContent = titulo;
        if (subtitulo) document.getElementById('cheque-reporte-subtitulo').textContent = subtitulo;
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    function ocultar() {
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }
    var form = document.getElementById('form-reporte-cheque');
    if (form) {
        form.addEventListener('submit', function () {
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;
            mostrar('Consultando cheques…', 'Puede demorar según la cantidad de cheques. No cierre la página.');
        });
    }
    document.querySelectorAll('a[href*="listareportecheque"]').forEach(function (a) {
        a.addEventListener('click', function () {
            mostrar('Exportando…', 'Pulse Esc para seguir en la pantalla si la descarga no navega.');
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') ocultar();
    });
    window.addEventListener('pageshow', ocultar);

    function syncTipo() {
        var marcado = document.querySelector('input[name="tipo"]:checked');
        var tipo = marcado ? marcado.value : 'E';
        var emitidos = document.getElementById('criterios-emitidos');
        var recibidos = document.getElementById('criterios-recibidos');
        var etiqueta = document.getElementById('etiqueta-fecha-doc');
        if (emitidos) emitidos.style.display = tipo === 'E' ? '' : 'none';
        if (recibidos) recibidos.style.display = tipo === 'R' ? '' : 'none';
        if (etiqueta) etiqueta.textContent = tipo === 'R' ? 'Fecha de ingreso' : 'Fecha de emisión';
    }
    document.querySelectorAll('input[name="tipo"]').forEach(function (radio) {
        radio.addEventListener('change', syncTipo);
    });
    syncTipo();
})();
</script>
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
    $f = $filtros ?? [];
    $tipo = ($f['tipo'] ?? 'E') === 'R' ? 'R' : 'E';
    $consultado = ! empty($consultado);
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
                            <div class="btn-group btn-group-sm btn-group-toggle" data-toggle="buttons">
                                <label class="btn {{ $tipo === 'E' ? 'btn-primary active' : 'btn-outline-primary' }}">
                                    <input type="radio" name="tipo" value="E" {{ $tipo === 'E' ? 'checked' : '' }}> Emitidos
                                </label>
                                <label class="btn {{ $tipo === 'R' ? 'btn-info active' : 'btn-outline-info' }}">
                                    <input type="radio" name="tipo" value="R" {{ $tipo === 'R' ? 'checked' : '' }}> Recibidos
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
                                <option value="asc" @selected(($f['orden_dir'] ?? 'asc') === 'asc')>Ascendente</option>
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
                        Emitidos filtra por estado del cheque propio (diferido, debitado, anulado).
                        Recibidos filtra por situación de cartera, depósito, acreditación, rechazo o caución.
                        El orden se aplica en pantalla y en PDF / Excel.
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
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>ID</th>
                            <th>Número</th>
                            <th>Int.</th>
                            <th>Estado</th>
                            <th>{{ $tipo === 'R' ? 'Ingreso' : 'Emisión' }}</th>
                            <th>Fecha de cheque</th>
                            <th>{{ $tipo === 'E' ? 'Cuenta' : 'Banco' }}</th>
                            @if (($empresa_query ?? collect())->count() > 1)
                            <th>Empresa</th>
                            @endif
                            <th class="text-right">Monto</th>
                            <th>Mon</th>
                            <th>Beneficiario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        @php
                            $estadoLabel = collect($estado_enum ?? [])->firstWhere('valor', $data->estado);
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('editar_cheque', ['id' => $data->id]) }}" class="text-primary" target="_blank" rel="noopener">{{ $data->id }}</a>
                            </td>
                            <td>{{ $data->numerocheque }}</td>
                            <td>{{ $data->nro_interno_anita }}</td>
                            <td>{{ $estadoLabel['nombre'] ?? $data->estado }}</td>
                            <td>{{ ChequeDepositoComprobanteSupport::fechaDmy($data->fechaemision) }}</td>
                            <td>{{ ChequeDepositoComprobanteSupport::fechaDmy($data->fechapago) }}</td>
                            <td>
                                @if ($tipo === 'E')
                                    {{ $data->cuentacajas->nombre ?? '' }}
                                @else
                                    {{ $data->bancos->nombre ?? '' }}
                                @endif
                            </td>
                            @if (($empresa_query ?? collect())->count() > 1)
                            <td>{{ $data->empresas->nombre ?? '' }}</td>
                            @endif
                            <td class="text-right">{{ number_format((float) $data->monto, 2, ',', '.') }}</td>
                            <td class="text-center small">{{ $data->monedas->abreviatura ?? '' }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($data->entregado ?? $data->anombrede, 28) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-3">No hay cheques para esos criterios.</td>
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
