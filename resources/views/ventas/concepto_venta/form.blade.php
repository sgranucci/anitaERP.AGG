<div class="alert alert-info py-2">
    Se usa en facturación de administración (mostrador) cuando la línea no es un artículo:
    la descripción y el GTIN van a ARCA; la cuenta, al asiento.
    Gastronomía, estacionamiento y POS no usan este catálogo.
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 control-label text-right pr-2 requerido">Código</label>
    <div class="col-lg-2">
        <input type="text" name="codigo" id="codigo" class="form-control" maxlength="20"
            value="{{ old('codigo', $data->codigo ?? '') }}" required>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-6">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="80"
            value="{{ old('nombre', $data->nombre ?? '') }}" required>
    </div>
</div>
<div class="form-group row">
    <label for="descripcion" class="col-lg-3 control-label text-right pr-2 requerido">Descripción ARCA</label>
    <div class="col-lg-6">
        <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="255"
            value="{{ old('descripcion', $data->descripcion ?? '') }}" required>
        <small class="form-text text-muted">
            Texto del ítem que se informa a ARCA. Puede usar tags
            <code>@clave@</code> (ej. <code>Abono período @periodo@</code>)
            y condicionales
            <code>&#123;&#123;#si dominio&#125;&#125;…&#123;&#123;/si&#125;&#125;</code>
            o
            <code>&#123;&#123;#si dominio=AB123CD&#125;&#125;…&#123;&#123;/si&#125;&#125;</code>.
            Al facturar se completan por modal, abono o tags de sistema.
        </small>
        <div class="mt-1">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="cv-insertar-tag-descripcion" title="Inserta @clave@ en la descripción">
                Insertar tag
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="cv-detectar-tags-plantilla" title="Agrega a la grilla los @tags@ de la descripción">
                Detectar tags de la plantilla
            </button>
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="codigo_gtin" class="col-lg-3 control-label text-right pr-2">Código GTIN</label>
    <div class="col-lg-2">
        <input type="text" name="codigo_gtin" id="codigo_gtin" class="form-control" maxlength="13"
            value="{{ old('codigo_gtin', $data->codigo_gtin ?? '') }}" inputmode="numeric">
        <small class="form-text text-muted">13 dígitos con dígito verificador GS1. Vacío solo si no se usa en WSMTXCA.</small>
    </div>
    <label for="unidades_mtx" class="col-lg-2 control-label text-right pr-2">Unidades MTX</label>
    <div class="col-lg-1">
        <input type="number" name="unidades_mtx" id="unidades_mtx" class="form-control" min="1" max="999"
            value="{{ old('unidades_mtx', $data->unidades_mtx ?? 1) }}" required>
    </div>
</div>
<div class="form-group row">
    <label for="impuesto_id" class="col-lg-3 control-label text-right pr-2">Alícuota IVA</label>
    <div class="col-lg-4">
        <select name="impuesto_id" id="impuesto_id" class="form-control" data-fouc>
            <option value="">-- Sin alícuota --</option>
            @foreach ($impuesto_query ?? [] as $impuesto)
                <option value="{{ $impuesto->id }}" {{ (int) old('impuesto_id', $data->impuesto_id ?? 0) === (int) $impuesto->id ? 'selected' : '' }}>
                    {{ $impuesto->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="unidadmedida_id" class="col-lg-3 control-label text-right pr-2">Unidad de medida</label>
    <div class="col-lg-4">
        <select name="unidadmedida_id" id="unidadmedida_id" class="form-control" data-fouc>
            <option value="">-- Unidad --</option>
            @foreach ($unidadmedida_query ?? [] as $um)
                <option value="{{ $um->id }}" {{ (int) old('unidadmedida_id', $data->unidadmedida_id ?? 0) === (int) $um->id ? 'selected' : '' }}>
                    {{ $um->codigo }} — {{ $um->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="activo" class="col-lg-3 control-label text-right pr-2">Estado</label>
    <div class="col-lg-8">
        <div class="custom-control custom-checkbox pt-2">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1"
                {{ old('activo', $data->activo ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="activo">Activo para el facturador de mostrador</label>
        </div>
        @if (($usoConcepto['emisiones'] ?? 0) > 0 || ($usoConcepto['tipos'] ?? 0) > 0)
            <small class="form-text text-warning">
                En uso:
                @if (($usoConcepto['emisiones'] ?? 0) > 0)
                    {{ $usoConcepto['emisiones'] }} línea(s) de factura
                @endif
                @if (($usoConcepto['tipos'] ?? 0) > 0)
                    {{ ($usoConcepto['emisiones'] ?? 0) > 0 ? ' y ' : '' }}{{ $usoConcepto['tipos'] }} tipo(s) de transacción
                @endif
                . No se puede inactivar ni borrar.
            </small>
        @endif
    </div>
</div>
@include('ventas.concepto_venta.form2')
@include('ventas.concepto_venta.form4')
