@php
    $ayudaNumeradorChequeEmitido = $ayudaNumeradorChequeEmitido
        ?? 'El nro. sale del numerador Anita: cheque al d&iacute;a o diferido seg&uacute;n F. pago vs fecha del comprobante.';
@endphp
<p class="text-muted small mb-2">
    Emisi&oacute;n de cheques propios. Cuenta: c&oacute;digo + Enter · F1 o lupa.
    {!! $ayudaNumeradorChequeEmitido !!}
    Chequera: lupa o F1 (tipo, rango y estado).
    <strong>A la orden</strong> es el texto legal impreso (por defecto: No a la orden).
    <strong>F&iacute;sico / e-cheq</strong> sale de la chequera.
</p>
<div class="table-responsive">
<table class="table table-sm table-bordered" id="cheque-emitido-table">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th style="width:10%;">C&oacute;digo</th>
            <th style="width:12%;">Descripci&oacute;n</th>
            <th style="width:14%;">Chequera</th>
            <th style="width:8%;">Nro.</th>
            <th style="width:9%;">F. pago</th>
            <th style="width:12%;">Instrumento</th>
            <th style="width:14%;">A nombre de</th>
            <th style="width:5%;">Mon.</th>
            <th style="width:9%;">Monto</th>
            <th style="width:6%;">Cotiz.</th>
            <th style="width:2%;"></th>
        </tr>
    </thead>
    <tbody id="tbody-cheque-emitido-table">
        @foreach ($chequesEmitidos as $cheque)
            @include('caja.ingresoegreso.partials.fila_cheque_emitido', ['cheque' => $cheque])
        @endforeach
    </tbody>
</table>
</div>
@include('caja.ingresoegreso.template_cheque_emitido')
<button type="button" id="agrega_renglon_cheque_emitido" class="btn btn-danger btn-sm">+ Cheque emitido</button>
<div class="form-group row totales-por-moneda-cheque-emitido mt-2"></div>
