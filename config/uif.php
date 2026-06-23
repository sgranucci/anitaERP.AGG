<?php
// Constantes de configuracion modulo uif

return [
	"LIMITE_INFORME_UIF" => 4700000,

    /** Razón social fallback si el premio no tiene sala/empresa. */
	'INFORME_RAZON_SOCIAL' => env('UIF_INFORME_RAZON_SOCIAL', 'Argentina Gaming Group S.A.'),

	/** ID de pais_uif para Argentina (tipo documento en XML). */
	'PAIS_ARGENTINA_ID' => (int) env('UIF_PAIS_ARGENTINA_ID', 5),

	/** Carpeta relativa en storage/app (escritura php-fpm / www-data). XML UIF por empresa/periodo. */
	'EXPORTACION_XML_PATH' => env('UIF_EXPORTACION_XML_PATH', 'tmp/exportacion_uif'),

	/** Empresa_id por código sala para conciliación Wigos (BSA/KSA/RSA). */
	'conciliacion_wigos' => [
		'empresas' => [
			'BSA' => 1,
			'KSA' => 2,
			'RSA' => 3,
		],
	],

	/**
	 * Fotos de DNI (montaje legacy). Las altas/edición graban SIEMPRE aquí salvo
	 * FOTOS_CLIENTES_PERMITIR_FALLBACK_ESCRITURA=true (solo emergencia / desarrollo).
	 */
	'FOTOS_CLIENTES_PATH' => env('UIF_FOTOS_CLIENTES_PATH', '/scan/tesoreria/fotos_clientes'),

	/** Solo lectura de fotos guardadas en disco local durante pruebas; no se usa para grabar por defecto. */
	'FOTOS_CLIENTES_PATH_FALLBACK' => env('UIF_FOTOS_CLIENTES_PATH_FALLBACK', storage_path('app/uif/fotos_clientes')),

	/** false = obligatorio escribir en FOTOS_CLIENTES_PATH (/scan). true = si /scan falla, usa fallback local. */
	'FOTOS_CLIENTES_PERMITIR_FALLBACK_ESCRITURA' => env('UIF_FOTOS_CLIENTES_PERMITIR_FALLBACK_ESCRITURA', false),

	/*
	 * Importación de adjuntos desde Anita al sincronizar clientes UIF.
	 * - mount: directorio **padre** del árbol de archivos UIF en Anita (debe contener las
	 *   carpetas `clientes/` y `premios/`). No usar `.../archivos/clientes` como mount:
	 *   el código concatena `mount/clientes/...` y `mount/premios/...` (ver AnitaUifArchivosSync).
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