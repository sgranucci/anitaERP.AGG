<p>Adjunto el listado de pagos a proveedores con el filtro que estaba en pantalla.</p>
@if ($recorte)
<p>El archivo trae las primeras {{ number_format($filas, 0, ',', '.') }} filas del filtro.</p>
@else
<p>Filas: {{ number_format($filas, 0, ',', '.') }}.</p>
@endif
