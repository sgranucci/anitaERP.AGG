<?php
// Constantes de configuracion modulo uif

return [
	"LIMITE_INFORME_UIF" => 4700000,

	/*
	 * Importación de adjuntos desde Anita al sincronizar clientes UIF.
	 * - mount: ruta local donde está montado (o copiado) el árbol de archivos de Anita
	 *   (por defecto la misma ruta servidor indicada en Cliente_UifRepository).
	 * - tabla_* / campos_*: si la tabla Informix existe y los nombres coinciden, se listan adjuntos
	 *   por API; si no, solo se usan archivos hallados bajo `mount` con la convención de carpetas.
	 */
	'anita_uif_archivos' => [
		'mount' => env('ANITA_UIF_ARCHIVOS_MOUNT', '/usr2/www/htdocs/uif/archivos'),
		/* Fotos DNI en Anita (distinto del árbol de adjuntos generales). */
		'dni_mount' => env('ANITA_UIF_DNI_MOUNT', '/usr2/www/htdocs/dni_uif'),
		'sistema' => env('ANITA_UIF_ARCHIVOS_SISTEMA', 'base_admin'),
		'tabla_cliente' => env('ANITA_UIF_ARCHIVOS_TABLA_CLIENTE', ''),
		'campos_cliente' => env('ANITA_UIF_ARCHIVOS_CAMPOS_CLIENTE', 'inroclienteid, inrolinea, carchivo'),
		'tabla_premio' => env('ANITA_UIF_ARCHIVOS_TABLA_PREMIO', ''),
		'campos_premio' => env('ANITA_UIF_ARCHIVOS_CAMPOS_PREMIO', 'inropremioid, inroclienteid, inrolinea, carchivo'),
	],
];