@extends("theme.$theme.layout")
@section('titulo')
    Sync canal Local
@endsection
@section('contenido')
<div class="row">
    <div class="col-lg-10">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Sincronizar canal Local desde Anita stkmae</h3>
            </div>
            <div class="card-body">
                <p>Lee <code>stkmae</code> del bridge Local (<code>LOCAL_IP</code> / <code>IFX_SERVER_LOCAL</code>) y asigna canal <strong>LOCAL</strong> a artículos ERP existentes. No modifica otros datos del maestro.</p>
                <form method="post" action="{{ route('facturacion_local_sync_canal_ejecutar') }}">
                    @csrf
                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Local (bridge)</label>
                        <div class="col-lg-5">
                            <select name="local_id" class="form-control">
                                <option value="0">Default config</option>
                                @foreach ($locales as $loc)
                                    <option value="{{ $loc->id }}" @if (($local_id ?? 0) === (int) $loc->id) selected @endif>
                                        {{ $loc->codigo }} — {{ $loc->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Persistir</label>
                        <div class="col-lg-5">
                            <label>
                                <input type="checkbox" name="ejecutar" value="1" @if (! empty($ejecutar)) checked @endif>
                                Ejecutar (sin tilde = dry-run)
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Consultar / Sync</button>
                </form>

                @if (! empty($resultado))
                    <hr>
                    @if (! empty($resultado['error']))
                        <div class="alert alert-danger">{{ $resultado['error'] }}</div>
                    @else
                        <table class="table table-sm table-bordered">
                            <tr><th>Modo</th><td>{{ ($resultado['dry_run'] ?? true) ? 'DRY-RUN' : 'EJECUTADO' }}</td></tr>
                            <tr><th>SKUs Anita</th><td>{{ $resultado['skus_anita'] }}</td></tr>
                            <tr><th>Encontrados ERP</th><td>{{ $resultado['encontrados_erp'] }}</td></tr>
                            <tr><th>Ya asignados</th><td>{{ $resultado['ya_asignados'] }}</td></tr>
                            <tr><th>A asignar</th><td>{{ $resultado['a_asignar'] }}</td></tr>
                            <tr><th>Asignados ahora</th><td>{{ $resultado['asignados'] }}</td></tr>
                            <tr><th>Sin match</th><td>{{ $resultado['sin_match'] }}</td></tr>
                            <tr><th>Ya tenían Fábrica</th><td>{{ $resultado['ya_fabrica'] ?? 0 }}</td></tr>
                            <tr><th>A asignar Fábrica (no están en Anita Local)</th><td>{{ $resultado['a_asignar_fabrica'] ?? 0 }}</td></tr>
                            <tr><th>Asignados Fábrica ahora</th><td>{{ $resultado['asignados_fabrica'] ?? 0 }}</td></tr>
                            <tr><th>LOCAL en ERP y no en Anita Local</th><td>{{ $resultado['extras_local_n'] ?? 0 }}</td></tr>
                        </table>
                        @if (! empty($resultado['skus_a_asignar']))
                            <p class="small mb-1"><strong>LOCAL a marcar:</strong> {{ implode(', ', array_slice($resultado['skus_a_asignar'], 0, 30)) }}</p>
                        @endif
                        @if (! empty($resultado['extras_local']))
                            <p class="small text-warning mb-1"><strong>LOCAL de más:</strong> {{ implode(', ', $resultado['extras_local']) }}</p>
                        @endif
                        @if (($resultado['dry_run'] ?? true) && ($resultado['a_asignar'] ?? 0) > 0)
                            <div class="alert alert-warning">Revise el impacto y marque «Ejecutar» para persistir.</div>
                        @endif
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
