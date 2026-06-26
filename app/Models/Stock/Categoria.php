<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use App\Models\Stock\Tipoarticulo;
use App\Support\Stock\CategoriaAnitaBridgeSupport;
use App\Support\Stock\TipoarticuloDefaultSupport;

class Categoria extends Model
{
    protected $fillable = ['nombre', 'codigo', 'copiaot', 'tipoarticulo_id',
                            'division', 'estado', 'grupocompra', 'linea_id', 'deposito_id', 
                            'puntoventa_id', 'excel'];
    protected $table = 'categoria';
    protected $tableAnita = 'stkagr';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'stka_agrupacion';

    public function tipoarticulo()
    {
        return $this->belongsTo(Tipoarticulo::class, 'tipoarticulo_id');
    }

    public function sincronizarConAnita(){
        $tipoarticuloId = TipoarticuloDefaultSupport::idParaImportacionAnita();
        $key = CategoriaAnitaBridgeSupport::keyField();
        $dataAnita = CategoriaAnitaBridgeSupport::listar();

        $datosLocalArray = Categoria::query()->pluck('codigo')->map(
            fn (string $codigo) => CategoriaAnitaBridgeSupport::normalizarCodigo($codigo)
        )->all();

        foreach ($dataAnita as $value) {
            $codigoAnita = CategoriaAnitaBridgeSupport::normalizarCodigo((string) ($value->{$key} ?? ''));
            if ($codigoAnita === '0' || in_array($codigoAnita, $datosLocalArray, true)) {
                continue;
            }

            $this->traerRegistroDeAnita((string) ($value->{$key} ?? ''), $tipoarticuloId);
            $datosLocalArray[] = $codigoAnita;
        }
    }

    public function traerRegistroDeAnita($key, ?int $tipoarticuloId = null){
        $tipoarticuloId ??= TipoarticuloDefaultSupport::idParaImportacionAnita();
        $keyField = CategoriaAnitaBridgeSupport::keyField();
        $filas = CategoriaAnitaBridgeSupport::listarDetalle(
            " WHERE {$keyField} = '".addslashes((string) $key)."' "
        );

        if ($filas === []) {
            return;
        }

        $payload = CategoriaAnitaBridgeSupport::mapPayloadErp($filas[0], $tipoarticuloId);

        if (Categoria::query()->where('codigo', $payload['codigo'])->exists()) {
            return;
        }

        Categoria::create($payload);
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		// Traigo id del tipo de articulo 
		if ($request->tipoarticulo_id == 2)
			$tipoarticulo = 'B';
		else
			$tipoarticulo = 'Z';

		$codigo = str_pad($request->codigo, 4, "0", STR_PAD_LEFT);

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'campos' => ' stka_agrupacion, stka_desc',
            'valores' => " '".$codigo."', '".$request->nombre."' "
        );
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		// Traigo id del tipo de articulo 
		if ($request->tipoarticulo_id == 2)
			$tipoarticulo = 'B';
		else
			$tipoarticulo = 'Z';

		$codigo = str_pad($request->codigo, 4, "0", STR_PAD_LEFT);

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'update',
					'valores' => " stka_desc = '".  $request->nombre."'", 
					'whereArmado' => " WHERE stka_agrupacion = '".$codigo."' " );
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();

        $data = array( 'acc' => 'delete', 
			'tabla' => $this->tableAnita, 
			'whereArmado' => " WHERE stka_id = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}
}
