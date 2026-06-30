<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Models\Stock\Listaprecio;
use Illuminate\Support\Facades\Auth;

class ListaprecioAnitaSyncService
{
    /**
     * Importa una lista desde premae (Anita). Si no existe en premae pero sí en stkpre, crea cabecera mínima en ERP.
     *
     * @return array{
     *     codigo: string,
     *     listaprecio_id: int,
     *     nombre: string,
     *     accion: 'ya_existia'|'importado_premae'|'creado_sin_premae',
     *     advertencias: list<string>
     * }
     */
    public function importarPorCodigo(string $codigo, int $usuarioId): array
    {
        $codigo = trim($codigo);
        if ($codigo === '' || ! preg_match('/^\d+$/', $codigo)) {
            throw new \InvalidArgumentException('Código de lista Anita inválido.');
        }

        $advertencias = [];

        $existente = Listaprecio::query()
            ->where('codigo', $codigo)
            ->orWhere('codigo', (int) $codigo)
            ->first();

        if ($existente) {
            return [
                'codigo' => $codigo,
                'listaprecio_id' => (int) $existente->id,
                'nombre' => (string) $existente->nombre,
                'accion' => 'ya_existia',
                'advertencias' => [],
            ];
        }

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            throw new \RuntimeException('Usuario inválido para importar listaprecio.');
        }

        $modelo = new Listaprecio;
        $modelo->traerRegistroDeAnita($codigo);

        $importada = Listaprecio::query()
            ->where('codigo', $codigo)
            ->orWhere('codigo', (int) $codigo)
            ->first();

        if ($importada) {
            return [
                'codigo' => $codigo,
                'listaprecio_id' => (int) $importada->id,
                'nombre' => (string) $importada->nombre,
                'accion' => 'importado_premae',
                'advertencias' => [],
            ];
        }

        if (! $this->existeEnStkpre($codigo)) {
            throw new \RuntimeException("Lista Anita {$codigo} no existe en premae ni tiene precios en stkpre.");
        }

        $advertencias[] = "Lista {$codigo} sin cabecera en premae (Anita); se creó en ERP desde stkpre.";

        $lista = Listaprecio::create([
            'nombre' => 'Lista '.$codigo,
            'formula' => '0',
            'incluyeimpuesto' => '2',
            'codigo' => (int) $codigo,
            'tiponumeracion_id' => null,
            'usuarioultcambio_id' => $usuarioId,
        ]);

        return [
            'codigo' => $codigo,
            'listaprecio_id' => (int) $lista->id,
            'nombre' => (string) $lista->nombre,
            'accion' => 'creado_sin_premae',
            'advertencias' => $advertencias,
        ];
    }

    private function existeEnStkpre(string $codigo): bool
    {
        $codigoEsc = addslashes($codigo);
        $api = new ApiAnita;
        $res = json_decode($api->apiCall([
            'acc' => 'list',
            'tabla' => 'stkpre',
            'campos' => 'stkp_lista',
            'whereArmado' => " WHERE stkp_lista = '{$codigoEsc}' ",
        ]));

        return is_array($res) && count($res) > 0;
    }
}
