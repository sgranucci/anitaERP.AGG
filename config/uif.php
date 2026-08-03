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
	 * Fotos de DNI / pago_* (file server /scan). Altas graban aquí salvo
	 * FOTOS_CLIENTES_PERMITIR_FALLBACK_ESCRITURA=true (solo emergencia).
	 */
	'FOTOS_CLIENTES_PATH' => env('UIF_FOTOS_CLIENTES_PATH', '/scan/tesoreria/fotos_clientes'),

	/** Solo lectura de fotos guardadas en disco local durante pruebas; no se usa para grabar por defecto. */
	'FOTOS_CLIENTES_PATH_FALLBACK' => env('UIF_FOTOS_CLIENTES_PATH_FALLBACK', storage_path('app/uif/fotos_clientes')),

	/** false = obligatorio escribir en FOTOS_CLIENTES_PATH (/scan). true = si /scan falla, usa fallback local. */
	'FOTOS_CLIENTES_PERMITIR_FALLBACK_ESCRITURA' => env('UIF_FOTOS_CLIENTES_PERMITIR_FALLBACK_ESCRITURA', false),

	/** Fotos de premio (pago_*). Por defecto el mismo directorio de tesorería. */
	'FOTOS_PREMIOS_PATH' => env('UIF_FOTOS_PREMIOS_PATH', '/scan/tesoreria/fotos_clientes'),

	/** Adjuntos planos (convención Anita {id}-{nombre}). */
	'ARCHIVOS_CLIENTES_PATH' => env('UIF_ARCHIVOS_CLIENTES_PATH', '/scan/uif/archivos/clientes'),
	'ARCHIVOS_PREMIOS_PATH' => env('UIF_ARCHIVOS_PREMIOS_PATH', '/scan/uif/archivos/premios'),

	/**
	 * Sync desde Anita: false = solo registra en BD (archivos ya están en /scan).
	 * true = copia a public/storage (legacy; engorda /var del ERP).
	 */
	'SYNC_COPIAR_ARCHIVOS' => filter_var(env('UIF_SYNC_COPIAR_ARCHIVOS', false), FILTER_VALIDATE_BOOLEAN),

	/*
	 * Importación de adjuntos desde Anita al sincronizar clientes UIF.
	 * - mount: directorio **padre** del árbol (debe contener `clientes/` y `premios/`).
	 *   En anitanextgen usar /scan/uif/archivos (réplica del file server).
	 * - tabla_* / campos_*: si la tabla Informix existe, listan adjuntos por API;
	 *   si no, solo filesystem bajo `mount`.
	 */
	'anita_uif_archivos' => [
		'mount' => env('ANITA_UIF_ARCHIVOS_MOUNT', '/scan/uif/archivos'),
		/* DNI PDF (Anita: /scan/tesoreria/dni_uif/{nro}.pdf). */
		'dni_mount' => env('ANITA_UIF_DNI_MOUNT', '/scan/tesoreria/dni_uif'),
		'sistema' => env('ANITA_UIF_ARCHIVOS_SISTEMA', 'base_admin'),
		'tabla_cliente' => env('ANITA_UIF_ARCHIVOS_TABLA_CLIENTE', ''),
		'campos_cliente' => env('ANITA_UIF_ARCHIVOS_CAMPOS_CLIENTE', 'inroclienteid, inrolinea, carchivo'),
		'tabla_premio' => env('ANITA_UIF_ARCHIVOS_TABLA_PREMIO', ''),
		'campos_premio' => env('ANITA_UIF_ARCHIVOS_CAMPOS_PREMIO', 'inropremioid, inroclienteid, inrolinea, carchivo'),
	],

	/**
	 * Sync bulk multi-empresa: bridge + sala ERP + carpetas /scan (pago_* separados por origen).
	 * DNI PDF comparte /scan/tesoreria/dni_uif (nombre = número documento).
	 */
	'anita_origenes' => [
		'biyemas' => [
			'servidor' => env('ANITA_IP', '10.20.30.200:8080'),
			'empresa_id' => (int) env('UIF_EMPRESA_ID_BIYEMAS', 1),
			'sala_id' => 1,
			'archivos_clientes' => '/scan/uif/archivos/clientes',
			'archivos_premios' => '/scan/uif/archivos/premios',
			'fotos_premios' => '/scan/tesoreria/fotos_clientes',
		],
		'kandiko' => [
			'servidor' => env('ANITA_IP_KANDIKO', '192.168.20.100:8080'),
			'empresa_id' => (int) env('UIF_EMPRESA_ID_KANDIKO', 2),
			'sala_id' => 2,
			'archivos_clientes' => '/scan/uif/archivos/clientes_KSA',
			'archivos_premios' => '/scan/uif/archivos/premios_KSA',
			'fotos_premios' => '/scan/tesoreria/fotos_clientes_KSA',
		],
		'rebisco' => [
			'servidor' => env('ANITA_IP_REBISCO', '192.168.40.100:8080'),
			'empresa_id' => (int) env('UIF_EMPRESA_ID_REBISCO', 3),
			'sala_id' => 3,
			'archivos_clientes' => '/scan/uif/archivos/clientes_RSA',
			'archivos_premios' => '/scan/uif/archivos/premios_RSA',
			'fotos_premios' => '/scan/tesoreria/fotos_clientes_RSA',
		],
	],
];