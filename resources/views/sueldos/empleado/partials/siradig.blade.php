<?php use App\Support\Sueldos\SiradigTablas; ?>
<div id="siradig-panel" data-empleado="{{ $empleado->id }}">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <h5 class="mb-0"><i class="fa fa-file-invoice-dollar"></i> SiRADIG — F572 (deducciones Ganancias)</h5>
        <a href="{{ route('consultar_siradig_sueldos') }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
            <i class="fa fa-file-import"></i> Importar / administrar F572
        </a>
    </div>

    @if (empty($empleado->cuil))
        <div class="alert alert-warning">El empleado no tiene CUIL cargado; no se puede vincular con presentaciones SiRADIG.</div>
    @endif

    @if ($presentaciones->isEmpty())
        <div class="alert alert-light border text-center text-muted py-4">
            Sin presentaciones F572 para este empleado (CUIL {{ $empleado->cuil ?: '—' }}).<br>
            Importe el XML/ZIP desde <strong>SiRADIG → Importar</strong>; se vincularán por CUIL automáticamente.
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover">
                <thead style="background-color:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Período</th>
                        <th>Secc.</th>
                        <th>Nro</th>
                        <th>Fecha</th>
                        <th class="text-right">Deducciones</th>
                        <th class="text-center">Cargas fam.</th>
                        <th class="text-center">Pluriempleo</th>
                        <th>Estado</th>
                        <th style="width:60px"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($presentaciones as $p)
                        <tr class="{{ $p->vigente ? '' : 'text-muted' }}">
                            <td>{{ $p->periodo }}</td>
                            <td><span class="badge badge-{{ $p->seccion === 'A' ? 'primary' : 'secondary' }}">{{ $p->seccion }}</span></td>
                            <td>{{ $p->nro_presentacion }}</td>
                            <td>{{ optional($p->fecha_presentacion)->format('d/m/Y') }}</td>
                            <td class="text-right">{{ number_format((float) $p->conceptos->where('grupo', SiradigTablas::GRUPO_DEDUCCION)->sum('monto_total'), 2, ',', '.') }}</td>
                            <td class="text-center">{{ $p->cargasFamilia->count() ?: '—' }}</td>
                            <td class="text-center">{{ $p->otrosEmpleadores->count() ?: '—' }}</td>
                            <td>
                                @if ($p->vigente)
                                    <span class="badge badge-success">Vigente</span>
                                @else
                                    <span class="badge badge-light">Reemplazada</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if ($puedeVer)
                                    <a href="{{ route('ver_siradig_sueldos', ['id' => $p->id]) }}" class="btn-accion-tabla tooltipsC" title="Ver detalle" target="_blank" rel="noopener">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-muted small mb-0">Se muestran todas las presentaciones (incluye rectificativas). La marcada <span class="badge badge-success">Vigente</span> es la que aplica al período fiscal.</p>
    @endif
</div>
