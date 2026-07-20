@extends("theme.$theme.layout")
@section('titulo')
    Traza de c&aacute;lculo &mdash; {{ $empleado->nombre }}
@endsection

@section('contenido')
@php
    $tipoBadge = [
        'remunerativo' => 'success', 'no_remunerativo' => 'success', 'asignacion' => 'success',
        'descuento' => 'danger', 'aporte' => 'danger', 'retencion' => 'danger',
        'contribucion' => 'secondary', 'informativo' => 'secondary', 'neto' => 'primary',
    ];
@endphp
<style>
    .rastro-tree ul { list-style: none; margin: 0 0 0 1.1rem; padding: 0; border-left: 1px dashed #ccc; }
    .rastro-tree > ul { border-left: none; margin-left: 0; }
    .rastro-tree li { padding: 2px 0 2px 10px; position: relative; }
    .rastro-tree code { font-size: 12px; }
</style>
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-bug"></i> Traza de c&aacute;lculo &mdash; Legajo {{ $empleado->legajo }} &middot; {{ $empleado->nombre }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('resultado_liquidacion_sueldos', ['id' => $liq->id]) }}" class="btn btn-sm btn-secondary">
                        <i class="fa fa-arrow-left"></i> Volver al resultado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Corrida N&deg; {{ $liq->numero }} &middot; Per&iacute;odo
                    {{ $liq->periodo_mes ? sprintf('%02d/%04d', $liq->periodo_mes, $liq->periodo_anio) : $liq->periodo }}.
                    Cada concepto muestra su f&oacute;rmula y el &aacute;rbol de evaluaci&oacute;n paso a paso (las ramas
                    no tomadas por condiciones no aparecen).
                </p>

                @forelse ($pasos as $p)
                    <div class="card mb-2 {{ $p['error'] ? 'border-danger' : '' }}">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center" data-toggle="collapse" data-target="#paso-{{ $p['codigo'] }}" style="cursor:pointer;">
                            <span>
                                <span class="badge badge-{{ $tipoBadge[$p['tipo']] ?? 'secondary' }}">{{ $p['codigo'] }}</span>
                                <strong>{{ $p['descripcion'] }}</strong>
                            </span>
                            <span>
                                @if ($p['error'])
                                    <span class="badge badge-danger">error</span>
                                @else
                                    Importe: <strong>$ {{ number_format((float) $p['importe'], 2, ',', '.') }}</strong>
                                @endif
                                <i class="fa fa-chevron-down ml-2"></i>
                            </span>
                        </div>
                        <div class="collapse" id="paso-{{ $p['codigo'] }}">
                            <div class="card-body py-2">
                                @if ($p['error'])
                                    <div class="alert alert-danger mb-2">{{ $p['error'] }}</div>
                                @endif
                                @if ($p['formula'])
                                    <div class="mb-2"><small class="text-muted">F&oacute;rmula:</small> <code>{{ $p['formula'] }}</code></div>
                                @else
                                    <div class="mb-2 text-muted"><small>Sin f&oacute;rmula de importe: cantidad &times; valor = {{ $p['cantidad'] }} &times; {{ $p['valor'] }}</small></div>
                                @endif

                                @if (! empty($p['rastro']))
                                    <div class="rastro-tree">
                                        <ul>
                                            @foreach ($p['rastro'] as $nodo)
                                                @include('sueldos.liquidacion.partials.rastro_nodo', ['nodo' => $nodo])
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if (! empty($p['acumuladores']))
                                    <div class="mt-2">
                                        <small class="text-muted">Acumuladores tras este concepto:</small>
                                        @foreach ($p['acumuladores'] as $cod => $val)
                                            <span class="badge badge-light border">{{ $cod }}: {{ number_format((float) $val, 2, ',', '.') }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="alert alert-warning">No hay conceptos activos para trazar.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
