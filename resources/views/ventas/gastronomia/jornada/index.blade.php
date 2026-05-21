@extends("theme.$theme.layout")
@section('titulo')
    Jornada gastronomía
@endsection

@section("scripts")
<script>
    window.JORNADA_GASTRONOMIA = {
        csrf: @json(csrf_token()),
    };
</script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/jornada.js') }}?v={{ filemtime(public_path('assets/pages/scripts/ventas/gastronomia/jornada.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="jornada-gastronomia-app"
     data-api-estado="{{ url('ventas/gastronomia/jornada/api/estado') }}"
     data-api-abrir="{{ url('ventas/gastronomia/jornada/api/abrir') }}"
     data-api-cerrar="{{ url('ventas/gastronomia/jornada/api/cerrar') }}"
     data-csrf="{{ csrf_token() }}"
     data-puede-abrir="{{ $puede_abrir ? '1' : '0' }}"
     data-puede-cerrar="{{ $puede_cerrar ? '1' : '0' }}">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Apertura y cierre de jornada</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    La <strong>fecha de factura</strong> de cada comprobante es siempre el día calendario real.
                    La <strong>fecha de jornada</strong> es la del turno abierto y se graba en <code>venta.fechajornada</code>
                    para todas las terminales de la empresa.
                </p>

                <form method="get" action="{{ url('ventas/gastronomia/jornada') }}" class="form-inline mb-4">
                    <label class="mr-2" for="empresa_id">Empresa</label>
                    <select name="empresa_id" id="empresa_id" class="form-control mr-2">
                        @foreach ($empresas as $emp)
                            <option value="{{ $emp->id }}" @selected((int) $empresa_id === (int) $emp->id)>
                                {{ $emp->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-secondary btn-sm">Consultar</button>
                </form>

                @if ($estado)
                    <div id="jornada-estado-panel" class="mb-4">
                        @if ($estado['jornada_abierta'])
                            <div class="alert alert-success">
                                <strong>Jornada abierta</strong>
                                — Fecha jornada:
                                <span id="lbl-fecha-jornada">{{ $estado['fecha_jornada'] }}</span>
                                · Facturas de hoy usan fecha
                                <span id="lbl-fecha-factura">{{ $estado['fecha_factura_hoy'] }}</span>
                                @if (! empty($estado['usuario_apertura']))
                                    <br>Abierta por {{ $estado['usuario_apertura'] }}
                                    @if (! empty($estado['apertura_en']))
                                        ({{ $estado['apertura_en'] }})
                                    @endif
                                @endif
                                @if (! empty($estado['observacion_apertura']))
                                    <br><em>{{ $estado['observacion_apertura'] }}</em>
                                @endif
                            </div>
                        @else
                            <div class="alert alert-warning">
                                <strong>Sin jornada abierta</strong> para esta empresa.
                                Las terminales de facturación no podrán emitir comprobantes hasta abrir la jornada.
                            </div>
                        @endif
                    </div>

                    <div class="row mb-4">
                        @if ($puede_abrir)
                            <div class="col-md-6">
                                <div class="card card-outline card-success @if ($estado['jornada_abierta']) opacity-50 @endif">
                                    <div class="card-header">Abrir jornada</div>
                                    <div class="card-body">
                                        @if (! empty($estado['motivo_no_puede_abrir']))
                                            <p class="text-muted small mb-2">{{ $estado['motivo_no_puede_abrir'] }}</p>
                                        @endif
                                        <div class="form-group">
                                            <label for="fecha_jornada_abrir">Fecha de jornada (turno)</label>
                                            <input type="date" class="form-control" id="fecha_jornada_abrir"
                                                   value="{{ $fecha_hoy }}" @disabled($estado['jornada_abierta'])>
                                        </div>
                                        <div class="form-group">
                                            <label for="observacion_abrir">Observación</label>
                                            <textarea class="form-control" id="observacion_abrir" rows="2"
                                                      @disabled($estado['jornada_abierta'])></textarea>
                                        </div>
                                        <button type="button" class="btn btn-success" id="btn-abrir-jornada"
                                                @disabled($estado['jornada_abierta'])>
                                            Abrir jornada
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($puede_cerrar)
                            <div class="col-md-6">
                                <div class="card card-outline card-danger @if (! $estado['jornada_abierta']) opacity-50 @endif">
                                    <div class="card-header">Cerrar jornada</div>
                                    <div class="card-body">
                                        @if (! empty($estado['errores_cierre']))
                                            <ul class="text-danger small" id="lista-errores-cierre">
                                                @foreach ($estado['errores_cierre'] as $err)
                                                    <li>{{ $err }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        <div class="form-group">
                                            <label for="observacion_cerrar">Observación de cierre</label>
                                            <textarea class="form-control" id="observacion_cerrar" rows="2"
                                                      @disabled(! $estado['jornada_abierta'])></textarea>
                                        </div>
                                        <button type="button" class="btn btn-danger" id="btn-cerrar-jornada"
                                                @disabled(! $estado['jornada_abierta'] || ! empty($estado['errores_cierre']))>
                                            Cerrar jornada
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                <h5>Historial reciente</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha jornada</th>
                                <th>Estado</th>
                                <th>Apertura</th>
                                <th>Cierre</th>
                                <th>Usuario apertura</th>
                                <th>Usuario cierre</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($historial as $j)
                                <tr>
                                    <td>{{ $j->id }}</td>
                                    <td>{{ $j->fecha_jornada->format('d/m/Y') }}</td>
                                    <td>{{ ucfirst($j->estado) }}</td>
                                    <td>{{ $j->apertura_en?->format('d/m/Y H:i') }}</td>
                                    <td>{{ $j->cierre_en?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td>{{ $j->usuarioApertura->nombre ?? '—' }}</td>
                                    <td>{{ $j->usuarioCierre->nombre ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Sin registros.</td>
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
