@php
    $formulariosOld = old('formularios');
    $reglasOld = old('reglas');
    $copiasPreset = $copiasPreset ?? [];
    $formulariosEnum = $formulariosEnum ?? [];
@endphp
<div class="form-group row">
    <label for="codigo" class="col-lg-3 control-label text-right pr-2 requerido">Código</label>
    <div class="col-lg-3">
        <input type="text" name="codigo" id="codigo" class="form-control" maxlength="40" value="{{ old('codigo', $data->codigo ?? '') }}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-6">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="120" value="{{ old('nombre', $data->nombre ?? '') }}" required/>
    </div>
</div>
@php
    $empresasForm = collect($empresa_query ?? $empresas ?? []);
    $unaEmpresaForm = $empresasForm->count() === 1;
@endphp
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresasForm,
    'empresa_id' => old('empresa_id', $data->empresa_id ?? null),
    'required' => $unaEmpresaForm,
    'permite_vacio' => ! $unaEmpresaForm,
    'opcion_vacia' => '-- Para todas las empresas --',
    'col_label' => 'col-lg-3 text-right pr-2',
    'col_input' => 'col-lg-6',
    'solo_lectura' => false,
])
<div class="form-group row">
    <div class="col-lg-3"></div>
    <div class="col-lg-6">
        <p class="text-muted small mb-0">
            La empresa del programa es la del comprobante (punto de venta / vendedor).
            En AGG o en la nube, cada raz&oacute;n social tiene sus programas.
            Vac&iacute;o = aplica a todas las empresas de esta instalaci&oacute;n (solo el default de sistema).
        </p>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Disparo al grabar</label>
    <div class="col-lg-6">
        <div class="form-check">
            <input type="hidden" name="permite_disparo_al_grabar" value="0">
            <input type="checkbox" name="permite_disparo_al_grabar" id="permite_disparo_al_grabar" class="form-check-input" value="1"
                {{ old('permite_disparo_al_grabar', $data->permite_disparo_al_grabar ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="permite_disparo_al_grabar">
                Permite disparar la sesión al grabar la factura (también debe estar tildado en Mi impresora de la sesión)
            </label>
        </div>
    </div>
</div>

<div class="card card-outline card-info mb-3">
    <div class="card-header">
        <h3 class="card-title">Ruta de la sesión de impresión</h3>
    </div>
    <div class="card-body">
        <ol class="programa-ruta-pasos pl-3 mb-3">
            <li>Sumá a la ruta los comprobantes que tienen que salir juntos (Factura, Remito, Pedido).</li>
            <li>En cada cuadro, tocá <strong>Original / Duplicado / Triplicado…</strong> para elegir las copias de ese comprobante.</li>
            <li>En cada hoja, indicá a quién va. Papel: Impresora del usuario. NAS: salida de archivo.</li>
        </ol>

        <div id="programa-agregar-comprobantes" class="mb-3" data-presets='@json($copiasPreset)'>
            <div class="font-weight-bold mb-1">1. Comprobantes de esta sesión</div>
            <p class="text-muted small mb-2">Si no está el Remito o el Pedido en la ruta, no se imprimen en la sesión.</p>
            <div class="btn-group flex-wrap" role="group">
                @foreach($formulariosEnum as $codigo => $etiqueta)
                    <button type="button"
                            class="btn btn-primary btn-sm agrega-comprobante"
                            data-formulario="{{ $codigo }}"
                            data-etiqueta="{{ $etiqueta }}">
                        <i class="fa fa-plus"></i> {{ $etiqueta }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="font-weight-bold mb-1">2. Recorrido</div>
        <div id="programa-ruta-preview" class="programa-ruta-preview mb-3" aria-label="Recorrido de la sesión"></div>

        @php
            $formulariosVista = $formulariosOld ?? $data->formularios;
            $hayFormularios = is_countable($formulariosVista) ? count($formulariosVista) > 0 : false;
        @endphp
        <div id="programa-formularios" class="programa-ruta-armado"
             data-etiquetas='@json($formulariosEnum)'
             @if (! $hayFormularios) style="display:none;" @endif>
            @forelse($formulariosVista as $fi => $form)
                @include('ventas.programa_impresion.partials.fila_formulario', ['fi' => $fi, 'form' => $form])
            @empty
            @endforelse
        </div>
        <p id="programa-ruta-vacia" class="text-muted mt-2 mb-0" @if ($hayFormularios) style="display:none;" @endif>
            Todavía no hay comprobantes. Usá los botones de arriba para sumar Factura, Remito o Pedido a la ruta.
        </p>
    </div>
</div>

<div class="card card-outline card-info">
    <div class="card-header">
        <h3 class="card-title">Reglas de asignación</h3>
    </div>
    <div class="card-body">
        @include('ventas.programa_impresion.partials.ayuda_precedencia')
        <table class="table table-sm table-bordered" id="tabla-reglas">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Tipo</th>
                    <th>Valor</th>
                    <th class="width80">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse(($reglasOld ?? $data->reglas) as $ri => $regla)
                    @include('ventas.programa_impresion.partials.fila_regla', ['ri' => $ri, 'regla' => $regla])
                @empty
                    @include('ventas.programa_impresion.partials.fila_regla', ['ri' => 0, 'regla' => null])
                @endforelse
            </tbody>
        </table>
        <button type="button" class="btn btn-outline-primary btn-sm" id="agrega-regla">
            <i class="fa fa-plus"></i> Agregar regla
        </button>
    </div>
</div>

<template id="tpl-formulario">
    @include('ventas.programa_impresion.partials.fila_formulario', ['fi' => '__FI__', 'form' => null])
</template>
<template id="tpl-copia">
    @include('ventas.programa_impresion.partials.fila_copia', ['fi' => '__FI__', 'ci' => '__CI__', 'copia' => null])
</template>
<template id="tpl-regla">
    @include('ventas.programa_impresion.partials.fila_regla', ['ri' => '__RI__', 'regla' => null])
</template>
