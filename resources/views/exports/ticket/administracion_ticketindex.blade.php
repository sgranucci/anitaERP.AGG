@php
    $colspan = 14;
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}">
                <h2 style="margin: 0; font-size: 16pt; font-weight: bold;">{{ $titulo ?? 'Administración de tickets' }}</h2>
                @if (! empty($subtitulo))
                    <div style="font-size: 10pt; color: #444;">{{ $subtitulo }}</div>
                @endif
            </td>
        </tr>
    </tbody>
    @include('ticket.administracion_ticket.partials.tabla_datos', [
        'ticket' => $ticket,
        'para_pdf' => true,
        'mostrar_acciones' => false,
        'puede_ver_ticket' => false,
    ])
</table>
