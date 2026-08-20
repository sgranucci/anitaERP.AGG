<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Camion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'camion';

    protected $fillable = [
        'codigo',
        'dominio',
        'habilitacion',
        'tipo',
        'dominio_acoplado',
        'cuit_chofer',
        'cantidad_precinto',
    ];

    protected $casts = [
        'cantidad_precinto' => 'integer',
    ];

    public function descripcionConsulta(): string
    {
        $partes = [];
        $dominio = trim((string) ($this->dominio ?? ''));
        $habilitacion = trim((string) ($this->habilitacion ?? ''));
        if ($dominio !== '') {
            $partes[] = $dominio;
        }
        if ($habilitacion !== '') {
            $partes[] = $habilitacion;
        }

        return implode(' · ', $partes);
    }

    /**
     * @return array{id: int, codigo: string, dominio: string, habilitacion: string, tipo: string, cantidad_precinto: int, descripcion: string}
     */
    public function aConsultaArray(): array
    {
        return [
            'id' => (int) $this->id,
            'codigo' => (string) $this->codigo,
            'dominio' => (string) ($this->dominio ?? ''),
            'habilitacion' => (string) ($this->habilitacion ?? ''),
            'tipo' => (string) ($this->tipo ?? ''),
            'cantidad_precinto' => (int) ($this->cantidad_precinto ?? 0),
            'descripcion' => $this->descripcionConsulta(),
        ];
    }

    public static function resolverIdPorCodigoAnita(mixed $codigoAnita): ?int
    {
        $codigoAnita = trim((string) ($codigoAnita ?? ''));
        if ($codigoAnita === '' || $codigoAnita === '0') {
            return null;
        }

        $variantes = self::variantesCodigoAnita($codigoAnita);
        $camion = self::query()
            ->whereIn('codigo', $variantes)
            ->orderBy('id')
            ->first();

        return $camion !== null ? (int) $camion->id : null;
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
