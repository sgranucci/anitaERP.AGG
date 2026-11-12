<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Support\Database\DbContencionSupport;
use RuntimeException;

/**
 * Unicidad de factura de proveedor (cualquier origen), **en todo el grupo**:
 * CUIT proveedor + código AFIP (01/02/…) + letra + sucursal + número + marca FCE.
 * No importa la empresa: la misma factura del mismo proveedor no se puede cargar dos veces
 * (caso Biyemas/Kandiko). El parámetro $empresaId solo prioriza el match local en el mensaje.
 * Una factura común y una FCE del mismo punto de venta y número son dos comprobantes
 * (Anita las separa con com_es_fce). Si $esFce es null, no se filtra la marca: choca con cualquiera.
 * Si el código de autorización es CAE/CAI (no CAEA), también se controla CUIT + número CAE.
 * La precarga no tiene marca FCE: ese control sigue sin distinguir las dos series.
 */
final class ComprobanteProveedorUnicidadSupport
{
    public static function normalizarCuitDigitos(?string $cuit): string
    {
        $digits = preg_replace('/\D/', '', trim((string) $cuit)) ?? '';

        return strlen($digits) === 11 ? $digits : '';
    }

    public static function normalizarCodigoAfip(?string $codigo): string
    {
        $digits = preg_replace('/\D/', '', trim((string) $codigo)) ?? '';
        if ($digits === '') {
            return '';
        }

        return (string) (int) $digits;
    }

    /** Marca Anita/ERP: S, 1 o true. N, vacío y null no son FCE. */
    public static function esMarcaFce(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }
        if (is_int($valor) || is_float($valor)) {
            return (int) $valor === 1;
        }

        $texto = strtoupper(trim((string) $valor));

        return $texto === 'S' || $texto === '1' || $texto === 'TRUE';
    }

    public static function normalizarNumeroCae(?string $numerocae): string
    {
        return preg_replace('/\D/', '', trim((string) $numerocae)) ?? '';
    }

    public static function resolverCuitDigitos(?int $proveedorId, ?string $documentoEventual): string
    {
        if ($proveedorId !== null && $proveedorId > 0) {
            $nro = Proveedor::query()->whereKey($proveedorId)->value('nroinscripcion');

            return self::normalizarCuitDigitos(is_string($nro) ? $nro : null);
        }

        return self::normalizarCuitDigitos($documentoEventual);
    }

    public static function cuitDesdeComprobante(Comprobante_Proveedor $comprobante): string
    {
        $comprobante->loadMissing('proveedores');
        $doc = $comprobante->proveedores?->nroinscripcion ?? $comprobante->proveedor_documento_eventual;

        return self::normalizarCuitDigitos(is_string($doc) ? $doc : null);
    }

    public static function codigoAfipDesdeTipoId(int $tipotransaccionCompraId): string
    {
        if ($tipotransaccionCompraId <= 0) {
            return '';
        }

        $codigo = Tipotransaccion_Compra::query()->whereKey($tipotransaccionCompraId)->value('codigoafip');

        return self::normalizarCodigoAfip(is_string($codigo) ? $codigo : null);
    }

    /** @var array<string, list<int>> */
    private static array $cacheTiposPorAfip = [];

    /** @return list<int> */
    public static function tipotransaccionIdsPorCodigoAfip(string $codigoAfipNorm): array
    {
        if ($codigoAfipNorm === '') {
            return [];
        }

        if (isset(self::$cacheTiposPorAfip[$codigoAfipNorm])) {
            return self::$cacheTiposPorAfip[$codigoAfipNorm];
        }

        self::$cacheTiposPorAfip[$codigoAfipNorm] = Tipotransaccion_Compra::query()
            ->get(['id', 'codigoafip'])
            ->filter(fn ($t) => self::normalizarCodigoAfip((string) $t->codigoafip) === $codigoAfipNorm)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return self::$cacheTiposPorAfip[$codigoAfipNorm];
    }

    /**
     * @throws RuntimeException
     */
    public static function assertUnico(
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        ?int $proveedorId,
        ?string $documentoEventual,
        ?int $excluirComprobanteId = null,
        ?int $excluirPrecargaId = null,
        ?string $numerocae = null,
        ?string $tipoAutorizacion = null,
        ?bool $esFce = null,
    ): void {
        $cuit = self::resolverCuitDigitos($proveedorId, $documentoEventual);
        if ($cuit === '') {
            throw new RuntimeException(
                'Debe indicar un CUIT válido (11 dígitos) del proveedor para registrar el comprobante.'
            );
        }

        $codigoAfip = self::codigoAfipDesdeTipoId($tipotransaccionCompraId);
        if ($codigoAfip === '') {
            throw new RuntimeException(
                'El tipo de comprobante no tiene código AFIP configurado; no se puede controlar la unicidad fiscal.'
            );
        }

        $duplicado = self::findDuplicadoPorAfip(
            $empresaId,
            $codigoAfip,
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuit,
            $excluirComprobanteId,
            $esFce,
        );

        if ($duplicado !== null) {
            throw ComprobanteProveedorDuplicadoException::desdeExistente($duplicado, $codigoAfip);
        }

        $duplicadoClave = self::findDuplicadoPorClaveUnica(
            $empresaId,
            $tipotransaccionCompraId,
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuit,
            $excluirComprobanteId,
            $esFce,
        );
        if ($duplicadoClave !== null) {
            throw ComprobanteProveedorDuplicadoException::desdeExistente($duplicadoClave, $codigoAfip);
        }

        $duplicadoPrecarga = self::findDuplicadoPrecargaPorAfip(
            $empresaId,
            $codigoAfip,
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuit,
            $excluirPrecargaId,
        );

        if ($duplicadoPrecarga !== null) {
            throw new RuntimeException(self::mensajeDuplicadoPrecarga($duplicadoPrecarga, $codigoAfip));
        }

        self::assertUnicoPorCae(
            $empresaId,
            $cuit,
            $numerocae,
            $tipoAutorizacion,
            $excluirComprobanteId,
            $excluirPrecargaId,
        );
    }

    /**
     * Valida unicidad al grabar precarga (API agente IA / pantalla precarga) contra precarga y comprobante definitivo.
     *
     * @throws RuntimeException
     */
    public static function assertUnicoPrecarga(
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        int $proveedorId,
        ?int $excluirPrecargaId = null,
        ?string $numerocae = null,
        ?string $tipoAutorizacion = null,
        ?bool $esFce = null,
    ): void {
        self::assertUnico(
            $empresaId,
            $tipotransaccionCompraId,
            $letra,
            $sucursal,
            $numerocomprobante,
            $proveedorId,
            null,
            null,
            $excluirPrecargaId,
            $numerocae,
            $tipoAutorizacion,
            $esFce,
        );
    }

    /**
     * @throws RuntimeException
     */
    public static function assertUnicoPorCae(
        int $empresaId,
        string $cuitDigitos,
        ?string $numerocae,
        ?string $tipoAutorizacion,
        ?int $excluirComprobanteId = null,
        ?int $excluirPrecargaId = null,
    ): void {
        if (! ComprobanteProveedorTipoAutorizacion::controlaUnicidadCodigo($tipoAutorizacion, $numerocae)) {
            return;
        }

        $cae = self::normalizarNumeroCae($numerocae);
        $cuitDigitos = self::normalizarCuitDigitos($cuitDigitos);
        if ($cae === '' || $cuitDigitos === '') {
            return;
        }

        $dupCp = self::findDuplicadoPorCae($empresaId, $cuitDigitos, $cae, $excluirComprobanteId);
        if ($dupCp !== null) {
            throw new RuntimeException(self::mensajeDuplicadoCaeComprobante($dupCp, $cae));
        }

        $dupPre = self::findDuplicadoPrecargaPorCae($empresaId, $cuitDigitos, $cae, $excluirPrecargaId);
        if ($dupPre !== null) {
            throw new RuntimeException(self::mensajeDuplicadoCaePrecarga($dupPre, $cae));
        }
    }

    public static function findDuplicadoPorAfip(
        int $empresaId,
        string $codigoAfipNorm,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        string $cuitDigitos,
        ?int $excluirComprobanteId = null,
        ?bool $esFce = null,
    ): ?Comprobante_Proveedor {
        $cuitDigitos = self::normalizarCuitDigitos($cuitDigitos);
        $codigoAfipNorm = self::normalizarCodigoAfip($codigoAfipNorm);
        $tipoIds = self::tipotransaccionIdsPorCodigoAfip($codigoAfipNorm);
        if ($cuitDigitos === '' || $codigoAfipNorm === '' || $tipoIds === []) {
            return null;
        }

        $letra = strtoupper(substr(trim($letra), 0, 1));

        $query = Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'proveedores', 'empresas'])
            ->whereIn('tipotransaccion_compra_id', $tipoIds)
            ->where('letra', $letra)
            ->where('sucursal', $sucursal)
            ->where('numerocomprobante', $numerocomprobante)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhere('estado', '!=', ComprobanteProveedorEstados::ANULADO);
            });
        self::aplicarFiltroEsFce($query, $esFce);
        $query->where(function ($q) use ($cuitDigitos): void {
            $q->where('identificacion_proveedor_cuit', $cuitDigitos)
                ->orWhereHas('proveedores', function ($p) use ($cuitDigitos): void {
                    $p->whereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), ' ', ''), '.', ''), '/', '') = ?",
                        [$cuitDigitos],
                    );
                })
                ->orWhere('proveedor_documento_eventual', $cuitDigitos)
                ->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(proveedor_documento_eventual, '-', ''), ' ', ''), '.', '') = ?",
                    [$cuitDigitos],
                );
        });

        if ($excluirComprobanteId !== null && $excluirComprobanteId > 0) {
            $query->where('id', '!=', $excluirComprobanteId);
        }

        self::priorizarEmpresaLocal($query, $empresaId);

        return $query->first();
    }

    /** Compat: búsqueda por tipo interno (delega a AFIP). */
    public static function findDuplicado(
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        string $cuitDigitos,
        ?int $excluirComprobanteId = null,
        ?bool $esFce = null,
    ): ?Comprobante_Proveedor {
        return self::findDuplicadoPorAfip(
            $empresaId,
            self::codigoAfipDesdeTipoId($tipotransaccionCompraId),
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuitDigitos,
            $excluirComprobanteId,
            $esFce,
        );
    }

    /**
     * Consulta temprana (UI) sin lanzar: ERP (comprobante) y precarga.
     * Anita se consulta aparte (bridge).
     *
     * @return array{
     *   listo: bool,
     *   duplicado: bool,
     *   fuente: 'erp'|'precarga'|null,
     *   titulo?: string|null,
     *   mensaje: string|null,
     *   comprobante_id: int|null,
     *   precarga_id: int|null,
     *   codigo_afip: string|null
     * }
     */
    public static function consultarDuplicadoCabecera(
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        ?int $proveedorId,
        ?string $documentoEventual = null,
        ?int $excluirComprobanteId = null,
        ?int $excluirPrecargaId = null,
        ?bool $esFce = null,
    ): array {
        $vacio = [
            'listo' => false,
            'duplicado' => false,
            'fuente' => null,
            'mensaje' => null,
            'comprobante_id' => null,
            'precarga_id' => null,
            'codigo_afip' => null,
        ];

        $letraNorm = strtoupper(substr(trim($letra), 0, 1));
        if ($empresaId <= 0
            || $tipotransaccionCompraId <= 0
            || $letraNorm === ''
            || $sucursal < 0
            || $numerocomprobante <= 0
        ) {
            return $vacio;
        }

        $cuit = self::resolverCuitDigitos($proveedorId, $documentoEventual);
        $codigoAfip = self::codigoAfipDesdeTipoId($tipotransaccionCompraId);
        if ($cuit === '' || $codigoAfip === '') {
            return $vacio;
        }

        $duplicado = self::findDuplicadoPorAfip(
            $empresaId,
            $codigoAfip,
            $letraNorm,
            $sucursal,
            $numerocomprobante,
            $cuit,
            $excluirComprobanteId,
            $esFce,
        );
        if ($duplicado === null) {
            $duplicado = self::findDuplicadoPorClaveUnica(
                $empresaId,
                $tipotransaccionCompraId,
                $letraNorm,
                $sucursal,
                $numerocomprobante,
                $cuit,
                $excluirComprobanteId,
                $esFce,
            );
        }
        if ($duplicado !== null) {
            return [
                'listo' => true,
                'duplicado' => true,
                'fuente' => 'erp',
                'titulo' => self::tituloDuplicado($duplicado),
                'mensaje' => self::mensajeDuplicado($duplicado, $codigoAfip),
                'comprobante_id' => (int) $duplicado->id,
                'precarga_id' => null,
                'codigo_afip' => $codigoAfip,
            ];
        }

        $dupPrecarga = self::findDuplicadoPrecargaPorAfip(
            $empresaId,
            $codigoAfip,
            $letraNorm,
            $sucursal,
            $numerocomprobante,
            $cuit,
            $excluirPrecargaId,
        );
        if ($dupPrecarga !== null) {
            return [
                'listo' => true,
                'duplicado' => true,
                'fuente' => 'precarga',
                'mensaje' => self::mensajeDuplicadoPrecarga($dupPrecarga, $codigoAfip),
                'comprobante_id' => null,
                'precarga_id' => (int) $dupPrecarga->id,
                'codigo_afip' => $codigoAfip,
            ];
        }

        return [
            'listo' => true,
            'duplicado' => false,
            'fuente' => null,
            'mensaje' => null,
            'comprobante_id' => null,
            'precarga_id' => null,
            'codigo_afip' => $codigoAfip,
        ];
    }

    /**
     * Misma clave fiscal en otra empresa del grupo (aviso; no bloquea unicidad local).
     *
     * @return list<Comprobante_Proveedor>
     */
    public static function findEnOtrasEmpresasPorAfip(
        int $empresaId,
        string $codigoAfipNorm,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        string $cuitDigitos,
        ?int $excluirComprobanteId = null,
        ?bool $esFce = null,
        int $limite = 5,
    ): array {
        $cuitDigitos = self::normalizarCuitDigitos($cuitDigitos);
        $codigoAfipNorm = self::normalizarCodigoAfip($codigoAfipNorm);
        $tipoIds = self::tipotransaccionIdsPorCodigoAfip($codigoAfipNorm);
        if ($empresaId <= 0 || $cuitDigitos === '' || $codigoAfipNorm === '' || $tipoIds === []) {
            return [];
        }

        $letra = strtoupper(substr(trim($letra), 0, 1));

        $query = Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'proveedores', 'empresas'])
            ->where('empresa_id', '!=', $empresaId)
            ->whereIn('tipotransaccion_compra_id', $tipoIds)
            ->where('letra', $letra)
            ->where('sucursal', $sucursal)
            ->where('numerocomprobante', $numerocomprobante)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhere('estado', '!=', ComprobanteProveedorEstados::ANULADO);
            });
        self::aplicarFiltroEsFce($query, $esFce);
        $query->where(function ($q) use ($cuitDigitos): void {
            $q->where('identificacion_proveedor_cuit', $cuitDigitos)
                ->orWhereHas('proveedores', function ($p) use ($cuitDigitos): void {
                    $p->whereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), ' ', ''), '.', ''), '/', '') = ?",
                        [$cuitDigitos],
                    );
                })
                ->orWhere('proveedor_documento_eventual', $cuitDigitos)
                ->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(proveedor_documento_eventual, '-', ''), ' ', ''), '.', '') = ?",
                    [$cuitDigitos],
                );
        });

        if ($excluirComprobanteId !== null && $excluirComprobanteId > 0) {
            $query->where('id', '!=', $excluirComprobanteId);
        }

        return $query->orderByDesc('id')->limit(max(1, $limite))->get()->all();
    }

    /**
     * Consulta temprana (UI) y al grabar: aviso intercompany sin bloquear.
     *
     * @return array{
     *   listo: bool,
     *   hay_aviso: bool,
     *   mensaje: string|null,
     *   titulo: string|null,
     *   comprobantes: list<array{id: int, empresa_id: int, empresa: string, total: float|null, estado: string|null}>
     * }
     */
    public static function consultarAvisoIntercompanyCabecera(
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        ?int $proveedorId,
        ?string $documentoEventual = null,
        ?int $excluirComprobanteId = null,
        ?bool $esFce = null,
    ): array {
        $vacio = [
            'listo' => false,
            'hay_aviso' => false,
            'mensaje' => null,
            'titulo' => null,
            'comprobantes' => [],
        ];

        $letraNorm = strtoupper(substr(trim($letra), 0, 1));
        if ($empresaId <= 0
            || $tipotransaccionCompraId <= 0
            || $letraNorm === ''
            || $sucursal < 0
            || $numerocomprobante <= 0
        ) {
            return $vacio;
        }

        $cuit = self::resolverCuitDigitos($proveedorId, $documentoEventual);
        $codigoAfip = self::codigoAfipDesdeTipoId($tipotransaccionCompraId);
        if ($cuit === '' || $codigoAfip === '') {
            return $vacio;
        }

        $otros = self::findEnOtrasEmpresasPorAfip(
            $empresaId,
            $codigoAfip,
            $letraNorm,
            $sucursal,
            $numerocomprobante,
            $cuit,
            $excluirComprobanteId,
            $esFce,
        );
        if ($otros === []) {
            return [
                'listo' => true,
                'hay_aviso' => false,
                'mensaje' => null,
                'titulo' => null,
                'comprobantes' => [],
            ];
        }

        $detalle = [];
        $comprobantes = [];
        foreach ($otros as $cp) {
            $empresaNombre = trim((string) ($cp->empresas?->nombre ?? ''));
            if ($empresaNombre === '') {
                $empresaNombre = 'empresa #'.(int) $cp->empresa_id;
            }
            $total = isset($cp->total) ? (float) $cp->total : null;
            $totalTxt = $total !== null
                ? '$ '.number_format($total, 2, ',', '.')
                : 'sin total';
            $detalle[] = sprintf(
                '%s (comprobante #%d, %s)',
                $empresaNombre,
                (int) $cp->id,
                $totalTxt,
            );
            $comprobantes[] = [
                'id' => (int) $cp->id,
                'empresa_id' => (int) $cp->empresa_id,
                'empresa' => $empresaNombre,
                'total' => $total,
                'estado' => $cp->estado !== null ? (string) $cp->estado : null,
            ];
        }

        $factura = self::rotuloFactura(
            (string) ($otros[0]->tipotransaccion_compras?->abreviatura ?? 'FAC'),
            $letraNorm,
            $sucursal,
            $numerocomprobante,
        );

        return [
            'listo' => true,
            'hay_aviso' => true,
            'titulo' => 'Misma factura en otra empresa',
            'mensaje' => sprintf(
                'La factura %s del CUIT %s ya está cargada en otra empresa del grupo: %s. '
                .'Verifique que no esté duplicando el pago del mismo comprobante fiscal.',
                $factura,
                self::formatearCuit($cuit),
                implode('; ', $detalle),
            ),
            'comprobantes' => $comprobantes,
        ];
    }

    /**
     * Misma clave fiscal en cualquier empresa (tipo interno + letra + sucursal + número + CUIT + FCE).
     * El índice DB aún incluye empresa_id; el bloqueo de negocio es global vía esta búsqueda.
     */
    public static function findDuplicadoPorClaveUnica(
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        string $cuitDigitos,
        ?int $excluirComprobanteId = null,
        ?bool $esFce = null,
    ): ?Comprobante_Proveedor {
        $cuitDigitos = self::normalizarCuitDigitos($cuitDigitos);
        if ($cuitDigitos === '' || $tipotransaccionCompraId <= 0) {
            return null;
        }

        $letra = strtoupper(substr(trim($letra), 0, 1));

        $query = Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'proveedores', 'empresas'])
            ->where('tipotransaccion_compra_id', $tipotransaccionCompraId)
            ->where('letra', $letra)
            ->where('sucursal', $sucursal)
            ->where('numerocomprobante', $numerocomprobante)
            ->where('identificacion_proveedor_cuit', $cuitDigitos);
        self::aplicarFiltroEsFce($query, $esFce);

        if ($excluirComprobanteId !== null && $excluirComprobanteId > 0) {
            $query->where('id', '!=', $excluirComprobanteId);
        }

        self::priorizarEmpresaLocal($query, $empresaId);

        return $query->first();
    }

    /**
     * Convierte violación de índice único (carrera) en mensaje de negocio.
     *
     * @throws ComprobanteProveedorDuplicadoException
     */
    public static function relevarViolacionUnicidad(
        \Throwable $e,
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        ?int $proveedorId,
        ?string $documentoEventual = null,
        ?int $excluirComprobanteId = null,
        ?bool $esFce = null,
    ): void {
        if (! self::esViolacionUnicidadFiscal($e)) {
            return;
        }

        $cuit = self::resolverCuitDigitos($proveedorId, $documentoEventual);
        $dup = self::findDuplicadoPorClaveUnica(
            $empresaId,
            $tipotransaccionCompraId,
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuit,
            $excluirComprobanteId,
            $esFce,
        );

        if ($dup !== null) {
            throw ComprobanteProveedorDuplicadoException::desdeExistente(
                $dup,
                self::codigoAfipDesdeTipoId($tipotransaccionCompraId),
            );
        }

        throw new RuntimeException(
            'Ya existe un comprobante con el mismo tipo, letra, sucursal, número, CUIT y marca FCE '
            .'(en cualquier empresa del grupo). Buscalo en Cuentas a pagar.'
        );
    }

    /**
     * Prioriza el match de la empresa del formulario; si no hay, cualquier otra del grupo.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function priorizarEmpresaLocal($query, int $empresaId): void
    {
        if ($empresaId > 0) {
            $query->orderByRaw('CASE WHEN empresa_id = ? THEN 0 ELSE 1 END', [$empresaId]);
        }
        $query->orderByDesc('id');
    }

    private static function rotuloEmpresaDesdeRelacion(?string $nombre, int $empresaId): string
    {
        $nombre = trim((string) $nombre);
        if ($nombre !== '') {
            return $nombre;
        }

        return $empresaId > 0 ? 'empresa #'.$empresaId : 'otra empresa';
    }

    public static function esViolacionUnicidadFiscal(\Throwable $e): bool
    {
        // Solo el índice fiscal: no usar la tabla como pista (cualquier UNIQUE de
        // comprobante_proveedor —p.ej. precarga_id— también contiene ese nombre).
        return DbContencionSupport::esViolacionUnicidad(
            $e,
            'uq_comprobante_proveedor_por_cuit',
        );
    }

    public static function esViolacionUnicidadPrecarga(\Throwable $e): bool
    {
        return DbContencionSupport::esViolacionUnicidad(
            $e,
            'uq_precarga_comprobante_proveedor_por_afip',
        );
    }

    /**
     * Traduce la violación del índice único de precarga al mensaje de negocio (devuelve null si el
     * error es otro). El chequeo previo puede quedar obsoleto entre el SELECT y el INSERT, así que
     * este es el que ve el operador cuando dos orígenes cargan la misma factura a la vez.
     */
    public static function mensajeViolacionUnicidadPrecarga(
        \Throwable $e,
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        ?int $proveedorId,
        ?string $documentoEventual = null,
        ?int $excluirPrecargaId = null,
    ): ?string {
        if (! self::esViolacionUnicidadPrecarga($e)) {
            return null;
        }

        $codigoAfip = self::codigoAfipDesdeTipoId($tipotransaccionCompraId);
        $duplicado = self::findDuplicadoPrecargaPorAfip(
            $empresaId,
            $codigoAfip,
            $letra,
            $sucursal,
            $numerocomprobante,
            self::resolverCuitDigitos($proveedorId, $documentoEventual),
            $excluirPrecargaId,
        );

        if ($duplicado !== null) {
            return self::mensajeDuplicadoPrecarga($duplicado, $codigoAfip);
        }

        return 'Factura duplicada: ya existe una precarga con el mismo tipo AFIP, letra, '
            .'sucursal, número y CUIT (en cualquier empresa). Buscala en el legajo o en precargas.';
    }

    public static function findDuplicadoPrecargaPorAfip(
        int $empresaId,
        string $codigoAfipNorm,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        string $cuitDigitos,
        ?int $excluirPrecargaId = null,
    ): ?Precarga_Comprobante_Proveedor {
        $cuitDigitos = self::normalizarCuitDigitos($cuitDigitos);
        $codigoAfipNorm = self::normalizarCodigoAfip($codigoAfipNorm);
        $tipoIds = self::tipotransaccionIdsPorCodigoAfip($codigoAfipNorm);
        if ($cuitDigitos === '' || $codigoAfipNorm === '' || $tipoIds === []) {
            return null;
        }

        $letra = strtoupper(substr(trim($letra), 0, 1));

        $query = Precarga_Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'proveedores', 'empresas'])
            ->whereIn('tipotransaccion_compra_id', $tipoIds)
            ->where('letra', $letra)
            ->where('sucursal', $sucursal)
            ->where('numerocomprobante', $numerocomprobante)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->where(function ($q) use ($cuitDigitos): void {
                $q->where('identificacion_proveedor_cuit', $cuitDigitos)
                    ->orWhereHas('proveedores', function ($p) use ($cuitDigitos): void {
                        $p->whereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), ' ', ''), '.', ''), '/', '') = ?",
                            [$cuitDigitos],
                        );
                    });
            });

        if ($excluirPrecargaId !== null && $excluirPrecargaId > 0) {
            $query->where('id', '!=', $excluirPrecargaId);
        }

        self::priorizarEmpresaLocal($query, $empresaId);

        return $query->first();
    }

    public static function findDuplicadoPrecarga(
        int $empresaId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        string $cuitDigitos,
        ?int $excluirPrecargaId = null,
    ): ?Precarga_Comprobante_Proveedor {
        return self::findDuplicadoPrecargaPorAfip(
            $empresaId,
            self::codigoAfipDesdeTipoId($tipotransaccionCompraId),
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuitDigitos,
            $excluirPrecargaId,
        );
    }

    public static function findDuplicadoPorCae(
        int $empresaId,
        string $cuitDigitos,
        string $caeDigitos,
        ?int $excluirComprobanteId = null,
    ): ?Comprobante_Proveedor {
        $query = Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'proveedores', 'empresas'])
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhere('estado', '!=', ComprobanteProveedorEstados::ANULADO);
            })
            ->where(function ($q) {
                $q->whereNull('tipo_autorizacion')
                    ->orWhere('tipo_autorizacion', '!=', ComprobanteProveedorTipoAutorizacion::CAEA);
            })
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(numerocae,''), '-', ''), ' ', ''), '.', ''), '/', '') = ?",
                [$caeDigitos],
            )
            ->where(function ($q) use ($cuitDigitos): void {
                $q->where('identificacion_proveedor_cuit', $cuitDigitos)
                    ->orWhereHas('proveedores', function ($p) use ($cuitDigitos): void {
                        $p->whereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), ' ', ''), '.', ''), '/', '') = ?",
                            [$cuitDigitos],
                        );
                    });
            });

        if ($excluirComprobanteId !== null && $excluirComprobanteId > 0) {
            $query->where('id', '!=', $excluirComprobanteId);
        }

        self::priorizarEmpresaLocal($query, $empresaId);

        return $query->first();
    }

    public static function findDuplicadoPrecargaPorCae(
        int $empresaId,
        string $cuitDigitos,
        string $caeDigitos,
        ?int $excluirPrecargaId = null,
    ): ?Precarga_Comprobante_Proveedor {
        $query = Precarga_Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'proveedores', 'empresas'])
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->where(function ($q) {
                $q->whereNull('tipo_autorizacion')
                    ->orWhere('tipo_autorizacion', '!=', ComprobanteProveedorTipoAutorizacion::CAEA);
            })
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(numerocae,''), '-', ''), ' ', ''), '.', ''), '/', '') = ?",
                [$caeDigitos],
            )
            ->where(function ($q) use ($cuitDigitos): void {
                $q->where('identificacion_proveedor_cuit', $cuitDigitos)
                    ->orWhereHas('proveedores', function ($p) use ($cuitDigitos): void {
                        $p->whereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), ' ', ''), '.', ''), '/', '') = ?",
                            [$cuitDigitos],
                        );
                    });
            });

        if ($excluirPrecargaId !== null && $excluirPrecargaId > 0) {
            $query->where('id', '!=', $excluirPrecargaId);
        }

        self::priorizarEmpresaLocal($query, $empresaId);

        return $query->first();
    }

    public static function mensajeDuplicadoPrecarga(
        Precarga_Comprobante_Proveedor $existente,
        ?string $codigoAfip = null,
    ): string {
        $existente->loadMissing('tipotransaccion_compras', 'proveedores', 'empresas');

        $factura = self::rotuloFactura(
            (string) ($existente->tipotransaccion_compras?->abreviatura ?? 'FAC'),
            (string) $existente->letra,
            $existente->sucursal,
            $existente->numerocomprobante,
        );
        $deQuien = self::rotuloProveedorCuit(
            trim((string) ($existente->proveedores?->nombre ?? '')),
            self::normalizarCuitDigitos($existente->identificacion_proveedor_cuit ?? $existente->proveedores?->nroinscripcion),
        );
        $enEmpresa = self::rotuloEmpresaDesdeRelacion($existente->empresas?->nombre ?? null, (int) ($existente->empresa_id ?? 0));

        return sprintf(
            'La factura %s%s ya está en la precarga #%d de %s. No la cargues de nuevo en ninguna empresa: abrí esa precarga.',
            $factura,
            $deQuien,
            $existente->id,
            $enEmpresa,
        );
    }

    public static function tituloDuplicado(Comprobante_Proveedor $existente): string
    {
        $estado = strtoupper(trim((string) ($existente->estado ?? '')));

        return $estado === ComprobanteProveedorEstados::CONTABILIZADO
            ? 'Esta factura ya está contabilizada (cualquier empresa)'
            : 'Esta factura ya está cargada (cualquier empresa)';
    }

    public static function mensajeDuplicado(Comprobante_Proveedor $existente, ?string $codigoAfip = null): string
    {
        $existente->loadMissing('tipotransaccion_compras', 'proveedores', 'empresas');

        $factura = self::rotuloFactura(
            (string) ($existente->tipotransaccion_compras?->abreviatura ?? 'FAC'),
            (string) $existente->letra,
            $existente->sucursal,
            $existente->numerocomprobante,
        );
        if ($existente->es_fce) {
            $factura .= ' (FCE)';
        }
        $deQuien = self::rotuloProveedorCuit(
            trim((string) ($existente->proveedores?->nombre ?? '')),
            self::cuitDesdeComprobante($existente),
        );
        $enEmpresa = self::rotuloEmpresaDesdeRelacion($existente->empresas?->nombre ?? null, (int) ($existente->empresa_id ?? 0));
        $estado = strtoupper(trim((string) ($existente->estado ?? '')));
        $id = (int) $existente->id;

        if ($estado === ComprobanteProveedorEstados::CONTABILIZADO) {
            return sprintf(
                'La factura %s%s ya está contabilizada en %s (comprobante #%d). '
                .'No se puede cargar de nuevo en ninguna empresa del grupo.',
                $factura,
                $deQuien,
                $enEmpresa,
                $id,
            );
        }

        if ($estado === ComprobanteProveedorEstados::ANULADO) {
            return sprintf(
                'La factura %s%s ya figuró en %s como comprobante #%d, que está anulado. '
                .'Revisá ese comprobante antes de cargarla otra vez.',
                $factura,
                $deQuien,
                $enEmpresa,
                $id,
            );
        }

        $texto = sprintf(
            'La factura %s%s ya está cargada en %s (comprobante #%d) y %s. '
            .'No se puede cargar de nuevo en ninguna empresa. Abrí ese comprobante.',
            $factura,
            $deQuien,
            $enEmpresa,
            $id,
            self::situacionNoContabilizada($estado),
        );

        $motivo = trim((string) ($existente->anita_sync_error ?? ''));
        if ($motivo !== '') {
            $texto .= ' No se contabilizó por: '.$motivo;
        }

        return $texto;
    }

    public static function mensajeDuplicadoCaeComprobante(Comprobante_Proveedor $existente, string $cae): string
    {
        $existente->loadMissing('tipotransaccion_compras', 'empresas');
        $enEmpresa = self::rotuloEmpresaDesdeRelacion($existente->empresas?->nombre ?? null, (int) ($existente->empresa_id ?? 0));

        return sprintf(
            'CAE/CAI duplicado: el código %s ya figura en el comprobante #%d de %s (%s %s %s-%s). '
            .'Si es CAEA, indique tipo de autorización CAEA.',
            $cae,
            $existente->id,
            $enEmpresa,
            strtoupper((string) ($existente->tipotransaccion_compras?->abreviatura ?? 'FAC')),
            strtoupper((string) $existente->letra),
            $existente->sucursal,
            $existente->numerocomprobante,
        );
    }

    public static function mensajeDuplicadoCaePrecarga(Precarga_Comprobante_Proveedor $existente, string $cae): string
    {
        $existente->loadMissing('empresas');
        $enEmpresa = self::rotuloEmpresaDesdeRelacion($existente->empresas?->nombre ?? null, (int) ($existente->empresa_id ?? 0));

        return sprintf(
            'CAE/CAI duplicado: el código %s ya figura en la precarga #%d de %s (%s %s-%s). '
            .'Si es CAEA, indique tipo de autorización CAEA.',
            $cae,
            $existente->id,
            $enEmpresa,
            strtoupper((string) $existente->letra),
            $existente->sucursal,
            $existente->numerocomprobante,
        );
    }

    private static function rotuloFactura(string $abreviatura, string $letra, mixed $sucursal, mixed $numero): string
    {
        $abrev = strtoupper(trim($abreviatura));
        if ($abrev === '') {
            $abrev = 'FAC';
        }

        return trim(sprintf(
            '%s %s %s-%s',
            $abrev,
            strtoupper(substr(trim($letra), 0, 1)),
            $sucursal,
            $numero,
        ));
    }

    private static function rotuloProveedorCuit(string $proveedor, string $cuitDigitos): string
    {
        $cuit = self::formatearCuit($cuitDigitos);
        $partes = [];
        if ($proveedor !== '') {
            $partes[] = 'de '.$proveedor;
        }
        if ($cuit !== '') {
            $partes[] = 'CUIT '.$cuit;
        }
        if ($partes === []) {
            return '';
        }

        return ' ('.implode(', ', $partes).')';
    }

    private static function situacionNoContabilizada(string $estado): string
    {
        return match ($estado) {
            ComprobanteProveedorEstados::BORRADOR,
            ComprobanteProveedorEstados::PRECARGA,
            '' => 'sigue en borrador: no tiene cuenta corriente y no está en Anita',
            ComprobanteProveedorEstados::PENDIENTE_REVISION => 'está pendiente de revisión: todavía no tiene cuenta corriente ni está en Anita',
            ComprobanteProveedorEstados::PENDIENTE_APROBACION => 'está pendiente de aprobación: todavía no tiene cuenta corriente ni está en Anita',
            ComprobanteProveedorEstados::PENDIENTE_DIFERENCIA => 'está pendiente por una diferencia: todavía no tiene cuenta corriente ni está en Anita',
            ComprobanteProveedorEstados::APROBADO => 'está aprobado, pero todavía no está contabilizado',
            ComprobanteProveedorEstados::ERROR_SYNC => 'quedó con error al pasarlo a Anita',
            default => 'todavía no está contabilizado: no tiene cuenta corriente ni está en Anita',
        };
    }

    private static function aplicarFiltroEsFce($query, ?bool $esFce): void
    {
        if ($esFce === null) {
            return;
        }

        $query->where('es_fce', $esFce);
    }

    public static function formatearCuit(string $cuitDigitos): string
    {
        $cuitDigitos = self::normalizarCuitDigitos($cuitDigitos);
        if ($cuitDigitos === '') {
            return '';
        }

        return substr($cuitDigitos, 0, 2).'-'.substr($cuitDigitos, 2, 8).'-'.substr($cuitDigitos, 10, 1);
    }

    /**
     * Mensaje de UI al fallar el alta/edición. Solo habla de identificación fiscal
     * cuando el índice único fiscal (o el equivalente de precarga por AFIP) es el que falló;
     * no enmascara otros SQLSTATE/Duplicate entry.
     */
    public static function mensajeParaErrorPersistencia(string $prefijo, \Throwable $e): string
    {
        if (self::esViolacionUnicidadFiscal($e)) {
            return $prefijo.'. Ya existe un comprobante con la misma identificación fiscal '
                .'(empresa, tipo, letra, sucursal, número, CUIT y marca FCE). Buscalo en el listado de Cuentas a pagar.';
        }

        if (DbContencionSupport::esViolacionUnicidad(
            $e,
            'uq_comprobante_proveedor_precarga',
            'precarga_comprobante_proveedor_id',
        )) {
            return $prefijo.'. Ya existe un comprobante vinculado a esta precarga. '
                .'Abrí ese registro en el listado de Cuentas a pagar.';
        }

        if (self::esViolacionUnicidadPrecarga($e)) {
            return $prefijo.'. Ya existe una precarga con la misma identificación fiscal '
                .'(empresa, tipo AFIP, letra, sucursal, número y CUIT).';
        }

        return $prefijo.': '.$e->getMessage();
    }
}
