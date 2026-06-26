<?php

namespace App\Support\Stock;

use App\ApiAnita;
use App\Models\Stock\Depmae;
use App\Models\Stock\Linea;
use App\Models\Ventas\Puntoventa;

/**
 * Bridge Anita stkagr → categoria ERP.
 * stka_rubro existe en algunos Informix pero no se usa en el ERP ni en la importación.
 */
final class CategoriaAnitaBridgeSupport
{
    public static function tabla(): string
    {
        return 'stkagr';
    }

    public static function keyField(): string
    {
        return 'stka_agrupacion';
    }

    public static function camposListado(): string
    {
        $camposEnv = trim((string) config('stock.categoria_anita_campos_listado', ''));
        if ($camposEnv !== '') {
            return $camposEnv;
        }

        return self::keyField();
    }

    public static function camposDetalle(): string
    {
        $camposEnv = trim((string) config('stock.categoria_anita_campos_detalle', ''));
        if ($camposEnv !== '') {
            return $camposEnv;
        }

        if (config('app.empresa') === 'FRASLE') {
            return 'stka_agrupacion,stka_desc,stka_division,stka_estado,stka_grupocom,stka_linea,stka_deposito,stka_sucursal,stka_excel';
        }

        return 'stka_agrupacion,stka_desc';
    }

    /**
     * @return list<object>
     */
    public static function listar(?string $whereArmado = null, ?string $campos = null): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => self::tabla(),
            'campos' => $campos ?? self::camposListado(),
            'orderBy' => self::keyField(),
        ];

        if ($whereArmado !== null && trim($whereArmado) !== '') {
            $payload['whereArmado'] = $whereArmado;
        }

        $rows = json_decode($api->apiCall($payload));

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<object>
     */
    public static function listarDetalle(?string $whereArmado = null): array
    {
        return self::listar($whereArmado, self::camposDetalle());
    }

    public static function normalizarCodigo(string $raw): string
    {
        $codigo = ltrim(trim($raw), '0');

        return $codigo !== '' ? $codigo : '0';
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapPayloadErp(object $row, int $tipoarticuloId): array
    {
        $key = self::keyField();
        $codigo = self::normalizarCodigo((string) ($row->{$key} ?? ''));
        $nombre = mb_substr(trim((string) ($row->stka_desc ?? '')), 0, 100);

        if ($codigo === '0' || $nombre === '') {
            throw new \InvalidArgumentException('Categoría Anita sin código o descripción.');
        }

        $payload = [
            'nombre' => $nombre,
            'codigo' => $codigo,
            'copiaot' => '',
            'tipoarticulo_id' => $tipoarticuloId,
        ];

        if (config('app.empresa') !== 'FRASLE') {
            return $payload;
        }

        $linea = Linea::query()->where('codigo', self::normalizarCodigo((string) ($row->stka_linea ?? '')))->first();
        $deposito = Depmae::query()->where('codigo', trim((string) ($row->stka_deposito ?? '')))->first();
        $puntoventa = Puntoventa::query()->where('codigo', trim((string) ($row->stka_sucursal ?? '')))->first();

        return array_merge($payload, [
            'division' => $row->stka_division ?? null,
            'estado' => $row->stka_estado ?? null,
            'grupocompra' => $row->stka_grupocom ?? null,
            'linea_id' => $linea?->id,
            'deposito_id' => $deposito?->id,
            'puntoventa_id' => $puntoventa?->id,
            'excel' => $row->stka_excel ?? null,
        ]);
    }
}
