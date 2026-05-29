<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Precarga_Comprobante_Proveedor_Concepto;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Configuracion\Empresa;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Support\Compras\AnitaSync\Precarga\PrecargaCabeceraAnitaMapper;
use App\Support\Compras\AnitaSync\Precarga\PrecargaConceptoAnitaMapper;
use RuntimeException;

/**
 * Sincroniza precarga de comprobantes de proveedor (ERP) → Informix compras (precarga / precargaconc).
 */
class PrecargaComprobanteAnitaSyncService
{
    private const SISTEMA = 'compras';

    private const TABLA_CABECERA = 'precarga';

    private const TABLA_CONCEPTO = 'precargaconc';

    public function __construct(
        private readonly Concepto_IvacompraRepositoryInterface $conceptoIvacompraRepository,
    ) {
    }

    public function insertCabecera(int $precargaId, array $payload): void
    {
        $payload = $this->enriquecerPayloadParaAnita($payload);

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'tabla' => self::TABLA_CABECERA,
            'acc' => 'insert',
            'sistema' => self::SISTEMA,
            'campos' => '
				prec_id,
				prec_proveedor,
                prec_empresa,
                prec_tipo,
                prec_letra,
                prec_sucursal,
                prec_numero,
                prec_ordencompra,
                prec_subtotal,
                prec_total
				',
            'valores' => PrecargaCabeceraAnitaMapper::valoresInsert($precargaId, $payload),
        ], 'precarga insert');
    }

    public function updateCabecera(int $precargaId, array $payload): void
    {
        $payload = $this->enriquecerPayloadParaAnita($payload);

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => self::TABLA_CABECERA,
            'sistema' => self::SISTEMA,
            'valores' => PrecargaCabeceraAnitaMapper::valoresUpdate($payload),
            'whereArmado' => " WHERE prec_id = '".$precargaId."' ",
        ], 'precarga update');
    }

    /**
     * Inserta o actualiza cabecera (edición ABM / registros previos a la sync).
     */
    public function syncCabecera(int $precargaId, array $payload): void
    {
        $payload = $this->enriquecerPayloadParaAnita($payload);

        if ($this->existsCabeceraEnAnita($precargaId)) {
            $this->updateCabecera($precargaId, $payload);
        } else {
            $this->insertCabecera($precargaId, $payload);
        }
    }

    /**
     * Completa códigos Anita desde FKs del ERP (formulario crear/editar precarga).
     */
    public function enriquecerPayloadParaAnita(array $payload): array
    {
        if (! empty($payload['empresa_id'])) {
            $empresa = Empresa::query()->find($payload['empresa_id']);
            if ($empresa) {
                $payload['codigoempresa'] = $empresa->codigo;
            }
        }

        if (! empty($payload['proveedor_id'])) {
            $proveedor = Proveedor::query()->find($payload['proveedor_id']);
            if ($proveedor) {
                $payload['codigoproveedor'] = $proveedor->codigo;
            }
        }

        if (! empty($payload['tipotransaccion_compra_id'])) {
            $tipo = Tipotransaccion_Compra::query()->find($payload['tipotransaccion_compra_id']);
            if ($tipo) {
                $payload['tipo'] = $tipo->abreviatura;
            }
        }

        $payload['subtotal'] = $this->normalizarDecimal($payload['subtotal'] ?? 0);
        $payload['total'] = $this->normalizarDecimal($payload['total'] ?? 0);

        return $payload;
    }

    public function existsCabeceraEnAnita(int $precargaId): bool
    {
        $api = new ApiAnita;
        $rows = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => self::SISTEMA,
            'tabla' => self::TABLA_CABECERA,
            'campos' => 'prec_id',
            'whereArmado' => " WHERE prec_id = '".$precargaId."' ",
        ]));

        return count($rows) > 0;
    }

    public function deleteCabecera(int $precargaId): void
    {
        $this->deleteConceptosPorPrecarga($precargaId);

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => self::TABLA_CABECERA,
            'sistema' => self::SISTEMA,
            'whereArmado' => " WHERE prec_id = '".$precargaId."' ",
        ], 'precarga delete');
    }

    /**
     * @param  array{codigo_concepto_anita?: mixed}  $payload
     */
    public function insertConcepto(Precarga_Comprobante_Proveedor_Concepto $linea, array $payload = []): void
    {
        $codigoAnita = $this->resolverCodigoConceptoAnita(
            $linea->concepto_ivacompra_id,
            $payload['codigo_concepto_anita'] ?? null
        );

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'tabla' => self::TABLA_CONCEPTO,
            'acc' => 'insert',
            'sistema' => self::SISTEMA,
            'campos' => '
				precc_id,
				precc_precarga_id,
                precc_concepto,
                precc_monto
				',
            'valores' => PrecargaConceptoAnitaMapper::valoresInsert(
                (int) $linea->id,
                (int) $linea->precarga_comprobante_proveedor_id,
                $codigoAnita,
                $linea->monto
            ),
        ], 'precargaconc insert');
    }

    public function updateConcepto(int $preccId, int $precargaId, array $payload): void
    {
        $codigoAnita = $this->resolverCodigoConceptoAnita(
            $payload['concepto_ivacompra_id'] ?? null,
            $payload['codigo_concepto_anita'] ?? null
        );

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => self::TABLA_CONCEPTO,
            'sistema' => self::SISTEMA,
            'valores' => PrecargaConceptoAnitaMapper::valoresUpdate(
                $precargaId,
                $codigoAnita,
                $payload['monto'] ?? 0
            ),
            'whereArmado' => " WHERE precc_id = '".$preccId."' ",
        ], 'precargaconc update');
    }

    public function deleteConcepto(int $preccId): void
    {
        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => self::TABLA_CONCEPTO,
            'sistema' => self::SISTEMA,
            'whereArmado' => " WHERE precc_id = '".$preccId."' ",
        ], 'precargaconc delete');
    }

    public function deleteConceptosPorPrecarga(int $precargaId): void
    {
        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => self::TABLA_CONCEPTO,
            'sistema' => self::SISTEMA,
            'whereArmado' => " WHERE precc_precarga_id = '".$precargaId."' ",
        ], 'precargaconc delete por precarga');
    }

    /**
     * Código Anita del concepto (conccomp.concc_concepto ↔ concepto_ivacompra.codigo).
     *
     * @throws RuntimeException
     */
    public function resolverCodigoConceptoAnita(?int $conceptoIvacompraId, mixed $codigoDesdeApi = null): int
    {
        if ($codigoDesdeApi !== null && $codigoDesdeApi !== '') {
            $concepto = $this->conceptoIvacompraRepository->findPorCodigo($codigoDesdeApi);
            if (! $concepto) {
                $normalizado = ltrim((string) $codigoDesdeApi, '0');
                if ($normalizado !== '') {
                    $concepto = $this->conceptoIvacompraRepository->findPorCodigo($normalizado);
                }
            }
            if ($concepto) {
                return (int) $concepto->codigo;
            }

            throw new RuntimeException(
                'Concepto IVA compra con código Anita «'.(string) $codigoDesdeApi.'» no existe en el ERP.'
            );
        }

        if ($conceptoIvacompraId) {
            $concepto = $this->conceptoIvacompraRepository->find($conceptoIvacompraId);
            if ($concepto) {
                return (int) $concepto->codigo;
            }
        }

        throw new RuntimeException('Concepto IVA compra no informado o inexistente en el ERP.');
    }

    private function normalizarDecimal(mixed $valor): float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $s = trim((string) $valor);
        if ($s === '') {
            return 0.0;
        }

        $s = str_replace([' ', "\xc2\xa0"], '', $s);
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        return (float) $s;
    }
}
