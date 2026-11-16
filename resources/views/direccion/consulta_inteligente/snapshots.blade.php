@extends("theme.$theme.layout")

@section('titulo')
    Snapshots · Consulta Inteligente
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/consulta-inteligente.css') }}?v={{ filemtime(public_path('assets/css/consulta-inteligente.css')) }}">
@endsection

@section('contenido')
<div class="container-fluid ci-wrap">
    <div class="ci-hero ci-explorar-hero">
        <div>
            <div class="ci-hero-kicker">Consulta Inteligente</div>
            <h1>Snapshots de consultas</h1>
            <p>
                Resultado congelado de una consulta pesada (hasta {{ $maxFilas }} filas).
                Caduca a las {{ $ttlHoras }} horas. Se guarda desde el explorador.
            </p>
        </div>
        <div class="ci-toolbar">
            <a href="{{ route('consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-reply-all"></i> Catálogo
            </a>
            <a href="{{ route('comparar_snapshots_consulta_inteligente') }}" class="btn btn-light btn-sm">
                <i class="fa fa-exchange-alt"></i> Comparar
            </a>
        </div>
    </div>

    @include('includes.mensaje')

    @if (! $tablaLista)
        <div class="alert alert-warning">
            Falta la tabla <code>consulta_inteligente_snapshot</code>.
            Corré la migración <code>2026_11_15_194500_consulta_inteligente_nl_snapshot_auditoria</code>.
        </div>
    @else
        <div class="card card-info shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Entidad</th>
                            <th>Filas</th>
                            <th>Creado</th>
                            <th>Vence</th>
                            <th style="width:7rem;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($snapshots as $snap)
                            <tr>
                                <td>{{ $snap->id }}</td>
                                <td>{{ $snap->titulo }}</td>
                                <td><code>{{ $snap->entidad }}</code></td>
                                <td>{{ $snap->filas_count }}</td>
                                <td>{{ optional($snap->created_at)->format('d/m/Y H:i') }}</td>
                                <td>{{ optional($snap->expires_at)->format('d/m/Y H:i') }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('ver_snapshot_consulta_inteligente', $snap->id) }}"
                                       class="btn btn-accion-tabla" title="Ver">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <form method="post" action="{{ route('eliminar_snapshot_consulta_inteligente', $snap->id) }}"
                                          class="d-inline" onsubmit="return confirm('¿Eliminar este snapshot?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-accion-tabla" title="Eliminar">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted text-center py-3">
                                    No hay snapshots. En el explorador, consultá y usá «Guardar snapshot».
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
