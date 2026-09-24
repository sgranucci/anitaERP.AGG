<!DOCTYPE html>
<html>
	<title>Proveedores</title>
	<head>
		<style>
			table {
				font-family: arial, sans-serif;
				border-collapse: collapse;
				width: 100%;
			}
			td, th {
				border: 1px solid #dddddd;
				text-align: left;
				padding: 8px;
			}
			tr:nth-child(even) {
				background-color: #dddddd;
			}
		</style>
	</head>
	<body>
		<h2>Proveedores</h2>
		<table class="table table-striped table-bordered table-hover">
			@include('compras.proveedor.partials.tabla_listado_export', ['proveedores' => $proveedores])
		</table>
	</body>
</html>
