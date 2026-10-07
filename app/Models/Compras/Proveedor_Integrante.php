<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Proveedor_Integrante extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'proveedor_integrante';

    protected $fillable = [
        'proveedor_id', 'orden', 'nombre', 'cuit', 'porcentaje', 'inscripto',
    ];

    protected $casts = [
        'porcentaje' => 'float',
    ];

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function estaInscripto(): bool
    {
        return strtoupper(trim((string) $this->inscripto)) !== 'N';
    }

    public static function digitosCuit(string $cuit): string
    {
        return substr(preg_replace('/\D+/', '', $cuit) ?? '', 0, 11);
    }

    public static function formatearCuit(string $cuit): string
    {
        $d = self::digitosCuit($cuit);
        if (strlen($d) !== 11) {
            return trim($cuit);
        }

        return substr($d, 0, 2).'-'.substr($d, 2, 8).'-'.substr($d, 10, 1);
    }
}
