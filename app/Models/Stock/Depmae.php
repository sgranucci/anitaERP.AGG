<?php

namespace App\Models\Stock;

use App\Models\Configuracion\Empresa;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\Stock\DepmaeAnitaSyncService;
use App\Support\Stock\DepmaeAnitaExclusionSupport;
use App\Traits\Stock\DepmaeTrait;
use App\ApiAnita;

class Depmae extends Model
{
    use DepmaeTrait;

    protected $fillable = ['nombre', 'tipodeposito', 'codigo', 'empresa_id'];
    protected $table = 'depmae';
    protected $keyField = 'depm_deposito';

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeParaEmpresa($query, int $empresaId)
    {
        if ($empresaId <= 0) {
            return $query;
        }

        return $query->where('empresa_id', $empresaId);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeParaUsuarioAutorizado($query)
    {
        return UsuarioDepositoAutorizado::aplicarFiltroQuery($query);
    }

    public static function autorizadoParaUsuario(int $depmaeId): bool
    {
        return UsuarioDepositoAutorizado::depositoAutorizado($depmaeId);
    }

    public static function autorizadoParaUsuarioYEmpresa(int $depmaeId, int $empresaId): bool
    {
        if (! static::existeParaEmpresa($depmaeId, $empresaId)) {
            return false;
        }

        return static::autorizadoParaUsuario($depmaeId);
    }

    public static function existeParaEmpresa(int $depmaeId, int $empresaId): bool
    {
        if ($depmaeId <= 0) {
            return false;
        }

        $query = static::query()->whereKey($depmaeId);

        return $empresaId > 0
            ? $query->paraEmpresa($empresaId)->exists()
            : $query->exists();
    }

    public function perteneceAEmpresa(int $empresaId): bool
    {
        if ($empresaId <= 0) {
            return true;
        }

        return (int) $this->empresa_id === $empresaId;
    }

    private function replicaEnAnita(int $empresaId): bool
    {
        return $empresaId <= 1;
    }

    /**
     * @return array{
     *     en_anita: int,
     *     omitidos_maquina: int,
     *     importados: int,
     *     actualizados: int,
     *     omitidos: int,
     *     errores: list<string>
     * }
     */
    public function sincronizarConAnita(): array
    {
        return app(DepmaeAnitaSyncService::class)->sincronizarConAnita();
    }

    public function traerRegistroDeAnita($key, int $empresaId = 1): string
    {
        if (DepmaeAnitaExclusionSupport::debeOmitirCodigo((string) $key)) {
            return 'omitido';
        }

        return app(DepmaeAnitaSyncService::class)->traerRegistroDeAnita($empresaId, (string) $key);
    }

	public function guardarAnita($request, $id) {
        if (! $this->replicaEnAnita((int) $request->empresa_id)) {
            return;
        }

        $apiAnita = new ApiAnita();

	if (config('app.empresa') == 'Calzados Ferli' ||
	    config('app.empresa') == 'EL BIERZO')
            $data = array( 'tabla' => 'depmae', 'acc' => 'insert',
                'campos' => ' depm_deposito, depm_desc, depm_maneja_part, depm_cta_contable ',
                'valores' => " '".$id."', '".$request->nombre."', 'S', 0"
            );
        else
        {
            $tipoDeposito = array_search($request->tipodeposito, 
                array_column(Depmae::$enumTipoDeposito, 'nombre', 'valor'));

            $data = array( 'tabla' => 'depmae', 'acc' => 'insert',
                'campos' => ' depm_deposito, depm_desc, depm_maneja_part, depm_tipo_deposito ',
                'valores' => " '".$request->codigo."', '".$request->nombre."', 'S', '".$tipoDeposito."' "
            );
        }
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request, $id) {
        if (! $this->replicaEnAnita((int) $request->empresa_id)) {
            return;
        }

        $apiAnita = new ApiAnita();

        if (config('app.empresa') == 'Calzados Ferli' ||
	    	config('app.empresa') == 'EL BIERZO')
            $data = array( 'acc' => 'update', 'tabla' => 'depmae', 'valores' => " depm_desc = '".
                        $request->nombre."' ", 'whereArmado' => " WHERE depm_deposito = '".$id."' " );
        else
        {
            $tipoDeposito = array_search($request->tipodeposito, 
                array_column(Depmae::$enumTipoDeposito, 'nombre', 'valor'));

            $data = array( 'acc' => 'update', 'tabla' => 'depmae', 'valores' => 
                        " depm_desc = '".$request->nombre."',
                          depm_tipo_deposito = '".$tipoDeposito."' ", 
                        'whereArmado' => " WHERE depm_deposito = '".$request->codigo."' " );            
        }
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id, int $empresaId = 1) {
        if (! $this->replicaEnAnita($empresaId)) {
            return;
        }

        $apiAnita = new ApiAnita();
        if (config('app.empresa') == 'Calzados Ferli' ||
	    	config('app.empresa') == 'EL BIERZO')
            $data = array( 'acc' => 'delete', 'tabla' => 'depmae', 'whereArmado' => " WHERE depm_deposito = '".$id."' " );
        else
            $data = array( 'acc' => 'delete', 'tabla' => 'depmae', 'whereArmado' => " WHERE depm_deposito = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}
}
