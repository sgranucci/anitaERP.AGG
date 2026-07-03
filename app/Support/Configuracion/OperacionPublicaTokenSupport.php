<?php

namespace App\Support\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tokens de consulta pública (acción visualizar) para enlaces en mails sin login ERP.
 */
final class OperacionPublicaTokenSupport
{
    public const ACCION_VISUALIZAR = 'visualizar';

    /**
     * Invalida tokens visualizar previos de la entidad y crea uno nuevo.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function renovarVisualizar(
        string $modelClass,
        string $foreignKey,
        int $entityId,
        ?int $usuarioDestinoId = null,
        ?int $horasValidez = null,
    ): string {
        $horasValidez = max(1, $horasValidez ?? (int) config('modulo_aviso.publico_horas_validez_token', 168));

        $modelClass::query()
            ->where($foreignKey, $entityId)
            ->where('accion', self::ACCION_VISUALIZAR)
            ->whereNull('usado_el')
            ->update(['usado_el' => now()]);

        $token = Str::random(48);

        $modelClass::create([
            $foreignKey => $entityId,
            'token' => $token,
            'accion' => self::ACCION_VISUALIZAR,
            'usuario_destino_id' => $usuarioDestinoId !== null && $usuarioDestinoId > 0 ? $usuarioDestinoId : null,
            'expira_el' => now()->addHours($horasValidez),
        ]);

        return $token;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function buscarActivo(string $modelClass, string $token, ?string $accionEsperada = null): ?Model
    {
        $row = $modelClass::query()->where('token', $token)->first();
        if ($row === null || ! method_exists($row, 'estaActivo') || ! $row->estaActivo()) {
            return null;
        }
        if ($accionEsperada !== null && (string) ($row->accion ?? '') !== $accionEsperada) {
            return null;
        }

        return $row;
    }
}
