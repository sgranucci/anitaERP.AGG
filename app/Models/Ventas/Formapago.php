<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Formapago extends Model
{
    protected $fillable = ['nombre', 'abreviatura'];
    protected $table = 'formapago';

    /** Abreviatura de la forma de pago Transferencia. */
    public const ABREVIATURA_TRANSFERENCIA = 'T';

    /**
     * IDs de las formas de pago que son transferencia (abreviatura T o nombre TRANSFERENCIA).
     *
     * @return list<int>
     */
    public static function idsTransferencia(): array
    {
        return static::query()
            ->where(function ($q) {
                $q->where('abreviatura', self::ABREVIATURA_TRANSFERENCIA)
                    ->orWhere('nombre', 'like', '%TRANSFERENC%');
            })
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    /**
     * ¿La forma de pago indicada es una transferencia?
     */
    public static function esTransferencia($formapagoId): bool
    {
        $formapagoId = (int) $formapagoId;

        return $formapagoId > 0 && in_array($formapagoId, self::idsTransferencia(), true);
    }
}

