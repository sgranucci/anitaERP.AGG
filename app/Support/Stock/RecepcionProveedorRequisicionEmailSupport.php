<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;

final class RecepcionProveedorRequisicionEmailSupport
{
    public static function emailSolicitanteOc(Recepcion_Proveedor $recepcion): ?string
    {
        // En Ordencompra la relación de creousuario_id se llama «usuarios»: pedir «creousuarios»
        // hacía que este método lanzara RelationNotFoundException siempre, y como los handlers de
        // aviso lo llaman dentro de un try que loguea y sigue, el aviso de recepción ingresada y la
        // encuesta al proveedor nunca se enviaban.
        $recepcion->loadMissing([
            'ordencompras.requisiciones.usuarios',
            'ordencompras.usuarios',
        ]);

        $oc = $recepcion->ordencompras;
        if (! $oc) {
            return null;
        }

        $emailReq = trim((string) (optional(optional($oc->requisiciones)->usuarios)->email ?? ''));
        if ($emailReq !== '') {
            return $emailReq;
        }

        return trim((string) (optional($oc->usuarios)->email ?? '')) ?: null;
    }

    /**
     * Solicitante de la OC para avisos de recepción (ingreso / encuesta).
     * Respeta RECEPCION_PROVEEDOR_AVISOS_EXCLUIR_EMAILS.
     */
    public static function emailSolicitanteOcParaAviso(Recepcion_Proveedor $recepcion): ?string
    {
        $email = self::emailSolicitanteOc($recepcion);
        if ($email === null || $email === '') {
            return null;
        }

        return self::estaExcluidoDeAviso($email) ? null : $email;
    }

    /** @return list<string> */
    public static function emailsExcluidosAviso(): array
    {
        $raw = config('recepcion_proveedor.avisos_excluir_emails', []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $email) {
            $email = strtolower(trim((string) $email));
            if ($email !== '') {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }

    public static function estaExcluidoDeAviso(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        return in_array($email, self::emailsExcluidosAviso(), true);
    }

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    public static function filtrarEmailsAviso(array $emails): array
    {
        $excluidos = self::emailsExcluidosAviso();
        if ($excluidos === []) {
            return array_values(array_unique(array_filter(array_map(
                static fn ($e) => strtolower(trim((string) $e)),
                $emails
            ))));
        }

        $out = [];
        foreach ($emails as $email) {
            $email = strtolower(trim((string) $email));
            if ($email === '' || in_array($email, $excluidos, true)) {
                continue;
            }
            $out[] = $email;
        }

        return array_values(array_unique($out));
    }
}
