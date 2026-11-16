@extends("theme.$theme.layout")

@section('titulo')
    Comparar snapshots · Consulta Inteligente
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/consulta-inteligente.css') }}?v={{ filemtime(public_path('assets/css/consulta-inteligente.css')) }}">
@endsection

@section('contenido')
<div class="container-fluid ci-wrap">
    <div class="ci-hero ci-explorar-hero">
        <div>
            <div class="ci-hero-kicker">Consulta Inteligente</div>
            <h1>Comparar snapshots</h1>
            <p>Diferencias entre dos resultados congelados (filas solo en A, solo en B y campos cambiados).</p>
        </div>
        <div class="ci-toolbar">
            <a href="{{ route('snapshots_consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-reply-all"></i> Snapshots
            </a>
        </div>
    </div>

    @include('includes.mensaje')

    @if (! $tablaLista)
        <div class="alert alert-warning">Falta la tabla de snapshots.</div>
    @elseif (count($snapshots) < 2)
        <div class="alert alert-light border">Necesitás al menos dos snapshots para comparar.</div>
    @else
        <form method="get" action="{{ route('comparar_snapshots_consulta_inteligente') }}" class="card card-info shadow-sm mb-3">
            <div class="card-body">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-5 mb-0">
                        <label class="small text-muted" for="ci-snap-a">Snapshot A</label>
                        <select name="a" id="ci-snap-a" class="form-control" required>
                            <option value="">Elegí…</option>
                            @foreach ($snapshots as $s)
                                <option value="{{ $s->id }}" @selected((int) $idA === (int) $s->id)>
                                    #{{ $s->id }} · {{ $s->titulo }} ({{ $s->entidad }}, {{ $s->filas_count }} filas)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-5 mb-0">
                        <label class="small text-muted" for="ci-snap-b">Snapshot B</label>
                        <select name="b" id="ci-snap-b" class="form-control" required>
                            <option value="">Elegí…</option>
                            @foreach ($snapshots as $s)
                                <option value="{{ $s->id }}" @selected((int) $idB === (int) $s->id)>
                                    #{{ $s->id }} · {{ $s->titulo }} ({{ $s->entidad }}, {{ $s->filas_count }} filas)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-2 mb-0">
                        <button type="submit" class="btn btn-primary btn-block">Comparar</button>
                    </div>
                </div>
            </div>
        </form>

        @if (is_array($resultado))
            @if (! ($resultado['ok'] ?? false))
                <div class="alert alert-warning">{{ $resultado['mensaje'] ?? 'No se pudo comparar.' }}</div>
            @else
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="ci-card" style="min-height:auto;border-left-color:#117A65;">
                            <div class="small text-muted">Iguales</div>
                            <div style="font-size:1.6rem;font-weight:700;">{{ $resultado['iguales'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="ci-card" style="min-height:auto;border-left-color:#B03A2E;">
                            <div class="small text-muted">Solo en A</div>
                            <div style="font-size:1.6rem;font-weight:700;">{{ count($resultado['solo_a']) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="ci-card" style="min-height:auto;border-left-color:#2471A3;">
                            <div class="small text-muted">Solo en B</div>
                            <div style="font-size:1.6rem;font-weight:700;">{{ count($resultado['solo_b']) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="ci-card" style="min-height:auto;border-left-color:#B9770E;">
                            <div class="small text-muted">Cambiados</div>
                            <div style="font-size:1.6rem;font-weight:700;">{{ count($resultado['cambiados']) }}</div>
                        </div>
                    </div>
                </div>

                @foreach ([
                    ['titulo' => 'Solo en A (#'.$resultado['a']->id.')', 'filas' => $resultado['solo_a']],
                    ['titulo' => 'Solo en B (#'.$resultado['b']->id.')', 'filas' => $resultado['solo_b']],
                ] as $bloque)
                    @if (($bloque['filas'] ?? []) !== [])
                        <div class="card card-outline card-info mb-3">
                            <div class="card-header"><strong>{{ $bloque['titulo'] }}</strong></div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead style="background:#85C1E9;color:#17202A;">
                                        <tr>
                                            @foreach ($resultado['labels'] as $lab)
                                                <th>{{ $lab }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (array_slice($bloque['filas'], 0, 50) as $fila)
                                            <tr>
                                                @foreach ($resultado['columnas'] as $c)
                                                    <td>{{ $fila[$c] ?? '' }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endforeach

                @if (($resultado['cambiados'] ?? []) !== [])
                    <div class="card card-outline card-warning mb-3">
                        <div class="card-header"><strong>Filas cambiadas (máx. 50)</strong></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Clave</th>
                                        <th>Campos</th>
                                        <th>A</th>
                                        <th>B</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (array_slice($resultado['cambiados'], 0, 50) as $chg)
                                        <tr>
                                            <td><code>{{ $chg['clave'] }}</code></td>
                                            <td>{{ implode(', ', $chg['campos']) }}</td>
                                            <td class="small">
                                                @foreach ($chg['campos'] as $c)
                                                    <div><strong>{{ $c }}:</strong> {{ $chg['a'][$c] ?? '' }}</div>
                                                @endforeach
                                            </td>
                                            <td class="small">
                                                @foreach ($chg['campos'] as $c)
                                                    <div><strong>{{ $c }}:</strong> {{ $chg['b'][$c] ?? '' }}</div>
                                                @endforeach
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endif
        @endif
    @endif
</div>
@endsection
