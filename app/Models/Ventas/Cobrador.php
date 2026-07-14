<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Cobrador extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre', 'comision', 'empresa_id', 'legajo_id', 'codigo'];

    protected $table = 'cobrador';

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public static function resolverIdPorCodigoAnita(mixed $codigoAnita): ?int
    {
        $codigoAnita = trim((string) ($codigoAnita ?? ''));
        if ($codigoAnita === '' || $codigoAnita === '0') {
            return null;
        }

        $variantes = self::variantesCodigoAnita($codigoAnita);
        $cobrador = self::query()
            ->whereIn('codigo', $variantes)
            ->orderBy('id')
            ->first();

        return $cobrador !== null ? (int) $cobrador->id : null;
    }

    public static function codigoAnitaDesdeId(?int $cobradorId): int
    {
        if ($cobradorId === null || $cobradorId <= 0) {
            return 0;
        }

        $codigo = trim((string) (self::query()->whereKey($cobradorId)->value('codigo') ?? ''));
        if ($codigo === '' || ! ctype_digit($codigo)) {
            return (int) $codigo ?: 0;
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
