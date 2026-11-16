@extends("theme.$theme.layout")
@section('titulo')
Impacto — {{ $tipo->nombre }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-search"></i> Analizador de impacto</h3>
                <div class="card-tools">
                    <a href="{{ route('editar_modulo_aviso', $tipo->id) }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a configurar
                    </a>
                    <a href="{{ route('dry_run_modulo_aviso_programado', $tipo->id) }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-calendar"></i> Dry-run 30 días
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <strong>{{ $impacto['nombre'] }}</strong>
                    <code class="ml-2">{{ $impacto['clave'] }}</code>
                </p>
                <p class="small text-muted">
                    Acción tipada: <strong>{{ $impacto['accion'] }}</strong>
                    @if ($impacto['comando'])
                        · comando <code>{{ $impacto['comando'] }}</code>
                    @endif
                    · canales: {{ implode(', ', $impacto['canales']) }}
                    @if ($impacto['proxima'])
                        · próxima: {{ $impacto['proxima'] }}
                    @endif
                </p>

                @if (! empty($impacto['advertencias']))
                    <div class="alert alert-warning">
                        <ul class="mb-0 pl-3">
                            @foreach ($impacto['advertencias'] as $adv)
                                <li>{{ $adv }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($impacto['dependencia'])
                    <p>
                        Dependencia:
                        <code>{{ $impacto['dependencia'] }}</code>
                        @if ($impacto['dependencia_ok'] === true)
                            <span class="badge badge-success">OK hoy</span>
                        @elseif ($impacto['dependencia_ok'] === false)
                            <span class="badge badge-danger">Sin OK hoy</span>
                        @endif
                    </p>
                @endif

                <h6>Destinatarios del catálogo ({{ $impacto['destinatarios_count'] }})</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Email</th>
                                <th>Usuario</th>
                                <th>Empresa</th>
                                <th>CC</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($impacto['destinatarios'] as $d)
                                <tr>
                                    <td>{{ $d['email'] ?? '—' }}</td>
                                    <td>{{ $d['usuario'] ?? '—' }}</td>
                                    <td>{{ $d['empresa_id'] ?? '—' }}</td>
                                    <td>{{ $d['centrocosto_id'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-muted">Sin destinatarios activos en catálogo.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if (! empty($impacto['condiciones']))
                    <h6>Condiciones</h6>
                    <pre class="small bg-light p-2">{{ json_encode($impacto['condiciones'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                @endif

                <h6>Simulación próximos 14 días</h6>
                <p class="small">
                    @forelse ($impacto['simular_14d'] as $f)
                        <span class="badge badge-info mr-1">{{ \Illuminate\Support\Carbon::parse($f)->format('d/m') }}</span>
                    @empty
                        <span class="text-muted">Ningún día en los próximos 14.</span>
                    @endforelse
                </p>

                @if (! empty($puede_ejecutar))
                    <form action="{{ route('ejecutar_ahora_modulo_aviso', $tipo->id) }}" method="POST" class="mt-3"
                          onsubmit="return confirm('¿Ejecutar ahora? Revisá el impacto arriba.');">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="fa fa-bolt"></i> Ejecutar ahora
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
