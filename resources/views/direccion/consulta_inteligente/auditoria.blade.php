@extends("theme.$theme.layout")

@section('titulo')
    Auditoría · Consulta Inteligente
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/consulta-inteligente.css') }}?v={{ filemtime(public_path('assets/css/consulta-inteligente.css')) }}">
@endsection

@section('contenido')
<div class="container-fluid ci-wrap">
    <div class="ci-hero ci-explorar-hero">
        <div>
            <div class="ci-hero-kicker">Consulta Inteligente</div>
            <h1>Auditoría de consultas</h1>
            <p>Quién consultó qué (catálogo, explorar, export, NL, cruce, join, snapshot).</p>
        </div>
        <div class="ci-toolbar">
            <a href="{{ route('consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-reply-all"></i> Catálogo
            </a>
        </div>
    </div>

    @include('includes.mensaje')

    @if (! $tablaLista)
        <div class="alert alert-warning">
            Falta la tabla <code>consulta_inteligente_auditoria</code>.
            Corré la migración <code>2026_11_15_194500_consulta_inteligente_nl_snapshot_auditoria</code>.
        </div>
    @else
        @if (! empty($puedeGlobal))
            <form method="get" action="{{ route('auditoria_consulta_inteligente') }}" class="mb-3">
                <div class="custom-control custom-switch">
                    <input type="hidden" name="solo_mias" value="0">
                    <input type="checkbox" class="custom-control-input" id="ci-aud-mias" name="solo_mias" value="1"
                           @checked($soloMias) onchange="this.form.submit()">
                    <label class="custom-control-label" for="ci-aud-mias">Solo mis consultas</label>
                </div>
            </form>
        @else
            <p class="small text-muted mb-3">Mostrando solo tus consultas. El historial global requiere permiso de auditoría.</p>
        @endif

        <div class="card card-info shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Cuándo</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Entidad</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $row)
                            <tr>
                                <td class="text-nowrap">{{ optional($row->created_at)->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $row->usuario->nombre ?? $row->usuario->usuario ?? ('#'.$row->usuario_id) }}</td>
                                <td><code>{{ $row->accion }}</code></td>
                                <td>{{ $row->entidad ?? '—' }}</td>
                                <td>{{ $row->detalle ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-muted text-center py-3">Sin registros todavía.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
