<div class="card card-secondary mt-3" id="sesiones-envio">
    <div class="card-header">
        <h3 class="card-title">Sesiones de env&iacute;o COT</h3>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-end justify-content-between mb-3">
            <div class="mb-2">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_cot_electronico',
                    'queryparams' => $filtrosHistoricoQuery ?? [],
                ])
            </div>
            <form method="get" action="{{ route('cot_electronico') }}" class="d-flex flex-wrap align-items-end ml-auto">
                <div class="form-group mr-2 mb-2">
                    <label for="fecha_desde" class="mr-1">Env&iacute;o desde</label>
                    <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                        value="{{ $filtrosHistorico['fecha_desde'] ?? '' }}">
                </div>
                <div class="form-group mr-2 mb-2">
                    <label for="fecha_hasta" class="mr-1">Hasta</label>
                    <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                        value="{{ $filtrosHistorico['fecha_hasta'] ?? '' }}">
                </div>
                <div class="form-group mr-2 mb-2">
                    <label for="ambiente_filtro" class="mr-1">Ambiente</label>
                    <select name="ambiente" id="ambiente_filtro" class="form-control">
                        <option value="">Todos</option>
                        <option value="test" @selected(($filtrosHistorico['ambiente'] ?? '') === 'test')>Test</option>
                        <option value="prod" @selected(($filtrosHistorico['ambiente'] ?? '') === 'prod')>Prod</option>
                    </select>
                </div>
                <div class="form-group mr-2 mb-2">
                    <label for="ok_filtro" class="mr-1">Estado</label>
                    <select name="ok" id="ok_filtro" class="form-control">
                        <option value="">Todos</option>
                        <option value="1" @selected(($filtrosHistorico['ok'] ?? '') === '1')>OK</option>
                        <option value="0" @selected(($filtrosHistorico['ok'] ?? '') === '0')>Con errores</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary mb-2">Filtrar sesiones</button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover">
                <thead style="background-color:#85C1E9;color:#17202A;">
                    <tr>
                        <th>#</th>
                        <th>Fecha env&iacute;o</th>
                        <th>Fecha facturas</th>
                        <th>Reparto</th>
                        <th>Ambiente</th>
                        <th>Usuario</th>
                        <th>Archivo</th>
                        <th>Comprob. ARBA</th>
                        <th class="text-center">Remitos</th>
                        <th class="text-center">OK</th>
                        <th class="text-center">Errores</th>
                        <th>Estado</th>
                        <th style="width:90px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sesiones as $sesion)
                        <tr class="{{ ($sesionId ?? null) === $sesion->id ? 'table-info' : '' }}">
                            <td>{{ $sesion->id }}</td>
                            <td>{{ $sesion->fecha_envio?->format('d/m/Y H:i') }}</td>
                            <td>{{ $sesion->fecha_facturas?->format('d/m/Y') }}</td>
                            <td>{{ $sesion->etiquetaRepartos() !== '' ? $sesion->etiquetaRepartos() : '—' }}</td>
                            <td>
                                <span class="badge badge-{{ $sesion->ambiente === 'prod' ? 'danger' : 'warning' }}">
                                    {{ strtoupper($sesion->ambiente) }}
                                </span>
                            </td>
                            <td>{{ $sesion->usuarios->nombre ?? $sesion->usuarios->usuario ?? '—' }}</td>
                            <td class="small">{{ $sesion->nombre_archivo ?? '—' }}</td>
                            <td>{{ $sesion->numero_comprobante_arba ?? '—' }}</td>
                            <td class="text-center">{{ $sesion->cantidad_remitos }}</td>
                            <td class="text-center text-success">{{ $sesion->cantidad_ok }}</td>
                            <td class="text-center text-danger">{{ $sesion->cantidad_error }}</td>
                            <td>
                                @if ($sesion->ok)
                                    <span class="badge badge-success">OK</span>
                                @else
                                    <span class="badge badge-danger" title="{{ $sesion->error_general }}">Error</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <a href="{{ route('cot_electronico', array_merge($filtrosHistoricoQuery ?? [], ['sesion_id' => $sesion->id])) }}#sesion-detalle"
                                    class="btn btn-info btn-sm" title="Ver detalle">
                                    <i class="fa fa-eye"></i>
                                </a>
                                @if ($sesion->cantidad_ok > 0)
                                    <a href="{{ route('sesion_impresion_cot', ['id' => $sesion->id, 'auto' => 1]) }}"
                                        class="btn btn-outline-success btn-sm" title="Imprimir constancias COT">
                                        <i class="fa fa-print"></i>
                                    </a>
                                @endif
                                <a href="{{ route('listar_cot_electronico_sesion', ['formato' => 'PDF', 'id' => $sesion->id]) }}"
                                    class="btn btn-outline-secondary btn-sm" title="Exportar PDF" target="_blank" rel="noopener">
                                    <i class="fa fa-file-pdf"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="text-center text-muted py-4">No hay sesiones de env&iacute;o en el per&iacute;odo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($sesiones->hasPages())
            <div class="mt-2">
                {{ $sesiones->links() }}
            </div>
        @endif
    </div>
</div>
