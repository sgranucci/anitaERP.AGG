@extends("theme.$theme.layout")
@section('titulo')
    Cierre X / Rendición mozo
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cierre X / Rendición mozo</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    El <strong>total X</strong> es lo que cada mozo tiene que rendir en tesorería antes del Z fiscal.
                    Sale de las cuentas <strong>cerradas</strong> del POS Crown de la jornada de la sala.
                    Al crear la rendición se continúa en <strong>Caja → Rendición gastronomía</strong>.
                </p>

                <form method="get" action="{{ route('gastronomia_crown_cierre_x') }}" class="form-inline mb-4" id="form-filtros-cierre-x">
                    @include('includes.listado.filtro_empresa_asignada_inline', [
                        'empresas' => $empresas,
                        'empresa_id' => $empresa_id,
                        'required' => true,
                        'permite_todas' => false,
                    ])
                    <div class="form-group mb-0 ml-2">
                        <label for="sala_id" class="mr-1 mb-0">Sala</label>
                        <select name="sala_id" id="sala_id" class="form-control" onchange="this.form.submit()">
                            <option value="">— Elija la sala —</option>
                            @foreach ($salas as $sala)
                                <option value="{{ $sala->id }}" {{ (int) $sala_id === (int) $sala->id ? 'selected' : '' }}>
                                    {{ trim(($sala->codigo ? $sala->codigo.' — ' : '').$sala->nombre) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-0 ml-2">
                        <label for="jornada_gastronomia_id" class="mr-1 mb-0">Jornada</label>
                        <select name="jornada_gastronomia_id" id="jornada_gastronomia_id" class="form-control" onchange="this.form.submit()">
                            @forelse ($jornadas as $j)
                                <option value="{{ $j->id }}" {{ (int) ($jornada->id ?? 0) === (int) $j->id ? 'selected' : '' }}>
                                    #{{ $j->id }} — {{ $j->fecha_jornada?->format('d/m/Y') }} ({{ ucfirst($j->estado) }})
                                </option>
                            @empty
                                <option value="">Sin jornadas</option>
                            @endforelse
                        </select>
                    </div>
                    <button type="submit" class="btn btn-info ml-2">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                </form>

                @if ((int) $sala_id <= 0)
                    <div class="alert alert-info mb-0">
                        Elija la <strong>sala</strong> para ver los cierres X por mozo.
                    </div>
                @elseif ($jornada === null)
                    <div class="alert alert-warning mb-0">
                        No hay jornadas de gastronomía para esta sala. Abra la jornada en
                        <strong>Ventas → Gastronomía → Jornada</strong>.
                    </div>
                @else
                    <div class="alert alert-secondary">
                        <strong>Jornada #{{ $jornada->id }}</strong>
                        — {{ $jornada->fecha_jornada?->format('d/m/Y') }}
                        · Estado: <strong>{{ ucfirst($jornada->estado) }}</strong>
                        @if ($jornada->sala?->nombre)
                            · Sala: <strong>{{ $jornada->sala->nombre }}</strong>
                        @endif
                        @if ($jornada->apertura_en)
                            · Apertura: {{ $jornada->apertura_en->format('d/m/Y H:i') }}
                        @endif
                        @if ($jornada->cierre_en)
                            · Cierre: {{ $jornada->cierre_en->format('d/m/Y H:i') }}
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered" id="tabla-cierre-x">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Mozo</th>
                                    <th class="text-right">Cuentas</th>
                                    <th class="text-right">Total X a rendir</th>
                                    <th class="text-right">Total facturado</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($totales as $fila)
                                    <tr>
                                        <td>
                                            {{ $fila['mozo_nombre'] }}
                                            @if ($fila['mozo_codigo'] !== '')
                                                <small class="text-muted">({{ $fila['mozo_codigo'] }})</small>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ $fila['cuentas'] }}</td>
                                        <td class="text-right">{{ number_format($fila['total_x'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format($fila['total_facturado'], 2, ',', '.') }}</td>
                                        <td>
                                            @if ($fila['ya_rendido'])
                                                <span class="badge badge-success">Rendido</span>
                                            @else
                                                <span class="badge badge-warning">Pendiente</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            @if ($fila['ya_rendido'])
                                                <a href="{{ route('editar_rendiciongastronomia', ['id' => $fila['rendicion_id']]) }}"
                                                   class="btn btn-outline-info btn-sm" target="_blank" rel="noopener">
                                                    <i class="fa fa-eye"></i> Ver rendición
                                                </a>
                                            @elseif ($puede_crear)
                                                <form method="post" action="{{ route('gastronomia_crown_cierre_x_crear_rendicion') }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="empresa_id" value="{{ $empresa_id }}"/>
                                                    <input type="hidden" name="sala_id" value="{{ $sala_id }}"/>
                                                    <input type="hidden" name="jornada_gastronomia_id" value="{{ $jornada->id }}"/>
                                                    <input type="hidden" name="mozo_gastronomia_id" value="{{ $fila['mozo_gastronomia_id'] }}"/>
                                                    <button type="submit" class="btn btn-primary btn-sm"
                                                            onclick="return confirm('¿Crear la rendición de tesorería del mozo {{ $fila['mozo_nombre'] }}?');">
                                                        <i class="fa fa-cash-register"></i> Crear rendición
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted small">Sin permiso para rendir.</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            Sin cuentas cerradas de mozos en esta jornada.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if (count($totales) > 0)
                                <tfoot>
                                    <tr class="font-weight-bold">
                                        <td colspan="2" class="text-right">Total X de la jornada</td>
                                        <td class="text-right">{{ number_format((float) $total_general, 2, ',', '.') }}</td>
                                        <td colspan="3"></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
