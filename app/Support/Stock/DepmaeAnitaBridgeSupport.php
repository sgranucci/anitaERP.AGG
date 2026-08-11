<?php

namespace App\Support\Stock;

use App\ApiAnita;
use App\Models\Stock\Depmae;
use App\Traits\Stock\DepmaeTrait;

/**
 * Bridge Anita por empresa para tabla depmae (stock descentralizado Kandiko/Rebisco).
 */
final class DepmaeAnitaBridgeSupport
{
    public static function sistema(): string
    {
        return (string) config('stock.anita_stkmov.sistema_ventas', 'ventas');
    }

    public static function tabla(): string
    {
        return 'depmae';
    }

    public static function keyField(): string
    {
        return 'depm_deposito';
    }

    /**
     * @return array{servidor?: string, path_sistema?: string, sistema: string, ifx_server?: string}
     */
    public static function parametrosBridge(int $empresaId): array
    {
        return StockAnitaBridgeSupport::parametrosBridge(max(1, $empresaId));
    }

    /**
     * @return list<object>
     */
    public static function listar(int $empresaId, ?string $whereArmado = null): array
    {
        $api = new ApiAnita;
        $payload = array_merge(self::parametrosBridge($empresaId), [
            'acc' => 'list',
            'tabla' => self::tabla(),
            'campos' => self::camposListado(),
            'orderBy' => self::keyField(),
        ]);

        if ($whereArmado !== null && trim($whereArmado) !== '') {
            $payload['whereArmado'] = $whereArmado;
        }

        $rows = json_decode($api->apiCall($payload));

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<object>
     */
    public static function listarOperativos(int $empresaId): array
    {
        $filas = [];
        foreach (self::listar($empresaId) as $row) {
            $codigo = trim((string) ($row->{self::keyField()} ?? ''));
            if (DepmaeAnitaExclusionSupport::debeOmitirCodigo($codigo)) {
                continue;
            }
            $filas[] = $row;
        }

        return $filas;
    }

    public static function mapTipoDeposito(object $row): string
    {
        if (config('app.empresa') === 'Calzados Ferli'
            || config('app.empresa') === 'EL BIERZO') {
            return 'N';
        }

        if (config('app.empresa') === 'AGG') {
            $tipo = array_search(
                (string) ($row->depm_tipo_deposito ?? ''),
                array_column(Depmae::$enumTipoDeposito, 'valor', 'nombre'),
                true
            );

            return $tipo !== false ? (string) $tipo : 'Normal';
        }

        return 'N';
    }

    /**
     * @return array{nombre: string, tipodeposito: string, codigo: string}
     */
    public static function mapPayload(object $row): array
    {
        $codigo = trim((string) ($row->{self::keyField()} ?? ''));

        return [
            'nombre' => mb_substr(trim((string) ($row->depm_desc ?? '')), 0, 50),
            'tipodeposito' => self::mapTipoDeposito($row),
            'codigo' => $codigo,
        ];
    }

    private static function camposListado(): string
    {
        $camposEnv = trim((string) config('stock.depmae_anita_campos_listado', ''));
        if ($camposEnv !== '') {
            return $camposEnv;
        }

        if (config('app.empresa') === 'Calzados Ferli'
            || config('app.empresa') === 'EL BIERZO') {
            // depm_desc al final: el bridge CSV parte por "|" sin escape.
            return 'depm_deposito,depm_maneja_part,depm_cta_contable,depm_desc';
        }

        if (config('app.empresa') === 'AGG') {
            return 'depm_deposito,depm_desc,depm_maneja_part,depm_tipo_deposito';
        }

        // INTERFORMING y otros: Informix sin depm_tipo_deposito ni depm_cta_contable.
        return 'depm_deposito,depm_desc,depm_maneja_part';
    }
}
