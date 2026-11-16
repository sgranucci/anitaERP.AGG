@extends("theme.$theme.layout")
@section('titulo')
Dry-run schedule — {{ $tipo->nombre }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-calendar"></i> Dry-run de schedule (30 días)</h3>
                <div class="card-tools">
                    <a href="{{ route('impacto_modulo_aviso_programado', $tipo->id) }}" class="btn btn-outline-light btn-sm">
                        Impacto
                    </a>
                    <a href="{{ route('editar_modulo_aviso', $tipo->id) }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p>
                    <strong>{{ $tipo->nombre }}</strong>
                    <code class="ml-2">{{ $tipo->modulo }}.{{ $tipo->codigo }}</code>
                </p>
                <p class="text-muted small">Programación: {{ $etiqueta }}</p>
                <p class="mb-2">Días en los que dispararía (sin escribir bitácora ni mails):</p>
                <div class="table-responsive" style="max-width:28rem;">
                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Día</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($fechas as $i => $f)
                                @php
                                    $c = \Illuminate\Support\Carbon::parse($f);
                                    $nomDia = \App\Support\Configuracion\ModuloAvisoProgramacionSupport::DIAS[(int) $c->dayOfWeekIso] ?? '';
                                @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $c->format('d/m/Y') }}</td>
                                    <td>{{ $nomDia }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-muted">Ninguna corrida en los próximos 30 días.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
