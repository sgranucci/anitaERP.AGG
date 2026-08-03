@php
    use App\Models\Sueldos\Liquidacion_Sueldos;
    $periodoDefault = now()->format('Y-m');
@endphp
<style>
    .rastro-tree ul { list-style: none; margin: 0 0 0 1.1rem; padding: 0; border-left: 1px dashed #ccc; }
    .rastro-tree > ul { border-left: none; margin-left: 0; }
    .rastro-tree li { padding: 2px 0 2px 10px; }
    .rastro-tree code { font-size: 12px; }
</style>
<div id="liquidacion-preview-panel"
     class="card card-outline card-info h-100 mb-0"
     data-url="{{ route('simular_liquidacion_empleado_sueldos', ['id' => $data->id]) }}"
     data-url-debug="{{ route('depurar_formulas_empleado_sueldos', ['id' => $data->id]) }}">
    <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between">
        <strong class="mb-0"><i class="fa fa-eye"></i> Preview de conceptos</strong>
        <small class="text-muted">Simulaci&oacute;n (no graba recibo)</small>
    </div>
    <div class="card-body py-2">
        <div class="form-row align-items-end mb-2">
            <div class="form-group col-md-5 mb-2">
                <label class="small mb-0">Per&iacute;odo</label>
                <input type="month" id="preview-periodo" class="form-control form-control-sm" value="{{ $periodoDefault }}">
            </div>
            <div class="form-group col-md-5 mb-2">
                <label class="small mb-0">Tipo de corrida</label>
                <select id="preview-tipo" class="form-control form-control-sm">
                    @foreach (Liquidacion_Sueldos::TIPOS as $cod => $label)
                        <option value="{{ $cod }}" {{ $cod === 'mensual' ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 mb-2">
                <button type="button" id="btn-simular-liquidacion" class="btn btn-info btn-sm btn-block">
                    <i class="fa fa-calculator"></i> Simular
                </button>
                <button type="button" id="btn-depurar-formulas" class="btn btn-outline-secondary btn-sm btn-block mt-1"
                        title="Rastro paso a paso de cada f&oacute;rmula">
                    <i class="fa fa-bug"></i> Debugger
                </button>
            </div>
        </div>
        <div class="form-row align-items-end mb-2" id="preview-debug-filtros" style="display:none;">
            <div class="form-group col-md-4 mb-2">
                <label class="small mb-0">Solo concepto (c&oacute;digo)</label>
                <input type="number" id="preview-debug-concepto" class="form-control form-control-sm" min="1" placeholder="vac&iacute;o = todos">
            </div>
        </div>
        <div class="custom-control custom-checkbox mb-2">
            <input type="checkbox" class="custom-control-input" id="preview-solo-con-importe" checked>
            <label class="custom-control-label small" for="preview-solo-con-importe">Solo conceptos con importe ≠ 0</label>
        </div>
        <div id="preview-meta" class="small text-muted mb-2">Elegí período y pulsá Simular.</div>
        <div id="preview-errores" class="d-none"></div>
        <div class="row text-center mb-2" id="preview-totales" style="display:none;">
            <div class="col-4">
                <small class="text-muted d-block">Haberes</small>
                <strong id="preview-tot-haber">0</strong>
            </div>
            <div class="col-4">
                <small class="text-muted d-block">Descuentos</small>
                <strong id="preview-tot-descuento">0</strong>
            </div>
            <div class="col-4">
                <small class="text-muted d-block">Neto est.</small>
                <strong id="preview-tot-neto" class="text-success">0</strong>
            </div>
        </div>
        <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
            <table class="table table-sm table-bordered table-striped mb-0" id="tabla-preview-conceptos">
                <thead style="background:#85C1E9;color:#17202A; position: sticky; top: 0; z-index: 1;">
                    <tr>
                        <th>Cód.</th>
                        <th>Concepto</th>
                        <th>Origen</th>
                        <th>Tipo</th>
                        <th class="text-right">Importe</th>
                    </tr>
                </thead>
                <tbody id="tbody-preview-conceptos">
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">Sin simulación aún.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-2">
            <button type="button" class="btn btn-link btn-sm p-0" data-toggle="collapse" data-target="#preview-excluidos-wrap">
                <i class="fa fa-ban"></i> Ver conceptos que no entraron al set
                <span id="preview-excluidos-count" class="text-muted"></span>
            </button>
            <div class="collapse" id="preview-excluidos-wrap">
                <div class="table-responsive mt-1" style="max-height: 180px; overflow-y: auto;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:70px;">Cód.</th>
                                <th>Concepto</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-preview-excluidos">
                            <tr><td colspan="3" class="text-muted text-center small">Sin datos.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-3 d-none" id="preview-debug-wrap">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <strong class="small"><i class="fa fa-bug"></i> Debugger de f&oacute;rmulas</strong>
                <button type="button" class="btn btn-link btn-sm p-0" id="btn-cerrar-debug">Cerrar</button>
            </div>
            <div id="preview-debug-host" class="border rounded p-2 bg-white" style="max-height: 480px; overflow-y: auto;"></div>
        </div>
    </div>
</div>
