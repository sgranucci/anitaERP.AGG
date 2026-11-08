@extends("theme.$theme.layout")
@section('titulo')
    Cierre fiscal Z (p-cierrefact)
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cierre fiscal Z — facturación</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Equivalente ERP de Anita <strong>p-cierrefact</strong>: acumula la
                    <strong>venta</strong> del día por punto de venta (FAC → venta Z, NC* → créditos)
                    y graba el informe <strong>Z</strong> en
                    <code>tesoreria_crown_cierre_fiscal</code>.
                    No genera informes X ni la rendición de mozo (eso es
                    <em>Cierre X / Rendición mozo</em>).
                </p>

                <form method="get" action="{{ route('gastronomia_crown_cierre_fiscal') }}" class="form-inline mb-3" data-enter-campos="1">
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-group mb-2 mr-2">
                        <label for="fecha" class="mr-1 mb-0">Fecha jornada</label>
                        <input type="date" name="fecha" id="fecha" class="form-control" value="{{ $fecha }}" required>
                    </div>
                    <div class="form-group mb-2 mr-2">
                        <label for="sucursal" class="mr-1 mb-0">PV / sucursal</label>
                        <input type="number" name="sucursal" id="sucursal" class="form-control" style="width:7rem"
                               value="{{ $sucursal }}" placeholder="Todas" min="1" step="1">
                    </div>
                    <button type="submit" class="btn btn-info mb-2">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                </form>

                @if ($jornada)
                    <p class="small text-muted">Jornada tesorería Crown #{{ $jornada->id }} del {{ $jornada->fecha->format('d/m/Y') }}.</p>
                @else
                    <div class="alert alert-warning py-2">No hay jornada de tesorería Crown para esa fecha. El Z se puede grabar igual (sin <code>jornada_id</code>).</div>
                @endif

                @if ($error_preview)
                    <div class="alert alert-danger">{{ $error_preview }}</div>
                @endif

                <h5 class="mt-3">Z ya grabados en ERP</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Sucursal</th>
                                <th>Z</th>
                                <th class="text-right">Venta</th>
                                <th class="text-right">Crédito (NC)</th>
                                <th>Hora</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($existentes as $fila)
                                <tr>
                                    <td>{{ $fila->sucursal }} {{ $fila->nombre_sucursal }}</td>
                                    <td>{{ $fila->nro_z }}</td>
                                    <td class="text-right">{{ number_format((float) $fila->tot_venta, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) $fila->tot_cred, 2, ',', '.') }}</td>
                                    <td>{{ $fila->hora }}</td>
                                    <td>{{ $fila->comprobante_id ? 'Tomado rendición #'.$fila->comprobante_id : 'Pendiente' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6">Sin Z en ERP para esta fecha.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($consultar && is_array($preview))
                    <h5>Simulación desde venta ERP</h5>
                    <p class="small text-muted mb-2">
                        Si el Z ya existe con otro total (p. ej. sync Anita), se omite salvo que fuerce.
                    </p>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Sucursal</th>
                                    <th>Z</th>
                                    <th class="text-right">Venta</th>
                                    <th class="text-right">Crédito</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($preview['filas'] as $f)
                                    <tr>
                                        <td>{{ $f['sucursal'] ?? '' }}</td>
                                        <td>{{ $f['nro_z'] ?? '' }}</td>
                                        <td class="text-right">{{ number_format((float) ($f['tot_venta'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($f['tot_cred'] ?? 0), 2, ',', '.') }}</td>
                                        <td>{{ $f['accion'] ?? '' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5">Sin venta ERP para PVs con Z Anita/ERP ese día.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($puede_procesar)
                        <form method="post" action="{{ route('gastronomia_crown_cierre_fiscal_procesar') }}"
                              onsubmit="return confirm('¿Grabar el cierre fiscal Z con estos criterios?');"
                              data-enter-campos="1">
                            @csrf
                            <input type="hidden" name="fecha" value="{{ $fecha }}">
                            <input type="hidden" name="sucursal" value="{{ $sucursal }}">
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" name="forzar_tomados" id="forzar_tomados" value="1">
                                <label class="form-check-label" for="forzar_tomados">
                                    Forzar: pisar Z existentes (también tomados por rendición)
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-save"></i> Grabar cierre Z
                            </button>
                        </form>
                    @endif
                @elseif (! $consultar)
                    <p class="text-muted">Pulse <strong>Consultar</strong> para simular el cierre desde venta.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
