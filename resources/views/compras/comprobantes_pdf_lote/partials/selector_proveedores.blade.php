@php
    $proveedoresIniciales = $proveedoresIniciales ?? [];
    $idsIniciales = collect($proveedoresIniciales)->pluck('id')->filter()->implode(',');
@endphp

<div class="form-group row mb-3" id="bloque-proveedores-pdf-lote">
    <label class="col-lg-2 control-label text-right pr-2 requerido">Proveedores</label>
    <div class="col-lg-9">
        <input type="hidden" name="proveedor_ids" id="proveedor_ids" value="{{ $idsIniciales }}">
        <p class="small text-muted mb-2">
            Código y <kbd>Enter</kbd> para agregar. <kbd>F1</kbd> o la lupa abre la consulta de proveedores.
            Puede cargar más de uno.
        </p>
        <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 6px;">
            <button type="button" class="btn btn-outline-secondary btn-sm consultaproveedor-pdf-lote" title="Buscar proveedor (F1)">
                <i class="fa fa-search"></i>
            </button>
            <input type="text"
                class="form-control form-control-sm"
                id="codigoproveedor_pdf_lote"
                placeholder="Código"
                autocomplete="off"
                style="max-width: 110px;">
            <input type="text"
                class="form-control form-control-sm flex-grow-1"
                id="nombreproveedor_pdf_lote"
                placeholder="Nombre del proveedor"
                readonly>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-proveedor-pdf-lote">
                <i class="fa fa-plus"></i> Agregar
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 bg-white">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width: 100px;">Código</th>
                        <th>Proveedor</th>
                        <th style="width: 70px;" class="text-center"></th>
                    </tr>
                </thead>
                <tbody id="tbody-proveedores-pdf-lote">
                    @foreach ($proveedoresIniciales as $prov)
                        <tr data-id="{{ $prov['id'] ?? '' }}">
                            <td>{{ $prov['codigo'] ?? '' }}</td>
                            <td>{{ $prov['nombre'] ?? '' }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-danger btn-xs btn-quitar-proveedor-pdf-lote" title="Quitar">
                                    <i class="fa fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="small text-muted mb-0 mt-2" id="aviso-proveedores-pdf-lote">
            @if ($proveedoresIniciales === [])
                Todavía no agregó proveedores.
            @else
                {{ count($proveedoresIniciales) }} proveedor(es) en la lista.
            @endif
        </p>
    </div>
</div>
