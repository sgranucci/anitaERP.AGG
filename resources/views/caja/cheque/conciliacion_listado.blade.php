@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $coleccionLogos = collect($filas ?? [])->map(function ($f) {
        return (object) ['nombreempresa' => $f['nombreempresa'] ?? $f['empresa'] ?? ''];
    });
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogos);
    $totalFilas = is_countable($filas) ? count($filas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Conciliación depósitos CHT</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
		table.data { border-collapse: collapse; width: 100%; }
		table.data td, table.data th { border: 1px solid #cccccc; padding: 4px; }
		table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
		table.data thead tr { background-color: #85C1E9; }
		table.data th { font-weight: bold; color: #17202A; }
		.text-right { text-align: right; }
	</style>
</head>
<body>
<table class="listado-header" style="width:100%;margin-bottom:8px;">
    <tr>
        <td>
            @foreach ($logosCabecera as $logo)
                @if (!empty($logo['uri']))
                    <img src="{{ $logo['uri'] }}" height="36" style="margin-right:8px;" />
                @endif
            @endforeach
        </td>
        <td>
            <strong>Conciliación depósitos CHT</strong><br>
            Generado {{ date('d/m/Y H:i') }} — {{ $totalFilas }} registros
            @if (!empty($subtitulo))<br>{{ $subtitulo }}@endif
        </td>
    </tr>
</table>
<table class="data">
    <thead>
        <tr>
            <th>ID</th>
            <th>Número</th>
            <th>Int.</th>
            <th>Depósito</th>
            <th>Acreditación</th>
            <th>Boleta</th>
            <th>Monto</th>
            <th>Mon</th>
            <th>Banco</th>
            <th>Cliente</th>
            <th>Cuenta</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $f)
            <tr>
                <td>{{ $f['id'] }}</td>
                <td>{{ $f['numerocheque'] }}</td>
                <td>{{ $f['nro_interno_anita'] }}</td>
                <td>{{ $f['fecha_deposito'] }}</td>
                <td>{{ $f['fecha_acreditacion'] }}</td>
                <td>{{ $f['nro_boleta'] }}</td>
                <td class="text-right">{{ number_format((float) $f['monto'], 2, ',', '.') }}</td>
                <td>{{ $f['moneda'] }}</td>
                <td>{{ $f['banco'] }}</td>
                <td>{{ $f['cliente'] }}</td>
                <td>{{ $f['cuenta'] }}</td>
                <td>{{ $f['estado_label'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
