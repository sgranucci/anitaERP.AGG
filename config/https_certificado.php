<?php

return [
    /*
     * Aviso del certificado HTTPS de anitaerp.elbierzo.com.ar.
     * Let's Encrypt valida el puerto 80 público. El 12280 no lo reemplaza.
     */
    'aviso_habilitado' => filter_var(env('HTTPS_CERT_AVISO_HABILITADO', false), FILTER_VALIDATE_BOOLEAN),
    'email' => env('HTTPS_CERT_AVISO_EMAIL', 'sergiogranucci@gmail.com'),
    'dominio' => env('HTTPS_CERT_AVISO_DOMINIO', 'anitaerp.elbierzo.com.ar'),
    // Correo diario cuando faltan estos días o menos, o si ya venció.
    'dias_aviso' => (int) env('HTTPS_CERT_AVISO_DIAS', 21),
    // Certbot intenta renovar cuando faltan 30 días. Ahí también se avisa si el timer falló.
    'dias_ventana_renovacion' => 30,
];
