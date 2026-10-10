@php
    $esIeOpp = $fila instanceof \App\Support\Compras\PagoproveedorListadoFila && $fila->esIeOpp();
    $alineacion = match ($key) {
        'monto' => 'text-right text-nowrap',
        'mail' => 'text-center align-middle js-op-mail-celda',
        default => '',
    };
@endphp
<td class="{{ $alineacion }}" @if ($key === 'mail') data-pagoproveedor-id="{{ $esIeOpp ? '' : $fila->id }}" @endif>
    @switch ($key)
        @case ('fecha')
            {{ optional($fila->fecha)->format('d/m/Y') }}
            @break
        @case ('op')
            {{ $fila->etiquetaComprobante() }}
            @if ($esIeOpp)
                <span class="badge badge-secondary ml-1">IE</span>
            @endif
            @break
        @case ('empresa')
            {{ $fila->empresas->nombre ?? '' }}
            @break
        @case ('proveedor')
            {{ $fila->proveedores->nombre ?? '' }}
            @break
        @case ('detalle')
            {{ $fila instanceof \App\Support\Compras\PagoproveedorListadoFila ? $fila->detalleIndicativo() : ($fila->detalle ?? '') }}
            @break
        @case ('cuentas')
            @php
                $cuentasCaja = $fila instanceof \App\Support\Compras\PagoproveedorListadoFila
                    ? $fila->cuentasCajaLista()
                    : [];
            @endphp
            @if (count($cuentasCaja) > 0)
                <ul class="mb-0 pl-3 small">
                    @foreach ($cuentasCaja as $cuentaCaja)
                        <li>
                            @if (str_contains($cuentaCaja, ' · CHP '))
                                @php
                                    [$ctaTxt, $nrosChp] = explode(' · CHP ', $cuentaCaja, 2);
                                @endphp
                                {{ $ctaTxt }}
                                <span class="badge badge-info ml-1" title="Cuenta de los cheques emitidos">CHP {{ $nrosChp }}</span>
                            @else
                                {{ $cuentaCaja }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @break
        @case ('monto')
            {{ number_format((float) $fila->monto, 2, ',', '.') }} {{ $fila->monedas->abreviatura ?? '' }}
            @break
        @case ('estado')
            {{ $fila->estado }}
            @break
        @case ('mail')
            @if (! $esIeOpp)
                @if ($fila->mailEnviado)
                    <i class="fa fa-envelope js-op-mail-icono" title="Enviado por correo" style="color:#1e8449;font-size:12px;opacity:.8"></i>
                @else
                    <i class="fa fa-envelope-o js-op-mail-icono" title="Sin enviar" style="color:#bfc9ca;font-size:12px"></i>
                @endif
            @endif
            @break
        @case ('id')
            {{ $fila->id }}
            @break
        @case ('origen')
            {{ $esIeOpp ? 'Ingresos y egresos' : 'Orden de pago' }}
            @break
        @case ('tipocomprobante')
            {{ $fila->etiquetaComprobante() }}
            @break
        @case ('moneda')
            {{ $fila->monedas->abreviatura ?? '' }}
            @break
    @endswitch
</td>
