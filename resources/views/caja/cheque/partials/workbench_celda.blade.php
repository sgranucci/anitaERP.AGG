@switch ($key)
    @case('id')
        <td>
            <a href="{{ route('editar_cheque', ['id' => $data->id] + $retornoListadoQuery) }}" class="text-primary" target="_blank" rel="noopener">{{ $data->id }}</a>
        </td>
        @break
    @case('numerocheque')
        <td>{{ $data->numerocheque }}</td>
        @break
    @case('nro_interno_anita')
        <td>{{ $data->nro_interno_anita }}</td>
        @break
    @case('origen')
        <td>
            @if (($data->origen ?? '') === 'E')
                <span class="badge badge-primary">{{ $origenLabel['nombre'] ?? 'Emitido' }}</span>
            @else
                <span class="badge badge-info">{{ $origenLabel['nombre'] ?? 'Recibido' }}</span>
            @endif
        </td>
        @break
    @case('tipo')
        <td>
            @if (strtoupper(trim((string) ($data->negociable ?? ''))) === 'E')
                <span class="badge badge-warning">e-cheq</span>
            @else
                <span class="badge badge-secondary">Físico</span>
            @endif
        </td>
        @break
    @case('estado')
        <td>
            {{ $estadoLabel['nombre'] ?? $data->estado }}
            @if ($enCartera)
                <span class="badge badge-success">Cartera</span>
            @endif
            @if ($estaCaucionado)
                <span class="badge badge-warning" title="Caución {{ $data->nro_caucion }}">Cauc.</span>
            @endif
            @if (! empty($data->fecha_deposito))
                <span class="badge badge-secondary">Dep</span>
            @endif
            @if (! empty($data->fecha_acreditacion))
                <span class="badge badge-success">Acr</span>
            @endif
            @if (! empty($data->venta_nd_id))
                <a href="{{ route('lista_una_factura', ['id' => $data->venta_nd_id]) }}"
                   class="badge badge-danger text-white"
                   target="_blank"
                   rel="noopener"
                   title="Ver ND">ND</a>
            @endif
        </td>
        @break
    @case('fechaemision')
        <td>{{ \App\Support\Caja\ChequeDepositoComprobanteSupport::fechaDmy($data->fechaemision) }}</td>
        @break
    @case('fechapago')
        <td>{{ \App\Support\Caja\ChequeDepositoComprobanteSupport::fechaDmy($data->fechapago) }}</td>
        @break
    @case('banco_cta')
        <td>
            @if (($data->origen ?? '') === 'E')
                {{ $data->cuentacajas->nombre ?? '' }}
            @else
                {{ $data->bancos->nombre ?? '' }}
            @endif
        </td>
        @break
    @case('empresa')
        <td>{{ $data->empresas->nombre ?? '' }}</td>
        @break
    @case('cliente')
        <td class="small">{{ $data->clientes->nombre ?? '' }}</td>
        @break
    @case('monto')
        <td class="text-right">{{ number_format((float) $data->monto, 2, ',', '.') }}</td>
        @break
    @case('moneda')
        <td class="text-center small">{{ $data->monedas->abreviatura ?? '' }}</td>
        @break
    @case('beneficiario')
        <td class="small">{{ \Illuminate\Support\Str::limit($data->entregado ?? $data->anombrede, 28) }}</td>
        @break
@endswitch
