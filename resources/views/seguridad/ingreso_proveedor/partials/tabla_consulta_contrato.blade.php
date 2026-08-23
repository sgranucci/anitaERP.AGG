<table class="table table-sm table-bordered table-hover mb-0">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>OC</th>
            <th>Proveedor</th>
            <th>Estado</th>
            <th>Vigencia</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($contratos as $oc)
            <tr>
                <td>{{ $oc->numeroordencompra }}</td>
                <td>{{ $oc->proveedores->nombre ?? '' }}</td>
                <td>{{ $oc->estadoordencompra }}</td>
                <td>
                    {{ optional($oc->contrato_vigencia_desde)->format('d/m/Y') }}
                    @if ($oc->contrato_vigencia_hasta)
                        — {{ $oc->contrato_vigencia_hasta->format('d/m/Y') }}
                    @endif
                </td>
                <td class="text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary js-elegir-contrato-ingreso"
                            data-id="{{ $oc->id }}"
                            data-numero="{{ $oc->numeroordencompra }}"
                            data-estado="{{ $oc->estadoordencompra }}"
                            data-proveedor-id="{{ $oc->proveedor_id }}"
                            data-proveedor-codigo="{{ $oc->proveedores->codigo ?? '' }}"
                            data-proveedor-nombre="{{ $oc->proveedores->nombre ?? '' }}">
                        Elegir
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-muted">No hay contratos activos para esos criterios.</td>
            </tr>
        @endforelse
    </tbody>
</table>
