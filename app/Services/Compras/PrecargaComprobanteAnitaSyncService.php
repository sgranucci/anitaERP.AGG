<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Precarga_Comprobante_Proveedor_Concepto;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Support\Compras\AnitaSync\Precarga\PrecargaCabeceraAnitaMapper;
use App\Support\Compras\AnitaSync\Precarga\PrecargaConceptoAnitaMapper;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorNumeroOcSupport;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sincroniza precarga de comprobantes de proveedor (ERP) → Informix compras (precarga / precargaconc).
 *
 * Tras cada insert verifica lectura en Anita (el bridge HTTP a veces responde [] sin grabar).
 */
class PrecargaComprobanteAnitaSyncService
{
    private const SISTEMA = 'compras';

    private const TABLA_CABECERA = 'precarga';

    private const TABLA_CONCEPTO = 'precargaconc';

    public function __construct(
        private readonly Concepto_IvacompraRepositoryInterface $conceptoIvacompraRepository,
        private readonly PrecargaProveedorNumeroOcSupport $numeroOcSupport,
    ) {
    }

    public function insertCabecera(int $precargaId, array $payload): void
    {
        $payload = $this->enriquecerPayloadParaAnita($payload);

        $this->escribirConVerificacion(
            function () use ($precargaId, $payload): void {
                if ($this->existsCabeceraEnAnita($precargaId)) {
                    return;
                }

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
                prec_total,
                prec_cod_mon,
                prec_cotizacion
				',
                    'valores' => PrecargaCabeceraAnitaMapper::valoresInsert($precargaId, $payload),
                ], 'precarga insert');
            },
            fn (): bool => $this->existsCabeceraEnAnita($precargaId),
            'precarga insert',
            ['precarga_id' => $precargaId]
        );
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
        $payload['cotizacion'] = $this->normalizarDecimal($payload['cotizacion'] ?? 1);
        if ($payload['cotizacion'] <= 0) {
            $payload['cotizacion'] = 1.0;
        }

        $monedaId = (int) ($payload['moneda_id'] ?? 0);
        if ($monedaId > 0) {
            $codigoMoneda = Moneda::query()->whereKey($monedaId)->value('codigo');
            if ($codigoMoneda !== null && (string) $codigoMoneda !== '') {
                $payload['codigo_moneda_anita'] = (string) $codigoMoneda;
            }
        }
        if (empty($payload['codigo_moneda_anita'])) {
            $payload['codigo_moneda_anita'] = PrecargaCabeceraAnitaMapper::codigoMoneda($payload);
        }

        if (! empty($payload['numeroordencompra'])) {
            try {
                $payload['numeroordencompra'] = $this->numeroOcSupport->normalizar($payload['numeroordencompra']);
            } catch (RuntimeException) {
                // Si no se puede normalizar, Anita mapper aplica el mismo criterio de respaldo.
            }
        }

        return $payload;
    }

    public function existsCabeceraEnAnita(int $precargaId): bool
    {
        return $this->listarUnaVez(
            self::TABLA_CABECERA,
            'prec_id',
            " WHERE prec_id = '".$precargaId."' "
        ) !== [];
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
        $preccId = (int) $linea->id;
        $precargaId = (int) $linea->precarga_comprobante_proveedor_id;
        $codigoAnita = $this->resolverCodigoConceptoAnita(
            $linea->concepto_ivacompra_id,
            $payload['codigo_concepto_anita'] ?? null
        );

        $this->escribirConVerificacion(
            function () use ($linea, $preccId, $precargaId, $codigoAnita): void {
                if ($this->existsConceptoEnAnita($preccId)) {
                    return;
                }

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
                        $preccId,
                        $precargaId,
                        $codigoAnita,
                        $linea->monto
                    ),
                ], 'precargaconc insert');
            },
            fn (): bool => $this->existsConceptoEnAnita($preccId),
            'precargaconc insert',
            [
                'precc_id' => $preccId,
                'precarga_id' => $precargaId,
                'precc_concepto' => $codigoAnita,
            ]
        );
    }

    /**
     * Reinserta en Anita un concepto ERP si falta (idempotente).
     */
    public function asegurarConceptoEnAnita(Precarga_Comprobante_Proveedor_Concepto $linea, array $payload = []): void
    {
        $preccId = (int) $linea->id;
        if ($this->existsConceptoEnAnita($preccId)) {
            return;
        }

        $this->insertConcepto($linea, $payload);
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

    public function existsConceptoEnAnita(int $preccId): bool
    {
        return $this->listarUnaVez(
            self::TABLA_CONCEPTO,
            'precc_id',
            " WHERE precc_id = '".$preccId."' "
        ) !== [];
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

    /**
     * Escribe en Anita y confirma con lectura; reintenta si el bridge responde OK sin persistir.
     *
     * @param  callable(): void  $escribir
     * @param  callable(): bool  $existe
     * @param  array<string, mixed>  $contextoLog
     */
    private function escribirConVerificacion(
        callable $escribir,
        callable $existe,
        string $contexto,
        array $contextoLog = []
    ): void {
        $maxIntentos = max(1, (int) config('precarga_comprobante.anita_write_reintentos', 3));
        $esperaMs = max(50, (int) config('precarga_comprobante.anita_write_espera_ms', 300));
        $ultimoError = null;

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            try {
                if ($existe()) {
                    return;
                }

                $escribir();

                if ($this->esperarHastaExiste($existe, $esperaMs)) {
                    if ($intento > 1) {
                        Log::info('precarga.anita_sync.escritura_ok_reintento', array_merge($contextoLog, [
                            'contexto' => $contexto,
                            'intento' => $intento,
                        ]));
                    }

                    return;
                }

                Log::warning('precarga.anita_sync.insert_sin_verificar', array_merge($contextoLog, [
                    'contexto' => $contexto,
                    'intento' => $intento,
                    'max_intentos' => $maxIntentos,
                ]));
            } catch (\Throwable $e) {
                $ultimoError = $e;

                if ($existe()) {
                    Log::info('precarga.anita_sync.existe_tras_error_escritura', array_merge($contextoLog, [
                        'contexto' => $contexto,
                        'intento' => $intento,
                        'mensaje' => $e->getMessage(),
                    ]));

                    return;
                }

                Log::warning('precarga.anita_sync.escritura_fallo', array_merge($contextoLog, [
                    'contexto' => $contexto,
                    'intento' => $intento,
                    'max_intentos' => $maxIntentos,
                    'mensaje' => $e->getMessage(),
                ]));
            }

            if ($intento < $maxIntentos) {
                usleep($esperaMs * 1000 * $intento);
            }
        }

        $detalle = $ultimoError ? $ultimoError->getMessage() : 'fila no encontrada tras insert';

        throw new RuntimeException(
            'Error al grabar en Anita ('.$contexto.'): no se pudo verificar la escritura tras '
            .$maxIntentos.' intentos ('.$detalle.')'
        );
    }

    /**
     * Tras un insert, relee con reintentos (el list del bridge a veces viene vacío al instante).
     *
     * @param  callable(): bool  $existe
     */
    private function esperarHastaExiste(callable $existe, int $esperaMs): bool
    {
        $verificaciones = max(1, (int) config('precarga_comprobante.anita_list_reintentos', 3));
        $esperaListMs = max(50, (int) config('precarga_comprobante.anita_list_espera_ms', 250));

        for ($i = 1; $i <= $verificaciones; $i++) {
            if ($existe()) {
                return true;
            }
            if ($i < $verificaciones) {
                usleep(max($esperaMs, $esperaListMs) * 1000);
            }
        }

        return false;
    }

    /**
     * @return list<object>
     */
    private function listarUnaVez(string $tabla, string $campos, string $whereArmado): array
    {
        $api = new ApiAnita;

        return ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => self::SISTEMA,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $whereArmado,
        ]));
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
