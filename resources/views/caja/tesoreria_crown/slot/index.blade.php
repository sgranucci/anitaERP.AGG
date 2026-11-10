@extends("theme.$theme.layout")

@section('titulo')
    Slots
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}?v={{ filemtime(public_path('assets/pages/scripts/includes/listado-filtros.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/listado.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/listado.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.mensaje')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tesoreria-crown-overlay',
    'tituloId' => 'tesoreria-crown-overlay-titulo',
    'subtituloId' => 'tesoreria-crown-overlay-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo.',
])
@php
    $textoImporte = fn (float $monto): string => number_format($monto, 2, ',', '.');
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Slots</h3>
                <div class="card-tools">
                    @include('includes.listado.filtros_toolbar', [
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => $tieneCriterios ?? false,
                        'limpiarUrl' => route('tesoreria_crown_slot', $sala ? ['sala_id' => $sala->id] : []),
                        'placeholder' => 'Máquina, nombre o número',
                        'toggleTarget' => '#panel-filtros-tesoreria-crown-slot',
                        'formId' => 'form-filtros-tesoreria-crown-slot',
                        'nuevoRegistroUrl' => (($jornadaAlta ?? null) && $jornadaAlta->estaAbierta())
                            ? route('nueva_tesoreria_crown_slot', ['jornada_id' => $jornadaAlta->id, 'origen' => 'menu'])
                            : null,
                        'nuevoRegistroCan' => 'crear-tesoreria-crown-slot',
                        'nuevoRegistroLabel' => 'Nuevo recuento',
                    ])
                    @php $jornadaEscritorio = $jornada ?? ($jornadaAlta ?? null); @endphp
                    @if ($jornadaEscritorio)
                        <a href="{{ route('ver_tesoreria_crown_jornada', $jornadaEscritorio->id) }}?panel=slots" class="btn btn-outline-info btn-sm ml-1">
                            <i class="fa fa-desktop"></i> Escritorio
                        </a>
                    @endif
                </div>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_slot',
                'exportRuta' => 'listar_tesoreria_crown_slot',
                'exportQueryparams' => $filtrosQuery ?? [],
            ])
            @include('caja.tesoreria_crown.partials.jornada_selector', [
                'ruta' => 'tesoreria_crown_slot',
                'jornada' => $jornada,
                'fechaPedida' => $fechaPedida ?? '',
                'fechaSugerida' => ($jornadaAlta ?? null)?->fecha?->format('Y-m-d'),
            ])
            <form id="form-filtros-tesoreria-crown-slot" method="get" action="{{ route('tesoreria_crown_slot') }}">
                <div id="panel-filtros-tesoreria-crown-slot" class="collapse">
                    <input type="hidden" name="sala_id" value="{{ $sala->id ?? '' }}">
                    <input type="hidden" name="jornada_id" value="{{ (int) ($filtros['jornada_id'] ?? 0) ?: '' }}">
                    <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                    <div class="card-body border-bottom py-2">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-2 mb-2">
                                <label for="fecha_desde" class="small text-muted mb-0">Desde</label>
                                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] ?? '' }}">
                            </div>
                            <div class="form-group col-md-2 mb-2">
                                <label for="fecha_hasta" class="small text-muted mb-0">Hasta</label>
                                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                            </div>
                            <div class="form-group col-md-2 mb-2">
                                <label for="tipo" class="small text-muted mb-0">Tipo</label>
                                <select name="tipo" id="tipo" class="form-control form-control-sm">
                                    <option value="">Todos</option>
                                    @foreach (\App\Models\Caja\TesoreriaCrownSlot::TIPOS as $codigo => $nombre)
                                        <option value="{{ $codigo }}" @selected(($filtros['tipo'] ?? '') === $codigo)>{{ $nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2 mb-2">
                                <label for="estado" class="small text-muted mb-0">Estado</label>
                                <select name="estado" id="estado" class="form-control form-control-sm">
                                    <option value="">Todos</option>
                                    @foreach (\App\Models\Caja\TesoreriaCrownSlot::ESTADOS as $codigo => $nombre)
                                        <option value="{{ $codigo }}" @selected(($filtros['estado'] ?? '') === $codigo)>{{ $nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2 mb-2">
                                <button type="submit" class="btn btn-sm btn-primary">Aplicar filtros</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <div class="card-body border-bottom py-2">
                <div class="d-flex flex-wrap align-items-center" style="gap:.5rem;">
                    <div class="border rounded px-3 py-2">
                        <div class="text-muted small">Drops</div>
                        <strong>{{ $resumenSlots['drops'] ?? 0 }}</strong>
                        <span class="text-muted">· {{ $textoImporte((float) ($resumenSlots['drop_total'] ?? 0)) }}</span>
                    </div>
                    <div class="border rounded px-3 py-2">
                        <div class="text-muted small">Recuentos</div>
                        <strong>{{ $resumenSlots['recuentos'] ?? 0 }}</strong>
                        <span class="text-muted">· {{ $textoImporte((float) ($resumenSlots['recuento_total'] ?? 0)) }}</span>
                    </div>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Fecha</th>
                            <th>Número</th>
                            <th>Tipo</th>
                            <th>Máquina</th>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th class="text-right">Total</th>
                            <th class="text-left" style="width:90px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $slot)
                            <tr>
                                <td>
                                    @if ($slot->jornada)
                                        <a class="text-primary" href="{{ route('ver_tesoreria_crown_jornada', $slot->jornada_id) }}?panel=slots" target="_blank" rel="noopener">
                                            {{ $slot->jornada->fecha?->format('d/m/Y') }}
                                        </a>
                                    @endif
                                </td>
                                <td>{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($slot->numero) }}</td>
                                <td>{{ \App\Models\Caja\TesoreriaCrownSlot::tipo((string) $slot->tipo) }}</td>
                                <td>{{ $slot->maquina }}</td>
                                <td>{{ $slot->maquina_nombre }}</td>
                                <td>{{ \App\Models\Caja\TesoreriaCrownSlot::estado((string) $slot->estado) }}</td>
                                <td class="text-right">{{ $textoImporte((float) $slot->total) }}</td>
                                <td class="text-left text-nowrap">
                                    <a href="{{ route('editar_tesoreria_crown_slot', ['id' => $slot->id, 'origen' => 'menu']) }}" class="btn-accion-tabla tooltipsC" title="{{ $slot->esBorrador() ? 'Editar' : 'Consultar' }}">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    @if ($slot->esBorrador() && $slot->jornada?->estaAbierta() && can('eliminar-borrador-tesoreria-crown-slot', false))
                                        <form method="POST" action="{{ route('eliminar_tesoreria_crown_slot', $slot->id) }}" class="d-inline" onsubmit="return confirm('¿Eliminar este borrador?');">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="origen" value="menu">
                                            <button type="submit" class="btn-accion-tabla tooltipsC" title="Eliminar borrador">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-3">
                                    @if (! $sala)
                                        Elegí una sala.
                                    @elseif ($tieneCriterios ?? false)
                                        No hay slots para este filtro.
                                    @else
                                        No hay slots cargados.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
                @if ($filas->total() > 0)
                    <span class="text-muted">{{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}</span>
                @endif
                {{ $filas->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
