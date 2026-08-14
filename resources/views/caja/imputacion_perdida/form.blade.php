@php
    $puedeAbrirAbmCuenta = can('editar-cuentas-contables', false) || can('listar-cuentas-contables', false);

    $lineasOld = old('empresa_ids');
    if (is_array($lineasOld)) {
        $lineasForm = [];
        $n = count($lineasOld);
        for ($i = 0; $i < $n; $i++) {
            $lineasForm[] = (object) [
                'empresa_id' => old('empresa_ids.'.$i),
                'cuentacontable_id' => old('cuentacontable_ids.'.$i),
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
    #tabla-imputacion-perdida-empresas thead th {
        background: #85C1E9;
        color: #17202A;
        font-size: 0.85rem;
        white-space: nowrap;
    }
    #tabla-imputacion-perdida-empresas .tm-cuentacontable-campo {
        margin-bottom: 0;
    }
    #tabla-imputacion-perdida-empresas td {
        vertical-align: middle;
    }
    .ipp-cuenta-compact .nombrecuentacontable {
        min-width: 8rem;
    }
</style>

<div class="form-group row">
    <label for="codigo" class="col-lg-3 control-label text-right pr-2 requerido">C&oacute;digo</label>
    <div class="col-lg-3">
        <input type="number" name="codigo" id="codigo" class="form-control text-right" min="1" step="1"
               value="{{ old('codigo', $data->codigo ?? '') }}"
               @if(isset($data->id)) readonly @else required @endif/>
        <small class="form-text text-muted">C&oacute;digo Anita (impp_codigo).</small>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="30" required
               value="{{ old('nombre', $data->nombre ?? '') }}"/>
        <small class="form-text text-muted">M&aacute;ximo 30 caracteres (impp_desc).</small>
    </div>
</div>

<div class="card card-outline card-info mb-0">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <strong>Cuentas por empresa</strong>
        <small class="text-muted mb-0">Una fila por empresa con su cuenta contable.</small>
    </div>
    <div class="card-body p-2">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-2" id="tabla-imputacion-perdida-empresas">
                <thead>
                    <tr>
                        <th style="min-width: 10rem;">Empresa</th>
                        <th style="min-width: 16rem;">Cuenta contable</th>
                        <th class="text-center" style="width: 3rem;"></th>
                    </tr>
                </thead>
                <tbody id="tbody-imputacion-perdida-empresas">
                    @foreach ($lineasForm as $linea)
                        @include('caja.imputacion_perdida.partials.fila_empresa', [
                            'linea' => $linea,
                            'empresa_query' => $empresa_query,
                            'puedeAbrirAbmCuenta' => $puedeAbrirAbmCuenta,
                            'indice' => $loop->index,
                        ])
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('caja.imputacion_perdida.partials.template_fila_empresa', [
            'empresa_query' => $empresa_query,
            'puedeAbrirAbmCuenta' => $puedeAbrirAbmCuenta,
        ])
        <div class="d-flex justify-content-end flex-wrap" style="gap: 6px;">
            <button type="button" id="replicar_imputacion_perdida_todas" class="btn btn-sm btn-outline-info"
                    title="Toma la primera fila completa y genera el resto de empresas con la misma cuenta (por c&oacute;digo)">
                <i class="fa fa-copy"></i> Enviar a todas las empresas
            </button>
            <button type="button" id="agrega_renglon_imputacion_perdida" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-plus"></i> Agregar empresa
            </button>
        </div>
    </div>
</div>
