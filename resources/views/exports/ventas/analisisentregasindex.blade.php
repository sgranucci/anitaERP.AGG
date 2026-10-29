@php
    $textoResumen = \App\Support\Ventas\AnalisisEntregasReporte::resumenTexto($resumen ?? []);
@endphp
<table>
    @if (! empty($hayFilaLogos))
        <tr>
            <td colspan="24" style="height:52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="24"><strong>Análisis de entregas y pendientes</strong></td>
    </tr>
    <tr>
        <td colspan="24">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="24">{{ $subtitulo }}</td>
    </tr>
    <tr>
        <td colspan="24">{{ $textoResumen }}</td>
    </tr>
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Nombre</th>
            <th>Artículo</th>
            <th>Descripción</th>
            <th>Tip</th>
            <th>Número</th>
            <th>F. pedido</th>
            <th>F. entrega</th>
            <th>Atraso</th>
            <th>Cant. uni</th>
            <th>Cant. kg</th>
            <th>UM</th>
            <th>Entregado</th>
            <th>Pendiente</th>
            <th>Mon</th>
            <th>P. unit.</th>
            <th>Virgen</th>
            <th>Fason</th>
            <th>E</th>
            <th>Mot. cierre</th>
            <th>Subtotal</th>
            <th>Descuentos</th>
            <th>Total</th>
            <th>Importe pend.</th>
        </tr>
    </thead>
    <tbody>
        @include('ventas.analisis_entregas.partials.filas', [
            'filas' => $filas,
            'para_excel' => true,
            'puede_ver_pedido' => false,
            'puede_ver_cliente' => false,
            'puede_ver_articulo' => false,
        ])
    </tbody>
</table>
