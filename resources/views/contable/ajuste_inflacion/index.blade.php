@extends("theme.$theme.layout")

@section('titulo')
    Ajuste por inflación
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/ajuste_inflacion/index.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/ajuste_inflacion/index.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'ajuste-inflacion-overlay',
    'tituloId' => 'ajuste-inflacion-overlay-titulo',
    'subtituloId' => 'ajuste-inflacion-overlay-subtitulo',
    'titulo' => 'Procesando ajuste por inflación…',
    'subtitulo' => 'Puede demorar según el período y las cuentas configuradas. No cierre la página.',
])

<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Ajuste por inflación — RT 6</h3>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Flujo controlado: <strong>índices → configuración → simulación → papel de trabajo → confirmación</strong>.
                    Simular no genera asientos. Confirmar crea un asiento <strong>AJ</strong> balanceado y lo sincroniza con Anita.
                    Los reportes históricos pueden excluir estos asientos mediante el filtro de inflación.
                </p>

                <form method="get" action="{{ route('ajuste_inflacion') }}" class="form-horizontal mb-3">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresa_id ?: null,
                        'col_label' => 'col-md-2 control-label text-right pr-2',
                        'col_input' => 'col-md-4',
                    ])
                    <div class="form-group row">
                        <div class="offset-md-2 col-md-4">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </form>

                @if ($empresa_id <= 0)
                    <div class="alert alert-info">Seleccione una empresa para operar el ajuste.</div>
                @else
                    <div class="row">
                        <div class="col-xl-6">
                            <div class="card card-outline card-info h-100">
                                <div class="card-header">
                                    <h3 class="card-title">1. Índices mensuales</h3>
                                </div>
                                <div class="card-body">
                                    @if ($puede_importar_indices)
                                        <form method="post" action="{{ route('guardar_indice_ajuste_inflacion') }}" class="form-horizontal mb-3">
                                            @csrf
                                            <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                                            <div class="form-group row">
                                                <label class="col-md-3 control-label text-right pr-2 requerido">Período</label>
                                                <div class="col-md-3">
                                                    <input type="month" name="periodo" class="form-control form-control-sm" required>
                                                </div>
                                                <label class="col-md-2 control-label text-right pr-2 requerido">Índice</label>
                                                <div class="col-md-4">
                                                    <input type="number" name="valor" class="form-control form-control-sm"
                                                        step="0.00000001" min="0.00000001" required>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label class="col-md-3 control-label text-right pr-2">Fuente</label>
                                                <div class="col-md-5">
                                                    <input type="text" name="fuente" value="FACPCE RT 6"
                                                        class="form-control form-control-sm" maxlength="120">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="mb-0">
                                                        <input type="checkbox" name="provisorio" value="1"> Provisorio
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="fa fa-save"></i> Guardar índice
                                                </button>
                                            </div>
                                        </form>

                                        <form method="post" action="{{ route('importar_indices_ajuste_inflacion') }}"
                                            enctype="multipart/form-data" class="border-top pt-3 mb-3 form-proceso-ajuste"
                                            data-titulo="Importando índices…">
                                            @csrf
                                            <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                                            <div class="form-row align-items-end">
                                                <div class="col-md-8">
                                                    <label>CSV (columnas periodo, valor, fuente, provisorio)</label>
                                                    <input type="file" name="archivo_indices" class="form-control form-control-sm"
                                                        accept=".csv,text/csv" required>
                                                </div>
                                                <div class="col-md-4 text-right">
                                                    <button type="submit" class="btn btn-outline-primary btn-sm">
                                                        <i class="fa fa-upload"></i> Importar CSV
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    @endif

                                    <div class="table-responsive" style="max-height: 330px;">
                                        <table class="table table-sm table-bordered table-hover mb-0">
                                            <thead style="background:#85C1E9;color:#17202A;">
                                                <tr>
                                                    <th>Período</th>
                                                    <th class="text-right">Índice</th>
                                                    <th>Fuente</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($indices as $indice)
                                                    <tr>
                                                        <td>{{ $indice->periodo->format('m/Y') }}</td>
                                                        <td class="text-right">{{ number_format((float) $indice->valor, 8, ',', '.') }}</td>
                                                        <td>
                                                            {{ $indice->fuente }}
                                                            @if ($indice->provisorio)
                                                                <span class="badge badge-warning">Provisorio</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="3" class="text-center text-muted">No hay índices cargados.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-6 mt-3 mt-xl-0">
                            <div class="card card-outline card-info h-100">
                                <div class="card-header">
                                    <h3 class="card-title">2. Configuración contable</h3>
                                </div>
                                <div class="card-body">
                                    @if ($configuracion)
                                        <div class="alert alert-secondary py-2">
                                            <strong>RECPAM:</strong>
                                            {{ $configuracion->cuentaRecpam?->codigo }} —
                                            {{ $configuracion->cuentaRecpam?->nombre }}
                                            <br>
                                            <strong>Centro de costo:</strong>
                                            {{ $configuracion->centrocostoRecpam?->codigo ?? 'Sin centro' }}
                                            {{ $configuracion->centrocostoRecpam?->nombre ?? '' }}
                                            · <strong>Tipo:</strong> {{ $configuracion->tipoasiento?->abreviatura }}
                                        </div>
                                    @else
                                        <div class="alert alert-warning py-2">
                                            Falta inicializar la configuración de esta empresa.
                                        </div>
                                    @endif

                                    @if ($puede_configurar)
                                        <form method="post" action="{{ route('inicializar_ajuste_inflacion') }}"
                                            class="d-inline form-proceso-ajuste" data-titulo="Analizando último asiento AJ…">
                                            @csrf
                                            <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                                            <button type="submit" class="btn btn-outline-info btn-sm">
                                                <i class="fa fa-magic"></i> Inicializar desde último AJ
                                            </button>
                                        </form>

                                        <form method="post" action="{{ route('configurar_ajuste_inflacion') }}"
                                            class="form-inline mt-3">
                                            @csrf
                                            <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                                            <label class="mr-1">Cuenta RECPAM</label>
                                            <input type="text" name="cuenta_recpam_codigo" class="form-control form-control-sm mr-2"
                                                value="{{ $configuracion->cuentaRecpam?->codigo ?? '533030001' }}" required>
                                            <label class="mr-1">CC</label>
                                            <input type="text" name="centrocosto_recpam_codigo" class="form-control form-control-sm mr-2"
                                                value="{{ $configuracion->centrocostoRecpam?->codigo ?? '97' }}">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="fa fa-save"></i> Guardar
                                            </button>
                                        </form>

                                        <form method="post" action="{{ route('agregar_cuenta_ajuste_inflacion') }}"
                                            class="form-inline mt-3">
                                            @csrf
                                            <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                                            <label class="mr-1">Agregar cuenta imputable</label>
                                            <input type="text" name="cuenta_codigo" class="form-control form-control-sm mr-2"
                                                placeholder="Código" required>
                                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                                <i class="fa fa-plus"></i> Agregar
                                            </button>
                                        </form>
                                    @endif

                                    <div class="mt-3 mb-1">
                                        <strong>Cuentas configuradas:</strong> {{ $cuentas_configuradas->count() }}
                                    </div>
                                    <div class="table-responsive" style="max-height: 280px;">
                                        <table class="table table-sm table-bordered table-hover mb-0">
                                            <thead style="background:#85C1E9;color:#17202A;">
                                                <tr><th>Código</th><th>Cuenta</th><th class="text-center">Acción</th></tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($cuentas_configuradas as $fila)
                                                    <tr>
                                                        <td>{{ $fila->cuentacontable?->codigo }}</td>
                                                        <td>{{ $fila->cuentacontable?->nombre }}</td>
                                                        <td class="text-center">
                                                            @if ($puede_configurar)
                                                                <form method="post"
                                                                    action="{{ route('quitar_cuenta_ajuste_inflacion', ['id' => $fila->id]) }}"
                                                                    class="d-inline" onsubmit="return confirm('¿Excluir esta cuenta de futuras simulaciones?');">
                                                                    @csrf
                                                                    @method('delete')
                                                                    <button type="submit" class="btn-accion-tabla" title="Excluir">
                                                                        <i class="fa fa-times-circle text-danger"></i>
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="3" class="text-center text-muted">Sin cuentas configuradas.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-outline card-primary mt-4">
                        <div class="card-header"><h3 class="card-title">3. Simular ajuste</h3></div>
                        <div class="card-body">
                            @if ($puede_simular)
                                <form method="post" action="{{ route('simular_ajuste_inflacion') }}"
                                    class="form-horizontal form-proceso-ajuste" data-titulo="Calculando papel de trabajo…">
                                    @csrf
                                    <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                                    <div class="form-group row">
                                        <label class="col-md-2 control-label text-right pr-2 requerido">Desde</label>
                                        <div class="col-md-2">
                                            <input type="month" name="periodo_desde" class="form-control" required
                                                value="{{ old('periodo_desde', now()->startOfYear()->format('Y-m')) }}">
                                        </div>
                                        <label class="col-md-2 control-label text-right pr-2 requerido">Fecha de cierre</label>
                                        <div class="col-md-2">
                                            <input type="date" name="fecha_cierre" class="form-control" required
                                                value="{{ old('fecha_cierre', now()->endOfMonth()->format('Y-m-d')) }}">
                                        </div>
                                        <div class="col-md-4">
                                            <button type="submit" class="btn btn-warning">
                                                <i class="fa fa-calculator"></i> Simular sin generar asiento
                                            </button>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-2 control-label text-right pr-2">Observación</label>
                                        <div class="col-md-8">
                                            <input type="text" name="observacion" class="form-control" maxlength="2000">
                                        </div>
                                    </div>
                                </form>
                            @else
                                <div class="alert alert-info">No posee permiso para simular ajustes.</div>
                            @endif
                        </div>
                    </div>

                    <div class="card card-outline card-info mt-4">
                        <div class="card-header"><h3 class="card-title">4. Corridas</h3></div>
                        <div class="card-body table-responsive">
                            <table id="tabla-paginada" class="table table-sm table-bordered table-hover">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>#</th><th>Período</th><th>Cierre</th><th>Estado</th>
                                        <th class="text-right">Ajuste neto</th><th>Asiento</th><th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($corridas as $corrida)
                                        <tr>
                                            <td>{{ $corrida->id }}</td>
                                            <td>{{ $corrida->periodo_desde->format('m/Y') }}</td>
                                            <td>{{ $corrida->fecha_cierre->format('d/m/Y') }}</td>
                                            <td>
                                                <span class="badge {{ $corrida->estado === 'confirmada' ? 'badge-success' : ($corrida->estado === 'anulada' ? 'badge-secondary' : 'badge-warning') }}">
                                                    {{ ucfirst($corrida->estado) }}
                                                </span>
                                            </td>
                                            <td class="text-right">{{ number_format((float) $corrida->total_ajuste, 2, ',', '.') }}</td>
                                            <td>
                                                @if ($corrida->asiento)
                                                    <a class="text-primary" target="_blank" rel="noopener"
                                                        href="{{ route('editar_asiento', ['id' => $corrida->asiento->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                                                        {{ $corrida->asiento->numeroasiento }}
                                                    </a>
                                                @endif
                                            </td>
                                            <td class="text-nowrap">
                                                <a class="btn btn-info btn-sm"
                                                    href="{{ route('ajuste_inflacion', ['empresa_id' => $empresa_id, 'corrida_id' => $corrida->id]) }}">
                                                    <i class="fa fa-eye"></i> Papel
                                                </a>
                                                <a class="btn btn-outline-success btn-sm"
                                                    href="{{ route('exportar_csv_ajuste_inflacion', ['id' => $corrida->id]) }}">
                                                    <i class="fa fa-file-excel-o"></i> CSV
                                                </a>
                                                <a class="btn btn-outline-danger btn-sm"
                                                    href="{{ route('exportar_pdf_ajuste_inflacion', ['id' => $corrida->id]) }}">
                                                    <i class="fa fa-file-pdf-o"></i> PDF
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-center text-muted">No hay corridas.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            {{ $corridas->links() }}
                        </div>
                    </div>

                    @if ($corrida_seleccionada && $detalles)
                        <div class="card card-outline card-info mt-4" id="papel-trabajo">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="card-title">
                                    Papel de trabajo — corrida #{{ $corrida_seleccionada->id }}
                                </h3>
                                <div class="card-tools">
                                    @if ($corrida_seleccionada->estado === 'simulada' && $puede_confirmar)
                                        <form method="post"
                                            action="{{ route('confirmar_ajuste_inflacion', ['id' => $corrida_seleccionada->id]) }}"
                                            class="d-inline form-proceso-ajuste" data-titulo="Confirmando y sincronizando asiento AJ…"
                                            onsubmit="return confirm('¿Confirma el ajuste? Se generará el asiento AJ y no podrá editarse esta corrida.');">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fa fa-check"></i> Confirmar y generar AJ
                                            </button>
                                        </form>
                                    @endif
                                    @if ($corrida_seleccionada->estado === 'simulada' && $puede_simular)
                                        <form method="post"
                                            action="{{ route('anular_ajuste_inflacion', ['id' => $corrida_seleccionada->id]) }}"
                                            class="d-inline" onsubmit="return confirm('¿Anular esta simulación?');">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="fa fa-times"></i> Anular simulación
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-secondary py-2">
                                    Índice de cierre:
                                    <strong>{{ number_format((float) $corrida_seleccionada->indiceCierre?->valor, 8, ',', '.') }}</strong>
                                    · Ajuste neto:
                                    <strong>{{ number_format((float) $corrida_seleccionada->total_ajuste, 2, ',', '.') }}</strong>
                                    · Firma: <code>{{ substr($corrida_seleccionada->firma, 0, 16) }}…</code>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-hover">
                                        <thead style="background:#85C1E9;color:#17202A;">
                                            <tr>
                                                <th>Origen</th><th>Cuenta</th><th>Centro costo</th>
                                                <th class="text-right">Saldo</th><th class="text-right">Índice</th>
                                                <th class="text-right">Coef.</th><th class="text-right">Reexpresado</th>
                                                <th class="text-right">Ajuste</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($detalles as $detalle)
                                                <tr>
                                                    <td>{{ $detalle->periodo_origen->format('m/Y') }}</td>
                                                    <td>
                                                        {{ $detalle->cuentacontable?->codigo }} —
                                                        {{ $detalle->cuentacontable?->nombre }}
                                                    </td>
                                                    <td>{{ $detalle->centrocosto?->codigo ?? '' }}</td>
                                                    <td class="text-right">{{ number_format((float) $detalle->saldo_origen, 2, ',', '.') }}</td>
                                                    <td class="text-right">{{ number_format((float) $detalle->indiceOrigen?->valor, 8, ',', '.') }}</td>
                                                    <td class="text-right">{{ number_format((float) $detalle->coeficiente, 10, ',', '.') }}</td>
                                                    <td class="text-right">{{ number_format((float) $detalle->importe_reexpresado, 2, ',', '.') }}</td>
                                                    <td class="text-right">{{ number_format((float) $detalle->ajuste, 2, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    {{ $detalles->links() }}
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
