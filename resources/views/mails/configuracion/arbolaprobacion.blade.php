<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Aprobación @if(isset($datosComprobante->numeroordenventa)) OV {{ $datosComprobante->numeroordenventa }} @elseif(isset($datosComprobante->numerorequisicion)) REQ {{ $datosComprobante->numerorequisicion }} @elseif(isset($datosComprobante->numeroordencompra)) OC {{ $datosComprobante->numeroordencompra }} @endif</title>
</head>
<body>
    @if ($tipoArbol == 'Ordenes de venta')
        <p>Hola! Tiene una Orden de venta para aprobación</p>
    @elseif ($tipoArbol == 'Ordenes de compra')
        <p>Hola! Tiene una Orden de compra para aprobación</p>
        @php $mx = $mailExtras ?? []; @endphp
        @if (!empty($mx['estado_tras_aprobar']))
            <p>Al <strong>aprobar este paso</strong>, la orden de compra quedará en estado <strong>{{ $mx['estado_tras_aprobar'] }}</strong>.</p>
        @else
            <p>En este nivel del árbol <strong>no está definido</strong> un estado destino al aprobar; solo avanzará la aprobación del flujo.</p>
        @endif
    @else
        <p>Hola! Tiene una Requisición para aprobación</p>
        @php $mx = $mailExtras ?? []; @endphp
        @if (!empty($mx['estado_tras_aprobar']))
            <p>Al <strong>aprobar este paso</strong>, la requisición quedará en estado <strong>{{ $mx['estado_tras_aprobar'] }}</strong>.</p>
        @else
            <p>En este nivel del árbol <strong>no está definido</strong> un estado destino para la requisición al aprobar; solo avanzará la aprobación del flujo.</p>
        @endif
    @endif

    <p>Estos son los datos:</p>
    @if ($tipoArbol == 'Ordenes de venta')
        <ul>
            <li>Tratamiento: {{ $datosComprobante->tratamiento }} </li>
            <li>Empresa: {{ $datosComprobante->empresas->nombre ?? '' }} </li>
            <li>Número: {{ $datosComprobante->numeroordenventa }} </li>
            <li>Fecha de la órden: {{ date("d/m/Y", strtotime($datosComprobante->fecha ?? '')) }} </li>
            <li>Monto: {{$datosComprobante->monedas->abreviatura}} {{ number_format($datosComprobante->monto,2) }}</li>
            <li>Forma de pago: {{ $datosComprobante->formapagos->nombre }}</li>
            <li>Cliente: {{$datosComprobante->nombrecliente}}</li>
            <li>Comentarios: {{$datosComprobante->comentario}}</li>
            <li>Detalle a Facturar: {{$datosComprobante->detalle}}</li>
            <br><br>
            <label for="Autorizar">Autorizar:</label>
            <div>
                <p><a href="{{ $linkAprobacion }}">{{ $linkAprobacion }}</a></p>
            </div>
            <br>
            <label for="Autorizar">Rechazar:</label>
            <div>
                <p><a href="{{ $linkRechazo }}">{{ $linkRechazo }}</a></p>
            </div>
        </ul>
        <br>
        <label for="Visualizar">Visualizar:</label>
        <div>
            <p><a href="{{ $linkVisualizar }}">{{ $linkVisualizar }}</a></p>
        </div>
    @elseif ($tipoArbol == 'Ordenes de compra')
        @php $mx = $mailExtras ?? []; @endphp
        <p style="font-size:14px;color:#444;">Al abrir los enlaces de <strong>Autorizar</strong> o <strong>Rechazar</strong> verá el detalle y podrá cargar observaciones antes de confirmar.</p>
        <ul>
            <li>Empresa: {{ $datosComprobante->empresas->nombre ?? '' }}</li>
            <li>Número OC: {{ $datosComprobante->numeroordencompra }}</li>
            <li>Fecha: {{ date('d/m/Y', strtotime($datosComprobante->fecha ?? '')) }}</li>
            <li>Monto total ítems: {{ $mx['moneda_abrev_items'] ?? '—' }} {{ number_format($mx['monto_items'] ?? 0, 2) }}</li>
            <li>Proveedor: {{ $datosComprobante->proveedores->nombre ?? '—' }}</li>
            <li>Comentarios: {{ $datosComprobante->comentario }}</li>
            <li>Detalle: {{ $datosComprobante->detalle }}</li>
        </ul>
        <br>
        <label for="Autorizar">Autorizar:</label>
        <div><p><a href="{{ $linkAprobacion }}">{{ $linkAprobacion }}</a></p></div>
        <br>
        <label for="Rechazar">Rechazar:</label>
        <div><p><a href="{{ $linkRechazo }}">{{ $linkRechazo }}</a></p></div>
        <br>
        <label for="Visualizar">Visualizar:</label>
        <div><p><a href="{{ $linkVisualizar }}">{{ $linkVisualizar }}</a></p></div>
    @elseif ($tipoArbol == 'Requisiciones')
        @php $mx = $mailExtras ?? []; @endphp
        <p style="font-size:14px;color:#444;">Al abrir los enlaces de <strong>Autorizar</strong> o <strong>Rechazar</strong> verá el detalle en una pantalla adaptable a celular y podrá cargar observaciones antes de confirmar.</p>
        <ul>
            <li>Empresa: {{ $datosComprobante->empresas->nombre ?? '' }}</li>
            <li>Número: {{ $datosComprobante->numerorequisicion }}</li>
            <li>Fecha: {{ date('d/m/Y', strtotime($datosComprobante->fecha ?? '')) }}</li>
            <li>Monto total ítems (Σ cantidad × precio, moneda del primer ítem, cotización del día según fecha de la requisición): {{ $mx['moneda_abrev_items'] ?? '—' }} {{ number_format($mx['monto_items'] ?? 0, 2) }}</li>
            <li>Proveedor sugerido: {{ $datosComprobante->proveedores->nombre ?? '—' }}</li>
            <li>Comentarios: {{ $datosComprobante->comentario }}</li>
            <li>Detalle: {{ $datosComprobante->detalle }}</li>
        </ul>
        <br>
        <label for="Autorizar">Autorizar:</label>
        <div><p><a href="{{ $linkAprobacion }}">{{ $linkAprobacion }}</a></p></div>
        <br>
        <label for="Rechazar">Rechazar:</label>
        <div><p><a href="{{ $linkRechazo }}">{{ $linkRechazo }}</a></p></div>
        <br>
        <label for="Visualizar">Visualizar:</label>
        <div><p><a href="{{ $linkVisualizar }}">{{ $linkVisualizar }}</a></p></div>
    @endif
</body>
</html>