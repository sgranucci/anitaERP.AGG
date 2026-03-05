<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Trade Nro. {{$datosTrade['idTrade']}}</title>
</head>
<body>
    @if ($datosTrade['precioCierre1'] == 0)
        <p>Hola! Se ha cambiado a posición activa.</p>
        <p>Estos son los datos:</p>
    @else
        <p>Se ha cerrado la posición.</p>
        <p>Estos son los resultados</p>
    @endif
    <ul>
        <li>Fecha: {{ $datosTrade['fecha'] }}</li>
        <li>Dirección: {{ $datosTrade['direccion'] }}</li>
        <li>Nro.contratos: {{ $datosTrade['numeroContratos'] }}</li>
        <li>Hora Entrada: {{ $datosTrade['desdeHora'] }}</li>
        <li>Valor Entrada: {{ $datosTrade['valorEntrada'] }}</li>
        <li>Stop Loss: {{ $datosTrade['stopLoss'] }}</li>
        <li>Target 1: {{ $datosTrade['t1'] }}</li>
        <li>Target 2: {{ $datosTrade['t2'] }}</li>
        <li>Target 3: {{ $datosTrade['t3'] }}</li>
        <li>Target 4: {{ $datosTrade['t4'] }}</li>
        <li>RRR: {{ $datosTrade['rrr'] }}</li>
        <li>Swing Bars: {{ $datosTrade['swingBars'] }}</li>
        <li>Contra Swing Bars: {{ $datosTrade['contraSwingBars'] }}</li>
        <li>RV: {{ $datosTrade['rv'] }}</li>
        <li>Retroceso: {{ $datosTrade['retroceso'] }}</li>
        <li>Retorno (en ticks): {{ $datosTrade['retornoTicks'] }}</li>

        @if ($datosTrade['precioCierre1'] != 0)
            <li>Precio de Cierre 1: {{ $datosTrade['precioCierre1'] }}</li>
            <li>Hora Out 1: {{ $datosTrade['horaCierre1'] }}</li>
            <li>Precio de Cierre 2: {{ $datosTrade['precioCierre2'] }}</li>
            <li>Hora Out 2: {{ $datosTrade['horaCierre2'] }}</li>
            <li>Precio de Cierre 3: {{ $datosTrade['precioCierre3'] }}</li>
            <li>Hora Out 3: {{ $datosTrade['horaCierre3'] }}</li>
            <li>Precio de Cierre 4: {{ $datosTrade['precioCierre4'] }}</li>
            <li>Hora Out 4: {{ $datosTrade['horaCierre4'] }}</li>
            <li></li>

            <li>Total ticks: {{ $datosTrade['totalTicks'] }}</li>
            <li>P&L en $: {{ $datosTrade['plPesos'] }}</li>
            <li>MPC: {{ $datosTrade['mpc'] }}</li>
            <li>MPF: {{ $datosTrade['mpf'] }}</li>
        @endif
    </ul>
</body>
</html>