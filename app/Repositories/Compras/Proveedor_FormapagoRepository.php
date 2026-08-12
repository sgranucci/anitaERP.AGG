<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor_Formapago;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Proveedor_FormapagoRepository implements Proveedor_FormapagoRepositoryInterface
{
    protected $model;

    public function __construct(Proveedor_Formapago $proveedor_formapago)
    {
        $this->model = $proveedor_formapago;
    }

    public function create(array $data, $id)
    {
        return self::guardarProveedor_Formapago($data, 'create', $id);
    }

    public function createUnique(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return self::guardarProveedor_Formapago($data, 'update', $id);
    }

    public function delete($proveedor_id, $codigo)
    {
        return $this->model->where('proveedor_id', $proveedor_id)->delete();
    }

    public function find($id)
    {
        if (null == $proveedor_formapago = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $proveedor_formapago;
    }

    public function leeProveedorFormapago($proveedor_id)
    {
        return $this->model->where('proveedor_id', $proveedor_id)->get();
    }

    public function findOrFail($id)
    {
        if (null == $proveedor_formapago = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $proveedor_formapago;
    }

    private function guardarProveedor_Formapago($data, $funcion, $id = null)
    {
        if (! isset($data['nombres'])) {
            $this->model->where('proveedor_id', $id)->delete();

            return;
        }

        $filas = $this->normalizarFilasFormapago($data);

        if ($funcion === 'update') {
            $this->model->where('proveedor_id', $id)->delete();
        }

        foreach ($filas as $fila) {
            $this->model->create(array_merge($fila, ['proveedor_id' => $id]));
        }
    }

    /**
     * Solo renglones con los FK obligatorios de BD.
     *
     * @return list<array<string, mixed>>
     */
    private function normalizarFilasFormapago(array $data): array
    {
        $nombres = (array) ($data['nombres'] ?? []);
        $formapagoIds = (array) ($data['formapago_ids'] ?? []);
        $cbus = (array) ($data['cbus'] ?? []);
        $aliasCbus = (array) ($data['alias_cbus'] ?? []);
        $tipocuentacajaIds = (array) ($data['tipocuentacaja_ids'] ?? []);
        $monedaIds = (array) ($data['moneda_ids'] ?? []);
        $numerocuentas = (array) ($data['numerocuentas'] ?? []);
        $nroinscripciones = (array) ($data['nroinscripciones'] ?? []);
        $bancoIds = (array) ($data['banco_ids'] ?? []);
        $mediopagoIds = (array) ($data['mediopago_ids'] ?? []);
        $emails = (array) ($data['emails'] ?? []);

        $max = max(
            count($nombres),
            count($formapagoIds),
            count($tipocuentacajaIds),
            count($monedaIds)
        );

        $filas = [];
        for ($i = 0; $i < $max; $i++) {
            $nombre = trim((string) ($nombres[$i] ?? ''));
            $formapagoId = $this->idEnteroONull($formapagoIds[$i] ?? null);
            $tipocuentacajaId = $this->idEnteroONull($tipocuentacajaIds[$i] ?? null);
            $monedaId = $this->idEnteroONull($monedaIds[$i] ?? null);

            if ($nombre === '' && $formapagoId === null && $tipocuentacajaId === null && $monedaId === null
                && trim((string) ($cbus[$i] ?? '')) === ''
                && trim((string) ($aliasCbus[$i] ?? '')) === ''
                && trim((string) ($numerocuentas[$i] ?? '')) === ''
                && trim((string) ($nroinscripciones[$i] ?? '')) === ''
                && $this->idEnteroONull($bancoIds[$i] ?? null) === null
                && $this->idEnteroONull($mediopagoIds[$i] ?? null) === null
                && trim((string) ($emails[$i] ?? '')) === '') {
                continue;
            }

            // El tipo de cuenta (TC) es opcional (solo relevante en transferencias),
            // así que no se descarta la fila cuando viene vacío.
            if ($nombre === '' || $formapagoId === null || $monedaId === null) {
                continue;
            }

            $filas[] = [
                'nombre' => $nombre,
                'formapago_id' => $formapagoId,
                'cbu' => $this->textoONull($cbus[$i] ?? null),
                'alias_cbu' => $this->textoONull($aliasCbus[$i] ?? null),
                'tipocuentacaja_id' => $tipocuentacajaId,
                'moneda_id' => $monedaId,
                'numerocuenta' => (string) ($numerocuentas[$i] ?? ''),
                'nroinscripcion' => (string) ($nroinscripciones[$i] ?? ''),
                'banco_id' => $this->idEnteroONull($bancoIds[$i] ?? null),
                'mediopago_id' => $this->idEnteroONull($mediopagoIds[$i] ?? null),
                'email' => (string) ($emails[$i] ?? ''),
            ];
        }

        return $filas;
    }

    private function idEnteroONull($valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);

        return ($id !== false && $id > 0) ? (int) $id : null;
    }

    private function textoONull($valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }
}
