<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Distribuidor extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'porcentajecomision', 'codigo'];
    protected $table = 'distribuidor';

    public static function resolverIdPorCodigoAnita(mixed $codigoAnita): ?int
    {
        $codigoAnita = trim((string) ($codigoAnita ?? ''));
        if ($codigoAnita === '' || $codigoAnita === '0') {
            return null;
        }

        $variantes = self::variantesCodigoAnita($codigoAnita);
        $distribuidor = self::query()
            ->whereIn('codigo', $variantes)
            ->orderBy('id')
            ->first();

        return $distribuidor !== null ? (int) $distribuidor->id : null;
    }

    public static function codigoAnitaDesdeId(?int $distribuidorId): int
    {
        if ($distribuidorId === null || $distribuidorId <= 0) {
            return 0;
        }

        $codigo = trim((string) (self::query()->whereKey($distribuidorId)->value('codigo') ?? ''));
        if ($codigo === '' || ! ctype_digit($codigo)) {
            return 0;
        }

        return (int) $codigo;
    }

    /**
     * @return list<string>
     */
    private static function variantesCodigoAnita(string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return [];
        }

        if (! ctype_digit($codigo)) {
            return [$codigo];
        }

        $norm = ltrim($codigo, '0');
        $norm = $norm !== '' ? $norm : '0';

        return array_values(array_unique([$codigo, $norm, str_pad($norm, 10, '0', STR_PAD_LEFT)]));
    }
}

