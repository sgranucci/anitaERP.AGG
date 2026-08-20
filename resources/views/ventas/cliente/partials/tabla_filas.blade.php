@php
    $esBierzo = $esBierzo ?? \App\Support\Configuracion\EntornoEmpresaSupport::esElBierzo();
    $retornoListadoQuery = $retornoListadoQuery
        ?? \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $colspan = 11;
@endphp
@forelse ($clientes as $data)
    @if ($data->estado == '1')
        <tr class="table-danger">
    @elseif ($data->estado == 'R')
        <tr class="table-warning">
    @else
        <tr>
    @endif
    @if ($esBierzo)
        <td><small>{{ $data->codigo }}</small></td>
    @else
        <td>{{ $data->id }}</td>
    @endif
    <td class="text-truncate" style="max-width: 160px;" title="{{ $data->nombre }}">{{ $data->nombre }}</td>
    <td class="text-truncate" style="max-width: 110px;" title="{{ trim(($data->cvendedor ?? '').($data->nombrevendedor ? ' - '.$data->nombrevendedor : '')) }}">
        <small>
            {{ $data->cvendedor ?? '' }}
            @if (!empty($data->nombrevendedor))
                -{{ $data->nombrevendedor }}
            @endif
        </small>
    </td>
    @if ($esBierzo)
        <td class="text-truncate" style="max-width: 110px;" title="{{ trim(($data->ctransporte ?? '').($data->nombretransporte ? '-'.$data->nombretransporte : '')) }}">
            <small>{{ $data->ctransporte }}-{{ $data->nombretransporte }}</small>
        </td>
    @endif
    <td><small>{{ $data->numerodocumento }}</small></td>
    <td class="text-truncate" style="max-width: 160px;" title="{{ $data->domicilio }}"><small>{{ $data->domicilio }}</small></td>
    <td class="text-truncate" style="max-width: 110px;" title="{{ $data->nombrelocalidad ?? '' }}"><small>{{ $data->nombrelocalidad ?? '' }}</small></td>
    <td class="text-truncate" style="max-width: 110px;" title="{{ $data->nombreprovincia ?? '' }}"><small>{{ $data->nombreprovincia ?? '' }}</small></td>
    @if (! $esBierzo)
        <td><small>{{ $data->codigo }}</small></td>
    @endif
    <td class="text-center p-1">
        @if ($data->estado === '1')
            <span class="badge badge-danger" title="Suspendido">S</span>
        @elseif ($data->estado === 'R')
            <span class="badge badge-warning text-dark" title="Regularizado: facturaci&oacute;n permitida pese a ARCA">R</span>
        @endif
    </td>
    <td class="text-center">
        @if (!empty($data->facturas_apocrifas))
            <span class="badge badge-danger" title="Facturas apócrifas ARCA">Sí</span>
        @elseif (!empty($data->facturas_apocrifas_consulta_at))
            <span class="badge badge-success" title="Consultado {{ $data->facturas_apocrifas_consulta_at }}">No</span>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>
    <td>
        @if (can('editar-clientes', false))
            <a href="{{ route('editar_cliente', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                <i class="fa fa-edit"></i>
            </a>
        @endif
        @if (can('listar-cuentacorriente-cliente', false))
            <a href="{{ route('listar_cuentacorriente_cliente', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Cuenta Corriente">
                <i class="fa fa-folder-open"></i>
            </a>
        @endif
        @if (can('borrar-clientes', false))
            <form action="{{ route('eliminar_cliente', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                @csrf @method('delete')
                <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                    <i class="fa fa-times-circle text-danger"></i>
                </button>
            </form>
        @endif
    </td>
    </tr>
@empty
    <tr>
        <td colspan="{{ $colspan }}" class="text-muted text-center py-3">Sin resultados</td>
    </tr>
@endforelse
