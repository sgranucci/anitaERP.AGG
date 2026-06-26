<?php

namespace App\Models\Stock;

use App\Support\Stock\UsuarioTipotransaccionStockAutorizado;
use App\Traits\Stock\Tipotransaccion_StockTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tipotransaccion_Stock extends Model
{
    use SoftDeletes;
    use Tipotransaccion_StockTrait;

    protected $fillable = ['nombre', 'operacion', 'abreviatura', 'signo', 'estado', 'requiere_aprobacion', 'maneja_contabilidad', 'destino_bien_uso', 'origen_bien_uso'];

    protected $casts = [
        'requiere_aprobacion' => 'boolean',
        'maneja_contabilidad' => 'boolean',
        'destino_bien_uso' => 'boolean',
        'origen_bien_uso' => 'boolean',
    ];

    protected $table = 'tipotransaccion_stock';

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeParaUsuarioAutorizado($query)
    {
        return UsuarioTipotransaccionStockAutorizado::aplicarFiltroQuery($query);
    }

    public static function autorizadoParaUsuario(int $tipotransaccionStockId): bool
    {
        return UsuarioTipotransaccionStockAutorizado::tipotransaccionAutorizada($tipotransaccionStockId);
    }

    public function setSignoAttribute($signo)
    {
        switch (Tipotransaccion_StockTrait::$enumSigno[$signo]) {
            case 'Suma':
                $this->attributes['signo'] = 1;
                break;
            case 'Resta':
                $this->attributes['signo'] = -1;
                break;
        }
    }

    public function getSignoAttribute($signo)
    {
        switch ($signo) {
            case 1:
                $retSigno = 'S';
                break;
            case -1:
                $retSigno = 'R';
                break;
            default:
                $retSigno = 'S';
        }

        return $retSigno;
    }
}
