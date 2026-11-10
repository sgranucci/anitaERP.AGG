@php
    use App\Support\Caja\TesoreriaCrownAvisoSupport as Aviso;
    use App\Support\Caja\TesoreriaCrownAvisoListadoFiltros as Filtros;

    $badgeSeveridad = ['A' => 'badge-danger', 'M' => 'badge-warning', 'B' => 'badge-secondary'];
    $badgeEstado = ['A' => 'badge-danger', 'S' => 'badge-info', 'R' => 'badge-success', 'D' => 'badge-secondary'];
    $totalFiltro = (int) $resumen->sum('n');
@endphp
@extends("theme.$theme.layout")

@section('titulo')
    Avisos de auditoría — Tesorería
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/listado.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/listado.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.mensaje')
@include('includes.form-error')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tesoreria-crown-overlay',
    'tituloId' => 'tesoreria-crown-overlay-titulo',
    'subtituloId' => 'tesoreria-crown-overlay-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo.',
])
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-bell"></i> Avisos de auditoría</h3>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_aviso',
                'queryExtra' => Filtros::paraQueryString($filtros),
                'exportRuta' => 'listar_tesoreria_crown_aviso',
                'exportQueryparams' => $filtrosQuery,
            ])
            <div class="card-body pb-2">
                <form method="get" action="{{ route('tesoreria_crown_aviso') }}" class="form-row align-items-end">
                    <input type="hidden" name="sala_id" value="{{ $sala->id ?? '' }}">
                    <div class="col-auto mb-2">
                        <label class="small mb-0" for="aviso-estado">Estado</label>
                        <select name="estado" id="aviso-estado" class="form-control form-control-sm">
                            @foreach (Aviso::ESTADOS as $codigo => $nombre)
                                <option value="{{ $codigo }}" @selected($filtros['estado'] === $codigo)>{{ $nombre }}</option>
                            @endforeach
                            <option value="{{ Filtros::ESTADO_TODOS }}" @selected($filtros['estado'] === Filtros::ESTADO_TODOS)>Todos</option>
                        </select>
                    </div>
                    <div class="col-auto mb-2">
                        <label class="small mb-0" for="aviso-severidad">Severidad</label>
                        <select name="severidad" id="aviso-severidad" class="form-control form-control-sm">
                            <option value="">Todas</option>
                            @foreach (Aviso::SEVERIDADES as $codigo => $nombre)
                                <option value="{{ $codigo }}" @selected($filtros['severidad'] === $codigo)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto mb-2">
                        <label class="small mb-0" for="aviso-regla">Regla</label>
                        <select name="regla" id="aviso-regla" class="form-control form-control-sm">
                            <option value="">Todas</option>
                            @foreach (Aviso::REGLAS as $codigo => $definicion)
                                <option value="{{ $codigo }}" @selected($filtros['regla'] === $codigo)>{{ $definicion['etiqueta'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto mb-2">
                        <label class="small mb-0" for="aviso-desde">Desde</label>
                        <input type="date" name="desde" id="aviso-desde" value="{{ $filtros['desde'] }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-auto mb-2">
                        <label class="small mb-0" for="aviso-hasta">Hasta</label>
                        <input type="date" name="hasta" id="aviso-hasta" value="{{ $filtros['hasta'] }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-auto mb-2">
                        <label class="small mb-0" for="aviso-texto">Texto</label>
                        <input type="text" name="texto" id="aviso-texto" value="{{ $filtros['texto'] }}" maxlength="100" class="form-control form-control-sm" placeholder="Cajero, caja, comentario…">
                    </div>
                    <div class="col-auto mb-2">
                        <input type="hidden" name="consultar" value="1">
                        <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-search"></i> Consultar</button>
                        @if (Filtros::tieneCriteriosAplicados($filtros))
                            <a href="{{ route('tesoreria_crown_aviso', ['sala_id' => $sala->id ?? null]) }}" class="btn btn-outline-secondary btn-sm">Limpiar filtros</a>
                        @endif
                    </div>
                </form>

                <div class="small mb-2">
                    <strong>{{ number_format($totalFiltro, 0, ',', '.') }}</strong> aviso(s) con este filtro:
                    @foreach (Aviso::SEVERIDADES as $codigo => $nombre)
                        @php $fila = $resumen->get($codigo); @endphp
                        <span class="badge {{ $badgeSeveridad[$codigo] }} ml-1">{{ $nombre }}: {{ (int) ($fila->n ?? 0) }}</span>
                        @if ($fila && (float) $fila->importe > 0)
                            <span class="text-muted">($ {{ number_format((float) $fila->importe, 2, ',', '.') }})</span>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="card-body pt-0">
                @if ($avisos->isEmpty())
                    <p class="text-muted mb-0">No hay avisos con este filtro.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Sev.</th>
                                    <th>Regla</th>
                                    <th>Aviso</th>
                                    <th>Detalle</th>
                                    <th class="text-right">Importe</th>
                                    <th>Estado</th>
                                    <th>Resolución</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($avisos as $aviso)
                                    @php $link = Aviso::link($aviso); @endphp
                                    <tr>
                                        <td class="text-nowrap">{{ $aviso->fecha?->format('d/m/Y') }}</td>
                                        <td><span class="badge {{ $badgeSeveridad[$aviso->severidad] ?? 'badge-light' }}">{{ Aviso::severidad($aviso->severidad) }}</span></td>
                                        <td class="small">{{ Aviso::regla($aviso->regla) }}</td>
                                        <td>
                                            @if ($link)
                                                <a href="{{ $link }}" class="text-primary" target="_blank" rel="noopener">{{ $aviso->titulo }}</a>
                                            @else
                                                {{ $aviso->titulo }}
                                            @endif
                                        </td>
                                        <td class="small" style="white-space:pre-line;">{{ $aviso->detalle }}</td>
                                        <td class="text-right text-nowrap">
                                            {{ $aviso->importe !== null ? number_format((float) $aviso->importe, 2, ',', '.') : '' }}
                                        </td>
                                        <td><span class="badge {{ $badgeEstado[$aviso->estado] ?? 'badge-light' }}">{{ Aviso::estado($aviso->estado) }}</span></td>
                                        <td class="small" style="min-width:220px;">
                                            @if ($aviso->resueltoUsuario)
                                                <div>{{ $aviso->resueltoUsuario->nombre }} · {{ $aviso->resuelto_at?->format('d/m/Y H:i') }}</div>
                                                <div class="text-muted" style="white-space:pre-line;">{{ $aviso->comentario }}</div>
                                            @elseif ($puedeResolver && in_array($aviso->estado, [Aviso::ABIERTO, Aviso::SUPERADO], true))
                                                <details>
                                                    <summary class="text-primary" style="cursor:pointer;">Resolver</summary>
                                                    <form method="post" action="{{ route('resolver_tesoreria_crown_aviso', $aviso->id) }}" class="mt-1">
                                                        @csrf
                                                        <textarea name="comentario" rows="2" maxlength="1000" required class="form-control form-control-sm mb-1" placeholder="Qué se revisó o por qué se descarta"></textarea>
                                                        <button type="submit" name="accion" value="revisar" class="btn btn-success btn-sm">Revisado</button>
                                                        <button type="submit" name="accion" value="descartar" class="btn btn-outline-secondary btn-sm">Descartar</button>
                                                    </form>
                                                </details>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="small text-muted">{{ $avisos->firstItem() }}–{{ $avisos->lastItem() }} de {{ $avisos->total() }}</span>
                        {{ $avisos->appends($filtrosQuery)->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
