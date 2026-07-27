<?php

return [
    'habilitado' => env('COMPROBANTE_PROVEEDOR_PDF_IA_HABILITADO', false),

    /*
    | Driver: interno (OCR+heurísticas+Ollama en el ERP), http (API externa legacy), hybrid (interno y si falla http).
    */
    'driver' => env('COMPROBANTE_PROVEEDOR_PDF_IA_DRIVER', 'interno'),

    'api_url' => env('COMPROBANTE_PROVEEDOR_PDF_IA_API_URL', ''),
    'api_timeout' => (int) env('COMPROBANTE_PROVEEDOR_PDF_IA_API_TIMEOUT', 120),

    'log_channel' => env('COMPROBANTE_PROVEEDOR_PDF_IA_LOG_CHANNEL', env('PRECARGA_PROVEEDOR_API_LOG_CHANNEL', 'precarga_proveedor_api')),

    'ocr' => [
        'min_chars' => (int) env('COMPROBANTE_PROVEEDOR_PDF_IA_OCR_MIN_CHARS', 30),
    ],

    'ollama' => [
        'habilitado' => filter_var(env('COMPROBANTE_PROVEEDOR_PDF_IA_OLLAMA_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'url' => env('COMPROBANTE_PROVEEDOR_PDF_IA_OLLAMA_URL', 'http://127.0.0.1:11434'),
        'model' => env('COMPROBANTE_PROVEEDOR_PDF_IA_OLLAMA_MODEL', 'factura-proveedor-anita'),
        'timeout' => (int) env('COMPROBANTE_PROVEEDOR_PDF_IA_OLLAMA_TIMEOUT', 180),
        'temperature' => (float) env('COMPROBANTE_PROVEEDOR_PDF_IA_OLLAMA_TEMPERATURE', 0.05),
        'max_tokens' => (int) env('COMPROBANTE_PROVEEDOR_PDF_IA_OLLAMA_MAX_TOKENS', 4096),
        'max_chars_ocr' => (int) env('COMPROBANTE_PROVEEDOR_PDF_IA_OLLAMA_MAX_CHARS_OCR', 12000),
        // Reparto del recorte de OCR: 0.4 = 40% cabecera y 60% pie (donde están los totales).
        'cabecera_ratio' => (float) env('COMPROBANTE_PROVEEDOR_PDF_IA_OLLAMA_CABECERA_RATIO', 0.4),
    ],

    'corpus' => [
        'cache_path' => env('COMPROBANTE_PROVEEDOR_PDF_IA_CORPUS_PATH', 'compras/factura_pdf_ia/corpus.json'),
        'max_ejemplos_prompt' => (int) env('COMPROBANTE_PROVEEDOR_PDF_IA_CORPUS_MAX_EJEMPLOS', 2),
        'max_muestras_por_cuit' => (int) env('COMPROBANTE_PROVEEDOR_PDF_IA_CORPUS_MAX_MUESTRAS_CUIT', 5),
        'scan_legacy_dir' => env('COMPROBANTE_PROVEEDOR_PDF_IA_SCAN_LEGACY_DIR', '/scan/compras/documentos'),
        'scan_legacy_max_docu' => (int) env('COMPROBANTE_PROVEEDOR_PDF_IA_SCAN_LEGACY_MAX_DOCU', 362500),
    ],
];
