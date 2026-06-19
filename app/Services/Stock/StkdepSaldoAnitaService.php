<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Support\Stock\ArticuloSeleccionOperativaSupport;
use App\Models\Stock\Depmae;
use Illuminate\Support\Facades\Log;

/**
 * Saldos por depósito en Informix (stkdep) vía ApiAnita.
 * Clave: stkd_articulo + stkd_deposito; stkd_deposito = depmae.codigo en el ERP.
 *
 * En transferencias se acota por artículos cuyo depósito de entrega (articulo.depositoentrega_id)
 * coincide con el depósito de salida consultado, para reducir filas en Anita y en el ERP.
 */
class StkdepSaldoAnitaService
{
    private const TABLA = 'stkdep';

    private const CAMPO_ARTICULO = 'stkd_articulo';

    private const CAMPO_DEPOSITO = 'stkd_deposito';

    private const CAMPO_CANTIDAD = 'stkd_cantidad';

    private const LONGITUD_CODIGO = 13;

    /** Tamaño máximo de lista IN por llamada a Anita. */
    private const IN_CHUNK_SIZE = 400;

    public function __construct(
        private ApiAnita $apiAnita,
    ) {}

    public function codigoAnitaDesdeSku(string $sku): string
    {
        return str_pad(trim($sku), self::LONGITUD_CODIGO, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<array{sku_anita: string, saldo: float, articulo_id: int|null, sku: string|null, descripcion: string|null}>
     */
    public function inventarioPorDepositoId(int $depositoId): array
    {
        $deposito = Depmae::query()->find($depositoId);
        if (! $deposito || trim((string) $deposito->codigo) === '') {
            return [];
        }

        $codigosAnita = $this->codigosAnitaPorDepositoEntrega($depositoId);
        if ($codigosAnita === []) {
            return [];
        }

        $codigoDeposito = str_replace("'", "''", trim((string) $deposito->codigo));
        $porCodigoAnita = $this->consultarStkdepPorDeposito($codigoDeposito, $codigosAnita);

        if ($porCodigoAnita === []) {
            return [];
        }

        $articulosPorCodigo = $this->resolverArticulosErp(array_keys($porCodigoAnita), $depositoId);
        $out = [];
        foreach ($porCodigoAnita as $codigo => $row) {
            $art = $articulosPorCodigo[$codigo] ?? null;
            if ($art === null) {
                continue;
            }
            $out[] = [
                'sku_anita' => $codigo,
                'saldo' => $row['saldo'],
                'articulo_id' => $art['id'],
                'sku' => $art['sku'],
                'descripcion' => $art['descripcion'],
            ];
        }

        usort($out, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['descripcion'] ?? $a['sku'] ?? ''), (string) ($b['descripcion'] ?? $b['sku'] ?? ''));
        });

        return $out;
    }

    /**
     * Saldos stkdep por artículo para filas de listado (p. ej. índice de artículos).
     * Solo artículos con depósito de entrega y SKU con prefijo LAB (laboratorio).
     *
     * @param  iterable<object{id: int|string, codigoarticulo?: string|null, sku?: string|null, depositoentrega_id?: int|string|null}>  $articulos
     * @return array<int, float> articulo_id => saldo
     */
    public function saldosStkdepPorArticulosLab(iterable $articulos): array
    {
        /** @var array<int, array<string, true>> $codigosPorDeposito */
        $codigosPorDeposito = [];
        /** @var array<int, array<string, int>> $articuloIdPorDepositoYCodigo */
        $articuloIdPorDepositoYCodigo = [];

        foreach ($articulos as $articulo) {
            $depositoId = (int) ($articulo->depositoentrega_id ?? 0);
            if ($depositoId <= 0) {
                continue;
            }

            $sku = trim((string) ($articulo->codigoarticulo ?? $articulo->sku ?? ''));
            if ($sku === '' || ! $this->esSkuLaboratorioLab($sku)) {
                continue;
            }

            $codigoAnita = $this->codigoAnitaDesdeSku($sku);
            $codigosPorDeposito[$depositoId][$codigoAnita] = true;
            $articuloIdPorDepositoYCodigo[$depositoId][$codigoAnita] = (int) $articulo->id;
        }

        if ($codigosPorDeposito === []) {
            return [];
        }

        $saldos = [];
        foreach ($codigosPorDeposito as $depositoId => $codigosSet) {
            $deposito = Depmae::query()->find($depositoId);
            if (! $deposito || trim((string) $deposito->codigo) === '') {
                continue;
            }

            $codigoDeposito = str_replace("'", "''", trim((string) $deposito->codigo));
            $codigosAnita = array_keys($codigosSet);
            $porCodigoAnita = $this->consultarStkdepPorDeposito($codigoDeposito, $codigosAnita);

            foreach ($porCodigoAnita as $codigo => $row) {
                $articuloId = $articuloIdPorDepositoYCodigo[$depositoId][$codigo] ?? null;
                if ($articuloId !== null) {
                    $saldos[$articuloId] = $row['saldo'];
                }
            }
        }

        return $saldos;
    }

    public function esSkuLaboratorioLab(string $sku): bool
    {
        return str_starts_with(strtoupper(trim($sku)), 'LAB');
    }

    /**
     * Códigos Anita (13 pos.) de artículos ERP con depósito de entrega = depósito consultado.
     *
     * @return list<string>
     */
    private function codigosAnitaPorDepositoEntrega(int $depositoId): array
    {
        $skus = ArticuloSeleccionOperativaSupport::aplicarSoloActivosTablaArticulo(
            Articulo::query()
                ->where('depositoentrega_id', $depositoId)
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
        )->pluck('sku');

        $codigos = [];
        foreach ($skus as $sku) {
            $codigos[$this->codigoAnitaDesdeSku((string) $sku)] = true;
        }

        return array_keys($codigos);
    }

    /**
     * @param  list<string>  $codigosAnita
     * @return array<string, array{sku_anita: string, saldo: float}>
     */
    private function consultarStkdepPorDeposito(string $codigoDeposito, array $codigosAnita): array
    {
        $porCodigoAnita = [];

        foreach (array_chunk($codigosAnita, self::IN_CHUNK_SIZE) as $chunk) {
            $whereArmado = ' WHERE '.self::CAMPO_DEPOSITO." = '".$codigoDeposito."' "
                .' AND '.self::CAMPO_CANTIDAD.' > 0 '
                .$this->clausulaInArticulos($chunk);

            $payload = [
                'acc' => 'list',
                'tabla' => self::TABLA,
                'campos' => self::CAMPO_ARTICULO.','.self::CAMPO_DEPOSITO.','.self::CAMPO_CANTIDAD,
                'whereArmado' => $whereArmado,
                'orderBy' => self::CAMPO_ARTICULO,
            ];

            try {
                $respuesta = $this->apiAnita->apiCallEscritura($payload);
            } catch (\Throwable $e) {
                Log::warning('StkdepSaldoAnita: error ApiAnita', ['exception' => $e->getMessage()]);

                throw new \RuntimeException('No se pudo consultar el stock en Anita.');
            }

            $errorBridge = ApiAnita::extraerMensajeError($respuesta === false ? null : (string) $respuesta);
            if ($errorBridge !== null) {
                Log::warning('StkdepSaldoAnita: error bridge', ['mensaje' => $errorBridge]);

                throw new \RuntimeException($errorBridge);
            }

            $filasObj = ApiAnita::decodificarListaFilas($respuesta === false ? null : (string) $respuesta);

            foreach ($filasObj as $fila) {
                $row = get_object_vars($fila);
                $codigo = trim((string) ($row[self::CAMPO_ARTICULO] ?? ''));
                if ($codigo === '') {
                    continue;
                }
                $cantidad = (float) ($row[self::CAMPO_CANTIDAD] ?? 0);
                if ($cantidad <= 0) {
                    continue;
                }
                $existente = $porCodigoAnita[$codigo]['saldo'] ?? 0;
                if ($cantidad > $existente) {
                    $porCodigoAnita[$codigo] = [
                        'sku_anita' => $codigo,
                        'saldo' => $cantidad,
                    ];
                }
            }
        }

        return $porCodigoAnita;
    }

    /**
     * @param  list<string>  $codigosAnita
     */
    private function clausulaInArticulos(array $codigosAnita): string
    {
        if ($codigosAnita === []) {
            return '';
        }

        $literales = [];
        foreach ($codigosAnita as $codigo) {
            $literales[] = "'".str_replace("'", "''", $codigo)."'";
        }

        return ' AND '.self::CAMPO_ARTICULO.' IN ('.implode(',', $literales).') ';
    }

    /**
     * @param  list<string>  $codigosAnita
     * @return array<string, array{id: int, sku: string, descripcion: string}>
     */
    private function resolverArticulosErp(array $codigosAnita, int $depositoEntregaId): array
    {
        $variantes = [];
        foreach ($codigosAnita as $codigo) {
            $variantes[$codigo] = true;
            $sinCeros = ltrim($codigo, '0');
            if ($sinCeros !== '') {
                $variantes[$sinCeros] = true;
            }
        }

        $skusBuscar = array_keys($variantes);
        if ($skusBuscar === []) {
            return [];
        }

        $articulos = ArticuloSeleccionOperativaSupport::aplicarSoloActivosTablaArticulo(
            Articulo::query()
                ->select('id', 'sku', 'descripcion', 'mventa_id')
                ->where('depositoentrega_id', $depositoEntregaId)
                ->whereIn('sku', $skusBuscar)
        )->get();

        $porSku = [];
        foreach ($articulos as $articulo) {
            $sku = trim((string) $articulo->sku);
            $porSku[$sku] = [
                'id' => (int) $articulo->id,
                'sku' => $sku,
                'descripcion' => (string) $articulo->descripcion,
                'mventa_id' => $articulo->mventa_id,
            ];
            $porSku[$this->codigoAnitaDesdeSku($sku)] = $porSku[$sku];
        }

        $out = [];
        foreach ($codigosAnita as $codigo) {
            if (isset($porSku[$codigo])) {
                $out[$codigo] = $porSku[$codigo];
            } elseif (isset($porSku[ltrim($codigo, '0')])) {
                $out[$codigo] = $porSku[ltrim($codigo, '0')];
            }
        }

        return $out;
    }
}
