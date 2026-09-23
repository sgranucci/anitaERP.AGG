@php
    $detalleClienteUifRestringido = $detalleClienteUifRestringido ?? esSoloVisualizacionClienteUif();
    $suffixConsultaPremios = $suffixConsultaPremios ?? '';
    $indice = $indice ?? 1;
@endphp
<tr class="item-premio">
    <td>
        <input type="hidden" class="form-control iipremio" readonly value="{{ $indice }}" />
        <input type="hidden" class="form-control premio_id" value="{{ $premio->id ?? '' }}" />
        <input type="datetime" class="form-control fechaentrega" readonly
               value="{{ $premio->fechaentrega ? $premio->fechaentrega->format('d-m-Y H:i:s') : '' }}" />
    </td>
    <td>
        <input type="text" class="form-control sala" readonly value="{{ $premio->salas->nombre ?? '' }}" />
    </td>
    <td>
        <input type="text" class="form-control detalle" readonly value="{{ $premio->juegos_uif->nombre ?? '' }}" />
    </td>
    <td>
        <input type="text" class="form-control numerotito" readonly value="{{ $premio->numerotito }}" />
    </td>
    <td>
        <input type="text" class="form-control montopremio" readonly style="text-align: right;"
               value="{{ number_format((float) ($premio->monto ?? 0), 2, ',', '.') }}" />
    </td>
    <td class="text-center align-middle premio-foto-preview">
        @include('uif.cliente_premio_uif.partials.foto_celda', [
            'foto' => $premio->foto ?? null,
            'premioId' => $premio->id ?? null,
        ])
    </td>
    <td>
        @if (can('editar-cliente-premio-uif', false) && ! $detalleClienteUifRestringido)
            <a href="{{ route('edita_cliente_premio_uif', ['id' => $premio->id]) }}?return_cliente_tab=3{{ $suffixConsultaPremios }}"
               class="btn-accion-tabla tooltipsC" title="Editar este registro">
                <i class="fa fa-edit"></i>
            </a>
        @endif
        @if (can('editar-cliente-premio-uif', false) && ! $detalleClienteUifRestringido)
            <a href="{{ route('lista_un_cliente_premio_uif', ['id' => $premio->id]) }}"
               class="btn-accion-tabla tooltipsC" title="Listar el premio">
                <i class="fa fa-print"></i>
            </a>
        @endif
        @if (can('borrar-cliente-premio-uif', false) && ! $detalleClienteUifRestringido)
            <button style="width: 7%;" type="button" title="Elimina el premio" class="btn-accion-tabla eliminar_premio tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        @endif
    </td>
</tr>
