@extends("theme.$theme.layout")
@section('titulo')
    Conciliación mensual
@endsection

@section('contenido')
@php
    $qs = array_filter(['empresa_id' => $empresa_id ?: null]);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Conciliación mensual</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.compras.boton-manual-suscripciones')
                    <a href="{{ route('consultar_suscripcion') }}" class="btn btn-outline-light btn-sm ml-1">← Suscripciones</a>
                </div>
            </div>

            @include('compras.suscripcion.partials.filtros_externos', [
                'rutaNombre' => 'conciliacion_suscripcion',
                'empresa_query' => $empresa_query,
                'empresa_id' => $empresa_id,
                'filtrosQuery' => $qs,
            ])

            <div class="card-body border-bottom">
                <p class="text-muted small">
                    Se importa el resumen del emisor y cada cargo se cruza contra las suscripciones vigentes.
                    Dentro de tolerancia queda <strong>conciliado</strong>; por encima vuelve como
                    <strong>desvío</strong>; sin match queda <strong>sin identificar</strong>.
                </p>

                <form method="post" action="{{ route('abrir_conciliacion_suscripcion') }}" class="form-inline">
                    @csrf
                    <label class="mr-2">Empresa</label>
                    <select name="empresa_id" class="form-control form-control-sm mr-3" required>
                        @foreach ($empresa_query as $emp)
                            <option value="{{ $emp->id }}" @selected($empresa_id === (int)$emp->id || ($empresa_id === 0 && $loop->first))>
                                {{ $emp->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <label class="mr-2">Período</label>
                    <input type="month" name="periodo" class="form-control form-control-sm mr-3" required value="{{ $periodo_sugerido }}">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> Abrir período</button>
                </form>
            </div>

            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-periodos">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Período</th>
                            <th>Empresa</th>
                            <th class="text-center">Cargos</th>
                            <th>Archivo</th>
                            <th>Importado</th>
                            <th>Estado</th>
                            <th class="width80"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($periodos as $p)
                            <tr>
                                <td><strong>{{ $p->etiquetaPeriodo() }}</strong></td>
                                <td>{{ optional($p->empresas)->nombre }}</td>
                                <td class="text-center">{{ $p->suscripcion_cargos_count }}</td>
                                <td class="small">{{ $p->archivo_nombre ?: '—' }}</td>
                                <td class="small">{{ $p->importado_at ? $p->importado_at->format('d/m/Y H:i') : '—' }}</td>
                                <td>
                                    <span class="badge badge-{{ $p->abierta() ? 'warning' : 'success' }}">
                                        {{ $p->abierta() ? 'Abierta' : 'Cerrada' }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('ver_conciliacion_suscripcion', $p->id) }}" class="btn btn-xs btn-outline-info">
                                        <i class="fa fa-eye"></i> Abrir
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Todavía no se abrió ningún período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
