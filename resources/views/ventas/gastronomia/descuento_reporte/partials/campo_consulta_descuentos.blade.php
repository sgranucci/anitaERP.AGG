@php
    $descuentosIniciales = $descuentos_iniciales ?? [];
    $codigosIniciales = collect($descuentosIniciales)->pluck('codigo')->filter()->implode(',');
@endphp
<div class="form-group row mb-2" id="tm-descuento-reporte-campo">
    <label class="col-lg-2 control-label text-right pr-2 requerido" id="label-seleccion-descuento-reporte">Códigos de descuento</label>
    <div class="col-lg-8">
        <input type="hidden" name="codigos_descuento" id="codigos_descuento" value="{{ $codigosIniciales }}">
        <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 6px;">
            <button type="button" title="Consultar códigos de descuento gastronomía" class="btn btn-outline-secondary btn-sm consultadescuento-reporte">
                <i class="fa fa-search"></i>
            </button>
            <input type="text"
                class="form-control form-control-sm codigodescuento-reporte"
                id="codigodescuento_reporte"
                value=""
                placeholder="Cód. descuento"
                autocomplete="off"
                style="max-width: 120px;">
            <input type="text"
                class="form-control form-control-sm nombredescuento-reporte flex-grow-1"
                id="nombredescuento_reporte"
                value=""
                placeholder="Nombre del descuento"
                readonly>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-descuento-reporte" title="Agregar código a la lista">
                <i class="fa fa-plus"></i> Agregar
            </button>
        </div>
        <p class="text-muted small mb-2" id="ayuda-seleccion-descuento-reporte">
            Cargue los <strong>códigos de descuento de cabecera</strong> (maestro gastronomía) que desea incluir.
            Por cada código elegido el reporte lista los artículos vendidos con ese descuento en el período
            (un bloque o una columna según la presentación). Use la lupa, o ingrese el código y pulse Agregar.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" id="tabla-descuentos-seleccionados-reporte">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 100px;">Código</th>
                        <th>Descuento (cabecera)</th>
                        <th style="width: 70px;" class="text-center"></th>
                    </tr>
                </thead>
                <tbody id="tbody-descuentos-seleccionados-reporte">
                    @foreach ($descuentosIniciales as $desc)
                        <tr data-codigo="{{ $desc['codigo'] ?? '' }}">
                            <td>{{ $desc['codigo'] ?? '' }}</td>
                            <td>{{ $desc['nombre'] ?? '' }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-danger btn-xs btn-quitar-descuento-reporte" title="Quitar">
                                    <i class="fa fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (($descuentosIniciales ?? []) === [])
            <p class="text-muted small mb-0 mt-1" id="aviso-sin-descuentos-reporte">Sin códigos de descuento cargados.</p>
        @endif
    </div>
</div>
