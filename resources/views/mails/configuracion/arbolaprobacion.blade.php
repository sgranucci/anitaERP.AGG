<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Aprobación {{$datosComprobante->nombre}}</title>
</head>
<body>
    @if ($tipoArbol == 'Ordenes de venta')
        <p>Hola! Tiene una Orden de venta para aprobación</p>
    @else
        <p>Hola! Tiene una Requisición para aprobación</p>
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
                <a href={{$linkAprobacion}} target="_blank">
                    Autorizar la Orden de Venta    
                </a>
            </div>
            <br>
            <label for="Autorizar">Rechazar:</label>
            <div>
                <a href={{$linkRechazo}} target="_blank">
                    Rechazar la Orden de Venta
                </a>
            </div>
        </ul>
        <br>
        <label for="Visualizar">Visualizar:</label>
        <div>
            <a href={{$linkVisualizar}} target="_blank">
                Visualizar la Orden de Venta
            </a>
        </div>
    @endif
</body>
</html>