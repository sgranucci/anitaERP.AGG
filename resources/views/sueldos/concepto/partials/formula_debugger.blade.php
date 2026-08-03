@php
    use App\Models\Sueldos\Liquidacion_Sueldos;
    $tiposLiq = $tiposLiquidacion ?? Liquidacion_Sueldos::TIPOS;
@endphp
<style>
    .rastro-tree ul { list-style: none; margin: 0 0 0 1.1rem; padding: 0; border-left: 1px dashed #ccc; }
    .rastro-tree > ul { border-left: none; margin-left: 0; }
    .rastro-tree li { padding: 2px 0 2px 10px; }
    .rastro-tree code { font-size: 12px; }
</style>
<div id="formula-debugger-concepto"
     class="card card-outline card-secondary mt-3"
     data-url-depurar="{{ route('depurar_formula_concepto_sueldos', ['id' => $concepto->id]) }}"
     data-url-validar="{{ route('validar_formula_concepto_sueldos') }}">
    <div class="card-header py-2">
        <strong><i class="fa fa-bug"></i> Debugger de f&oacute;rmulas</strong>
        <span class="small text-muted ml-2">
            Prob&aacute; la f&oacute;rmula del formulario contra un legajo (sin grabar). Incluye rastro paso a paso.
        </span>
    </div>
    <div class="card-body py-2">
        <div class="form-row align-items-end">
            <div class="form-group col-md-3 mb-2">
                <label class="small mb-0 control-label text-right pr-2 d-block text-left">Empresa</label>
                <select id="dbg-empresa-id" class="form-control form-control-sm">
                    <option value="">—</option>
                    @foreach (($empresas ?? []) as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-0">Legajo</label>
                <input type="number" id="dbg-legajo" class="form-control form-control-sm" min="1" placeholder="ej. 7702872">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-0">o Empleado ID</label>
                <input type="number" id="dbg-empleado-id" class="form-control form-control-sm" min="1">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-0">Per&iacute;odo</label>
                <input type="month" id="dbg-periodo" class="form-control form-control-sm" value="{{ now()->format('Y-m') }}">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-0">Tipo</label>
                <select id="dbg-tipo" class="form-control form-control-sm">
                    @foreach ($tiposLiq as $cod => $label)
                        <option value="{{ $cod }}" {{ $cod === 'mensual' ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-1 mb-2">
                <button type="button" id="btn-dbg-run" class="btn btn-secondary btn-sm btn-block" title="Depurar">
                    <i class="fa fa-play"></i>
                </button>
            </div>
        </div>
        <div class="mb-2">
            <button type="button" id="btn-dbg-validar" class="btn btn-outline-info btn-sm">
                <i class="fa fa-check"></i> Validar sintaxis (importe)
            </button>
            <span id="dbg-validar-msg" class="small ml-2"></span>
        </div>
        <div id="dbg-host" class="border rounded p-2 bg-light" style="min-height: 60px;">
            <p class="text-muted small mb-0">Eleg&iacute; legajo/per&iacute;odo y pulsá ▶ para ver el rastro.</p>
        </div>
    </div>
</div>
