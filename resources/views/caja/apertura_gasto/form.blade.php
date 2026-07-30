@php
    use App\Models\Caja\AperturaGasto;
    $puedeAbrirAbmCuenta = can('editar-cuentas-contables', false) || can('listar-cuentas-contables', false);
    $puedeAbrirAbmCc = can('editar-centro-costo', false) || can('listar-centro-costo', false);

    $lineasOld = old('empresa_ids');
    if (is_array($lineasOld)) {
        $lineasForm = [];
        $n = count($lineasOld);
        for ($i = 0; $i < $n; $i++) {
            $lineasForm[] = (object) [
                'empresa_id' => old('empresa_ids.'.$i),
                'cuentacontable_id' => old('cuentacontable_ids.'.$i),
                'cuentacontable_contrapartida_id' => old('cuentacontable_contrapartida_ids.'.$i),
                'centrocosto_id' => old('centrocosto_ids.'.$i),
            ];
        }
        if ($lineasForm === []) {
            $lineasForm = [null];
        }
    } else {
        $lineasForm = ($data->empresas ?? collect())->count()
            ? $data->empresas
            : [null];
    }
@endphp

<style>
    #tabla-apertura-gasto-empresas thead th {
        background: #85C1E9;
        color: #17202A;
        font-size: 0.85rem;
        white-space: nowrap;
    }
    #tabla-apertura-gasto-empresas .tm-cuentacontable-campo,
    #tabla-apertura-gasto-empresas .tm-centrocosto-campo {
        margin-bottom: 0;
    }
    #tabla-apertura-gasto-empresas td {
        vertical-align: middle;
    }
    .apg-cuenta-compact .nombrecuentacontable,
    .apg-cuenta-compact .descripcioncentrocosto {
        min-width: 8rem;
    }
</style>

<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">C&oacute;digo</label>
    <div class="col-lg-3">
        <input type="number" name="codigo" id="codigo" class="form-control text-right" min="1" step="1"
               value="{{ old('codigo', $data->codigo ?? '') }}"
               @if(isset($data->id)) readonly @else required @endif/>
        <small class="form-text text-muted">Concepto Anita (apg_concepto).</small>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="40" required
               value="{{ old('nombre', $data->nombre ?? '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
    <div class="col-lg-4">
        <select id="estado" name="estado" class="form-control" required>
            <option value="">-- Elija estado --</option>
            @foreach($estado_enum as $estado)
                <option value="{{ $estado['valor'] }}"
                    @selected($estado['valor'] == old('estado', $data->estado ?? AperturaGasto::ESTADO_ACTIVO))>
                    {{ $estado['nombre'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="card card-outline card-info mb-0">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <strong>Cuentas por empresa</strong>
        <small class="text-muted mb-0">Una fila por empresa (cuenta, contrapartida y centro de costo).</small>
    </div>
    <div class="card-body p-2">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-2" id="tabla-apertura-gasto-empresas">
                <thead>
                    <tr>
                        <th style="min-width: 10rem;">Empresa</th>
                        <th style="min-width: 16rem;">Cuenta contable</th>
                        <th style="min-width: 16rem;">Contrapartida</th>
                        <th style="min-width: 12rem;">Centro de costo</th>
                        <th class="text-center" style="width: 3rem;"></th>
                    </tr>
                </thead>
                <tbody id="tbody-apertura-gasto-empresas">
                    @foreach ($lineasForm as $linea)
                        @include('caja.apertura_gasto.partials.fila_empresa', [
                            'linea' => $linea,
                            'empresa_query' => $empresa_query,
                            'puedeAbrirAbmCuenta' => $puedeAbrirAbmCuenta,
                            'puedeAbrirAbmCc' => $puedeAbrirAbmCc,
                            'indice' => $loop->index,
                        ])
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('caja.apertura_gasto.partials.template_fila_empresa', [
            'empresa_query' => $empresa_query,
            'puedeAbrirAbmCuenta' => $puedeAbrirAbmCuenta,
            'puedeAbrirAbmCc' => $puedeAbrirAbmCc,
        ])
        <div class="d-flex justify-content-end flex-wrap" style="gap: 6px;">
            <button type="button" id="replicar_apertura_gasto_todas" class="btn btn-sm btn-outline-info"
                    title="Toma la primera fila completa y genera el resto de empresas con la misma cuenta (por c&oacute;digo)">
                <i class="fa fa-copy"></i> Enviar a todas las empresas
            </button>
            <button type="button" id="agrega_renglon_apertura_gasto" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-plus"></i> Agregar empresa
            </button>
        </div>
    </div>
</div>
