<?php

namespace App\Support\Contable\ConciliacionBancaria;

use App\ApiAnita;
use RuntimeException;
use Throwable;

/**
 * Lectura de cheques propios Anita (cpromae) para conciliación bancaria.
 */
final class ConciliacionBancariaCpromaeBridgeReader
{
    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
    ) {
    }

    /**
     * @return list<object>
     */
    public function listarPorCuenta(string $codigoCuentacaja, ?int $empresaId = null): array
    {
        $cuenta = str_pad(ltrim($codigoCuentacaja, '0'), 8, '0', STR_PAD_LEFT);
        $where = " WHERE cpro_cuenta='".$cuenta."'";
        if ($empresaId !== null && $empresaId > 0) {
            $where .= ' AND cpro_empresa='.(int) $empresaId;
        }

        return $this->listar($where);
    }

    /**
     * @param  list<string|int>  $numeros
     * @return list<object>
     */
    public function listarPorNumeros(string $codigoCuentacaja, array $numeros, ?int $empresaId = null): array
    {
        $cuenta = str_pad(ltrim($codigoCuentacaja, '0'), 8, '0', STR_PAD_LEFT);
        $nums = array_values(array_unique(array_filter(array_map(
            static fn ($n) => (int) preg_replace('/\D/', '', (string) $n),
            $numeros,
        ), static fn (int $n) => $n > 0)));

        if ($nums === []) {
            return [];
        }

        $out = [];
        // Bridge: consultas por lote para no saturar el where.
        foreach (array_chunk($nums, 40) as $chunk) {
            $in = implode(',', $chunk);
            $where = " WHERE cpro_cuenta='".$cuenta."' AND cpro_nro_cheque IN (".$in.")";
            if ($empresaId !== null && $empresaId > 0) {
                $where .= ' AND cpro_empresa='.(int) $empresaId;
            }
            foreach ($this->listar($where) as $row) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @return list<object>
     */
    private function listar(string $where): array
    {
        try {
            $raw = $this->api->apiCall([
                'acc' => 'list',
                'sistema' => 'che_ban',
                'tabla' => 'cpromae',
                'campos' => 'cpro_cuenta,cpro_nro_cheque,cpro_fecha_cheque,cpro_fecha_emision,cpro_importe,'
                    .'cpro_estado,cpro_estado_banco,cpro_fecha_entrega,cpro_entregado_a,cpro_proveedor,'
                    .'cpro_nro_op,cpro_para_dep,cpro_empresa,cpro_fecha_anula,cpro_cod_mon,cpro_cotizacion',
                'whereArmado' => $where,
            ]);
            $decoded = json_decode($raw);
            if (! is_array($decoded)) {
                throw new RuntimeException('Respuesta bridge cpromae inválida.');
            }

            return $decoded;
        } catch (Throwable $e) {
            throw new RuntimeException('Error leyendo cpromae: '.$e->getMessage(), 0, $e);
        }
    }
}
