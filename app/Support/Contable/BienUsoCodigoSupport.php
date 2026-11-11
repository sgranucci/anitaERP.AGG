<?php

namespace App\Support\Contable;

use App\Models\Contable\BienUso;
use Illuminate\Support\Facades\Schema;

/**
 * Numeración de código de bien en el ERP (max + 1 por empresa).
 */
final class BienUsoCodigoSupport
{
    /** @var array<string, int> */
    private static array $ultimoAsignado = [];

    public static function columnaDisponible(): bool
    {
        return Schema::hasColumn('bien_uso', 'codigo_bien');
    }

    /**
     * Sugerencia para el formulario (max + 1). No consume el correlativo de la request.
     */
    public static function sugerido(?int $empresaId): int
    {
        return self::maxActual($empresaId) + 1;
    }

    /**
     * Próximo código libre para la empresa (o sin empresa).
     * En la misma request entrega números consecutivos si se llama varias veces.
     */
    public static function siguiente(?int $empresaId): int
    {
        if (! self::columnaDisponible()) {
            return 1;
        }

        $key = self::claveEmpresa($empresaId);

        if (! isset(self::$ultimoAsignado[$key])) {
            self::$ultimoAsignado[$key] = self::maxActual($empresaId);
        }

        self::$ultimoAsignado[$key]++;

        return self::$ultimoAsignado[$key];
    }

    private static function maxActual(?int $empresaId): int
    {
        if (! self::columnaDisponible()) {
            return 0;
        }

        $query = BienUso::query();
        if ($empresaId === null || $empresaId <= 0) {
            $query->whereNull('empresa_id');
        } else {
            $query->where('empresa_id', $empresaId);
        }

        return (int) $query->max('codigo_bien');
    }

    private static function claveEmpresa(?int $empresaId): string
    {
        return $empresaId === null || $empresaId <= 0 ? 'null' : (string) $empresaId;
    }

    /**
     * Si el payload no trae código, completa con el siguiente del ERP.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function completarSiVacio(array $data): array
    {
        if (! self::columnaDisponible()) {
            return $data;
        }

        $codigo = $data['codigo_bien'] ?? null;
        if ($codigo !== null && $codigo !== '' && (int) $codigo > 0) {
            return $data;
        }

        $empresaId = isset($data['empresa_id']) && $data['empresa_id'] !== '' && $data['empresa_id'] !== null
            ? (int) $data['empresa_id']
            : null;

        $data['codigo_bien'] = self::siguiente($empresaId);

        return $data;
    }
}
