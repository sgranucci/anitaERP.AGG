<p>Adjunto el listado de administración de tickets con el filtro y el alcance que estaban en pantalla.</p>
@if ($recorte)
<p>El archivo trae las primeras {{ number_format($filas, 0, ',', '.') }} filas del filtro.</p>
@else
<p>Filas: {{ number_format($filas, 0, ',', '.') }}.</p>
@endif
