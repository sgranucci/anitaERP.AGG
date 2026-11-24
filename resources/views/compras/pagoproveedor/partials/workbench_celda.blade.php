@php
    use App\Support\Compras\PagoproveedorListadoColumnas;
    use App\Support\Listado\ListadoGrillaConfigSupport;

    $esIeOpp = $fila instanceof \App\Support\Compras\PagoproveedorListadoFila && $fila->esIeOpp();
    $cfg = $cfg ?? null;
    $alinea = is_array($cfg) ? ($cfg['alinea'] ?? 'izquierda') : match ($key) {
        'monto' => 'derecha',
        'mail' => 'centro',
        default => 'izquierda',
    };
    $cls = trim(ListadoGrillaConfigSupport::claseAlineacion($alinea).' lw-col');
    $titleTexto = PagoproveedorListadoColumnas::valorCelda($fila, $key);
@endphp
@switch ($key)
    @case ('fecha')
        @php
            $horaEmision = $fila instanceof \App\Support\Compras\PagoproveedorListadoFila
                ? $fila->horaEmision
                : null;
        @endphp
        <td class="{{ $cls }} text-nowrap pp-fecha-celda" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            <span class="pp-fecha-bloque">
                <span class="pp-fecha-dia">{{ optional($fila->fecha)->format('d/m/Y') }}</span>
                @if ($horaEmision)
                    <span class="pp-fecha-hora" title="Hora de emisión">{{ $horaEmision->format('H:i') }}</span>
                @endif
            </span>
        </td>
        @break
    @case ('op')
        <td class="{{ $cls }}" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            <span class="lw-celda-texto">
                {{ $fila->etiquetaComprobante() }}
                @if ($esIeOpp)
                    <span class="badge badge-secondary ml-1">IE</span>
                @endif
            </span>
        </td>
        @break
    @case ('cuentas')
        @php
            $cuentasCaja = $fila instanceof \App\Support\Compras\PagoproveedorListadoFila
                ? $fila->cuentasCajaLista()
                : [];
        @endphp
        <td class="{{ $cls }} pp-cuentas-celda" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            @if (count($cuentasCaja) > 0)
                <ul class="mb-0 pl-3 small">
                    @foreach ($cuentasCaja as $cuentaCaja)
                        <li title="{{ $cuentaCaja }}">
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
        </td>
        @break
    @case ('mail')
        <td class="{{ $cls }} text-center align-middle js-op-mail-celda"
            data-pagoproveedor-id="{{ $esIeOpp ? '' : $fila->id }}">
            @if (! $esIeOpp)
                @if ($fila->mailEnviado)
                    <i class="fa fa-envelope js-op-mail-icono" title="Enviado por correo" style="color:#1e8449;font-size:12px;opacity:.8"></i>
                @else
                    <i class="fa fa-envelope-o js-op-mail-icono" title="Sin enviar" style="color:#bfc9ca;font-size:12px"></i>
                @endif
            @endif
        </td>
        @break
    @case ('monto')
        <td class="{{ $cls }} text-nowrap" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            <span class="lw-celda-texto">{{ $titleTexto }}</span>
        </td>
        @break
    @default
        <td class="{{ $cls }}" @if ($titleTexto !== '') title="{{ $titleTexto }}" @endif>
            <span class="lw-celda-texto">{{ $titleTexto }}</span>
        </td>
@endswitch
