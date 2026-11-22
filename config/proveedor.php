<?php

$filtroEmpresa = filter_var(env('PROVEEDOR_FILTRO_EMPRESA', false), FILTER_VALIDATE_BOOLEAN);

$parseEmails = static function (?string $raw): array {
    $parts = preg_split('/[\s,;]+/', trim((string) $raw)) ?: [];
    $out = [];
    foreach ($parts as $email) {
        $email = strtolower(trim($email));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $out[$email] = $email;
        }
    }

    return array_values($out);
};

/*
| Aviso al alta (Anita a-promae envia_mail_alta): destinatarios en .env
| PROVEEDOR_EMAIL_AVISO_ALTA (coma/punto y coma). Vacío = no envía.
*/
$enviaMailAltaDefault = strtoupper(trim((string) env('EMPRESA', ''))) === 'EL BIERZO' ? 'true' : 'false';

switch (config('app.empresa')) {
    case 'AGG':
        return [
            'tipoalta' => 'PROVISORIA',
            'enviamailaprobacion' => 'S',
            'emailapruebaalta' => ['impuestosBSA@grupoagg.com'],
            // Default false: AGG no filtra ni exige empresa en el maestro.
            'filtro_empresa' => $filtroEmpresa,
            'enviamailalta' => filter_var(env('PROVEEDOR_ENVIA_MAIL_ALTA', 'false'), FILTER_VALIDATE_BOOLEAN),
            'emailavisoalta' => $parseEmails(env('PROVEEDOR_EMAIL_AVISO_ALTA', '')),
        ];

    default:
        return [
            'tipoalta' => 'DEFINITIVA',
            'enviamailaprobacion' => 'N',
            'emailapruebaalta' => [''],
            // El Bierzo: activar con PROVEEDOR_FILTRO_EMPRESA=true en .env
            'filtro_empresa' => $filtroEmpresa,
            'enviamailalta' => filter_var(env('PROVEEDOR_ENVIA_MAIL_ALTA', $enviaMailAltaDefault), FILTER_VALIDATE_BOOLEAN),
            'emailavisoalta' => $parseEmails(env('PROVEEDOR_EMAIL_AVISO_ALTA', '')),
        ];
}
