<table>
    <tr>
        <td colspan="11"><strong>{{ $titulo ?? 'Histórico COT ARBA' }}</strong></td>
    </tr>
    @if (!empty($subtitulo))
        <tr>
            <td colspan="11">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <td colspan="11">Generado {{ now()->format('d/m/Y H:i') }}</td>
    </tr>
    <tr></tr>
    <tr style="background-color:#85C1E9;font-weight:bold;">
        <td>Tipo</td>
        <td>Letra</td>
        <td>Suc.</td>
        <td>N remito</td>
        <td>Fecha remito</td>
        <td>Fecha envio</td>
        <td>Cliente</td>
        <td>N COT</td>
        <td>N unico</td>
        <td>Proc.</td>
        <td>Observacion ARBA</td>
    </tr>
    @foreach ($filas as $remito)
        @php
            $fechaEnvio = $remito->cotSesionEnvio->fecha_envio ?? null;
        @endphp
        <tr>
            <td>{{ $remito->tipo }}</td>
            <td>{{ $remito->letra }}</td>
            <td>{{ $remito->sucursal }}</td>
            <td>{{ $remito->numero_remito }}</td>
            <td>{{ optional($remito->fecha_remito)->format('d/m/Y') }}</td>
            <td>{{ optional($fechaEnvio)->format('d/m/Y H:i') }}</td>
            <td>{{ $remito->cliente_nombre ?: optional($remito->clientes)->nombre }}</td>
            <td>{{ $remito->cot }}</td>
            <td>{{ $remito->nro_unico }}</td>
            <td>{{ $remito->procesado }}</td>
            <td>{{ $remito->error }}</td>
        </tr>
    @endforeach
</table>
