@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $empleado = $sancion->empleado;
    $empresaNombre = optional(optional($empleado)->empresa)->nombre ?? config('app.empresa');
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([(object) ['nombreempresa' => $empresaNombre]]));
    $plazo = (int) (optional($sancion->tipo)->plazo_descargo_dias ?? 2);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<title>Notificación de sanción</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11px; color: #17202A; }
		h1 { font-size: 16px; margin: 8px 0; }
		.box { border: 1px solid #cccccc; padding: 8px; margin: 10px 0; }
		.firmas { margin-top: 40px; width: 100%; }
		.firmas td { width: 50%; text-align: center; padding-top: 40px; }
		.muted { color: #555; font-size: 9px; }
	</style>
</head>
<body>
	@foreach ($logos as $logo)
		<img src="{{ $logo['uri'] }}" alt="" style="max-height: 56px;">
	@endforeach
	<h1>Notificación de sanción disciplinaria</h1>
	<p class="muted">Generado {{ date('d/m/Y H:i') }} · {{ $empresaNombre }}</p>
	<p>
		Señor/a <strong>{{ optional($empleado)->nombre }}</strong>
		(legajo {{ optional($empleado)->legajo }})
	</p>
	<p>
		Por la presente se le notifica la siguiente medida disciplinaria, en los términos del art. 67 de la LCT,
		con indicación de causa y derecho a formular descargo dentro de {{ $plazo }} día(s).
	</p>
	<div class="box">
		<p><strong>Tipo:</strong> {{ optional($sancion->tipo)->nombre }}</p>
		<p><strong>Motivo:</strong> {{ optional($sancion->motivo)->nombre }}</p>
		<p><strong>Fecha del hecho:</strong> {{ optional($sancion->fecha_hecho)->format('d/m/Y') }}</p>
		@if ($sancion->fecha_desde)
			<p><strong>Período:</strong> {{ optional($sancion->fecha_desde)->format('d/m/Y') }} al {{ optional($sancion->fecha_hasta)->format('d/m/Y') }} ({{ $sancion->cant_dias }} días)</p>
		@elseif ($sancion->cant_dias)
			<p><strong>Días:</strong> {{ $sancion->cant_dias }}</p>
		@endif
		@if ((float) $sancion->importe_perdida > 0)
			<p><strong>Importe no cobrado:</strong> $ {{ number_format((float) $sancion->importe_perdida, 2, ',', '.') }}</p>
		@endif
		<p><strong>Causa / comentario:</strong></p>
		<p>{{ $sancion->comentario }}</p>
		@if (optional($sancion->tipo)->plantilla_notificacion)
			<p>{{ $sancion->tipo->plantilla_notificacion }}</p>
		@endif
	</div>
	<p>Queda a su disposición el expediente para formular descargo por escrito.</p>
	<table class="firmas">
		<tr>
			<td>________________________<br>Empleador / RRHH<br>Fecha notificación: {{ optional($sancion->fecha_notificacion)->format('d/m/Y') }}</td>
			<td>________________________<br>Empleado (recepción)<br>Fecha recepción: {{ optional($sancion->fecha_recepcion)->format('d/m/Y') }}</td>
		</tr>
	</table>
</body>
</html>
