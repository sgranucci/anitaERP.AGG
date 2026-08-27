@php
    $perc = $percepcionNoCateg ?? [
        'habilitado' => false,
        'tasa' => 10.5,
        'minimo' => 0,
        'impuesto_id' => null,
    ];
    $puedeEditarPerc = can('actualizar-impuestos', false);
@endphp
<div class="card card-outline card-primary mt-3">
    <div class="card-header">
        <h3 class="card-title">Percepción a sujetos no categorizados (RG 2126)</h3>
    </div>
    <form action="{{ route('actualizar_percepcion_no_categorizado') }}" method="POST" class="form-horizontal" id="form-percepcion-no-categ">
        @csrf
        @method('PUT')
        <div class="card-body">
            <p class="text-muted mb-3">
                Se cobra sobre el total de la factura (neto + IVA + otros tributos) cuando el cliente es
                Sujeto No Categorizado. No es la percepción IVA 3&nbsp;% a responsables inscriptos (RG 5329).
                La cuenta del asiento se carga en el impuesto código <strong>PNC</strong>.
            </p>
            <div class="form-group row">
                <label for="habilitado" class="col-lg-3 control-label text-right pr-2">Agente de percepción</label>
                <div class="col-lg-8">
                    <div class="custom-control custom-checkbox pt-2">
                        <input type="hidden" name="habilitado" value="0">
                        <input type="checkbox" class="custom-control-input" name="habilitado" id="habilitado" value="1"
                            {{ ! empty($perc['habilitado']) ? 'checked' : '' }}
                            {{ $puedeEditarPerc ? '' : 'disabled' }}>
                        <label class="custom-control-label" for="habilitado">Aplicar RG 2126 al facturar</label>
                    </div>
                </div>
            </div>
            <div class="form-group row">
                <label for="tasa" class="col-lg-3 control-label text-right pr-2 requerido">Alícuota (%)</label>
                <div class="col-lg-2">
                    <input type="number" step="0.0001" min="0" max="100" name="tasa" id="tasa" class="form-control"
                        value="{{ old('tasa', $perc['tasa']) }}" required {{ $puedeEditarPerc ? '' : 'readonly' }}>
                </div>
                <div class="col-lg-6 col-form-label text-muted">
                    IVA 21&nbsp;%: esta tasa. IVA reducido 10,5&nbsp;%: la mitad (como a-comprob.c).
                </div>
            </div>
            <div class="form-group row">
                <label for="minimo" class="col-lg-3 control-label text-right pr-2">Mínimo</label>
                <div class="col-lg-2">
                    <input type="number" step="0.01" min="0" name="minimo" id="minimo" class="form-control"
                        value="{{ old('minimo', $perc['minimo']) }}" required {{ $puedeEditarPerc ? '' : 'readonly' }}>
                </div>
                <div class="col-lg-6 col-form-label text-muted">
                    Si el importe calculado no supera este mínimo, no se percibe.
                </div>
            </div>
            @if (! empty($perc['impuesto_id']))
                <div class="form-group row">
                    <label class="col-lg-3 control-label text-right pr-2">Cuenta contable</label>
                    <div class="col-lg-8 pt-2">
                        <a href="{{ route('editar_impuesto', ['id' => $perc['impuesto_id']]) }}" class="text-primary">
                            Impuesto PNC — Cuentas contables
                        </a>
                    </div>
                </div>
            @endif
        </div>
        @if ($puedeEditarPerc)
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save"></i> Guardar percepción no categorizado
                </button>
            </div>
        @endif
    </form>
</div>
