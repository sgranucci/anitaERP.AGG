<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Canal extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const CODIGO_LOCAL = 'LOCAL';

    public const CODIGO_FABRICA = 'FABRICA';

    protected $table = 'canal';

    protected $fillable = ['codigo', 'nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function articulos()
    {
        return $this->belongsToMany(Articulo::class, 'articulo_canal', 'canal_id', 'articulo_id')
            ->withTimestamps();
    }

    public static function local(): ?self
    {
        return static::porCodigo(config('facturacion_local.canal_codigo', self::CODIGO_LOCAL));
    }

    public static function fabrica(): ?self
    {
        return static::porCodigo(self::CODIGO_FABRICA);
    }

    public static function porCodigo(string $codigo): ?self
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }

        return static::query()
            ->where('codigo', $codigo)
            ->where('activo', true)
            ->first();
    }
}
