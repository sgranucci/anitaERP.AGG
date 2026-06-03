<?php

namespace App\Support\Compras;

use App\Models\Compras\Retencionganancia;
use App\Models\Compras\Retencioniva;
use App\Models\Compras\Retencionsuss;

/**
 * Reglas de autocompletado y bloqueo en la solapa Impuestos del proveedor.
 */
class ProveedorImpuestosRetencionRules
{
    public const CODIGO_SIN_RETENCION = '0';

    public const NOMBRE_SIN_CODIGO_GANANCIA = 'Sin código de retención de ganancias';

    public const NOMBRE_SIN_CODIGO_IVA = 'Sin código de retención de IVA';

    public const NOMBRE_SIN_CODIGO_SUSS = 'Sin código de retención de SUSS';

    /** @var array<string, int|null>|null */
    private static ?array $idsSinCodigoCache = null;

    /**
     * @return array{retencionganancia_id: int|null, retencioniva_id: int|null, retencionsuss_id: int|null}
     */
    public static function idsSinCodigo(): array
    {
        if (self::$idsSinCodigoCache !== null) {
            return self::$idsSinCodigoCache;
        }

        self::$idsSinCodigoCache = [
            'retencionganancia_id' => self::resolverIdSinCodigo(Retencionganancia::class, self::NOMBRE_SIN_CODIGO_GANANCIA),
            'retencioniva_id' => self::resolverIdSinCodigo(Retencioniva::class, self::NOMBRE_SIN_CODIGO_IVA),
            'retencionsuss_id' => self::resolverIdSinCodigo(Retencionsuss::class, self::NOMBRE_SIN_CODIGO_SUSS),
        ];

        return self::$idsSinCodigoCache;
    }

    public static function normalizar(array $data): array
    {
        $ids = self::idsSinCodigo();

        if (($data['condicionganancia'] ?? '') === 'N') {
            $data['retieneganancia'] = 'N';
        }

        if (
            (($data['condicionganancia'] ?? '') === 'N' || ($data['retieneganancia'] ?? '') === 'N')
            && $ids['retencionganancia_id'] !== null
        ) {
            $data['retencionganancia_id'] = $ids['retencionganancia_id'];
        }

        if (($data['retieneiva'] ?? '') === 'N' && $ids['retencioniva_id'] !== null) {
            $data['retencioniva_id'] = $ids['retencioniva_id'];
        }

        if (($data['retienesuss'] ?? '') === 'N' && $ids['retencionsuss_id'] !== null) {
            $data['retencionsuss_id'] = $ids['retencionsuss_id'];
        }

        return $data;
    }

    /**
     * @param  class-string  $modelClass
     */
    private static function resolverIdSinCodigo(string $modelClass, string $nombre): ?int
    {
        $id = $modelClass::query()
            ->where('codigo', self::CODIGO_SIN_RETENCION)
            ->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        $id = $modelClass::query()
            ->where('nombre', $nombre)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
