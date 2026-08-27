<?php

namespace App\Support\Compras;

use App\ApiAnita;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Configuracion\Empresa;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Consulta Anita (tabla compra) para evitar cargar/confirmar en ERP una factura
 * que ya existe en Anita (nativo u otro origen).
 *
 * Unicidad fiscal: proveedor + tipo ARCA (001/002/003, …) + letra + sucursal + número
 * (+ empresa cuando se puede resolver). El tipo ARCA agrupa abreviaturas distintas
 * (FAC, FNB, …) que informan el mismo código AFIP.
 */
final class ComprobanteProveedorAnitaCompraExistenciaSupport
{
    /** @var array<string, string>|null abreviatura 3 letras → codigoafip */
    private static ?array $cacheCodigoAfipPorAbreviatura = null;

    /**
     * @throws ComprobanteProveedorYaExistenteEnAnitaException
     * @throws RuntimeException
     */
    public static function assertNoDuplicadoEnAnita(
        int $empresaId,
        int $proveedorId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        ?int $excluirAnitaNroInterno = null,
    ): void {
        $fila = self::buscar(
            $empresaId,
            $proveedorId,
            $tipotransaccionCompraId,
            $letra,
            $sucursal,
            $numerocomprobante,
            $excluirAnitaNroInterno,
        );

        if ($fila === null) {
            return;
        }

        $tipoArca = self::tipoArcaDesdeTipoId($tipotransaccionCompraId, $letra);

        throw ComprobanteProveedorYaExistenteEnAnitaException::desdeFila(
            $fila,
            $letra,
            $sucursal,
            $numerocomprobante,
            $tipoArca,
        );
    }

    /**
     * Aviso para UI si Anita ya tiene esa identificación fiscal y el ERP aún no la posteó.
     *
     * @return array{mensaje: string, nro_interno: int|null, fila: array<string, mixed>, ya_marcada: bool, comprobante_id: int}|null
     */
    public static function avisoDuplicadoDesdeComprobante(Comprobante_Proveedor $comprobante): ?array
    {
        if (ComprobanteProveedorEstados::tieneHuellaAnita($comprobante)) {
            return null;
        }
        if (in_array((string) ($comprobante->estado ?? ''), [
            ComprobanteProveedorEstados::CONTABILIZADO,
            ComprobanteProveedorEstados::ANULADO,
        ], true)) {
            return null;
        }

        $comprobante->loadMissing('tipotransaccion_compras', 'proveedores', 'empresas');

        try {
            $fila = self::buscar(
                (int) ($comprobante->empresa_id ?? 0),
                (int) ($comprobante->proveedor_id ?? 0),
                (int) ($comprobante->tipotransaccion_compra_id ?? 0),
                (string) ($comprobante->letra ?? ''),
                (int) ($comprobante->sucursal ?? 0),
                (int) ($comprobante->numerocomprobante ?? 0),
                (int) ($comprobante->anita_nro_interno ?? 0) ?: null,
            );
        } catch (\Throwable $e) {
            Log::warning('comprobante_proveedor.anita_compra_aviso_error', [
                'comprobante_id' => (int) $comprobante->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($fila === null) {
            return null;
        }

        $letra = (string) ($comprobante->letra ?? '');
        $sucursal = (int) ($comprobante->sucursal ?? 0);
        $nro = (int) ($comprobante->numerocomprobante ?? 0);

        return [
            'mensaje' => self::mensajeDuplicado($fila, $letra, $sucursal, $nro),
            'nro_interno' => ((int) ($fila['com_nro_interno'] ?? 0)) ?: null,
            'fila' => $fila,
            'ya_marcada' => false,
            'comprobante_id' => (int) $comprobante->id,
        ];
    }

    /**
     * @throws ComprobanteProveedorYaExistenteEnAnitaException
     * @throws RuntimeException
     */
    public static function assertDesdeComprobante(Comprobante_Proveedor $comprobante): void
    {
        $comprobante->loadMissing('tipotransaccion_compras', 'proveedores', 'empresas');

        self::assertNoDuplicadoEnAnita(
            (int) ($comprobante->empresa_id ?? 0),
            (int) ($comprobante->proveedor_id ?? 0),
            (int) ($comprobante->tipotransaccion_compra_id ?? 0),
            (string) ($comprobante->letra ?? ''),
            (int) ($comprobante->sucursal ?? 0),
            (int) ($comprobante->numerocomprobante ?? 0),
            (int) ($comprobante->anita_nro_interno ?? 0) ?: null,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function buscar(
        int $empresaId,
        int $proveedorId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        ?int $excluirAnitaNroInterno = null,
    ): ?array {
        if ($proveedorId <= 0 || $numerocomprobante <= 0) {
            return null;
        }

        $proveedorCodigo = self::proveedorCodigoAnita($proveedorId);
        $letraNorm = strtoupper(substr(trim($letra), 0, 1));
        $tipoAbrev = self::tipoAbreviaturaAnita($tipotransaccionCompraId);
        $tipoArca = self::tipoArcaDesdeTipoId($tipotransaccionCompraId, $letraNorm);
        if ($proveedorCodigo === '' || $letraNorm === '') {
            return null;
        }
        if ($tipoAbrev === '' && $tipoArca === '') {
            return null;
        }

        $empresaCodigo = self::empresaCodigoAnita($empresaId);

        $where = " WHERE com_proveedor = '".$proveedorCodigo."'"
            ." AND com_letra = '".$letraNorm."'"
            .' AND com_sucursal = '.(int) $sucursal
            .' AND com_nro = '.(int) $numerocomprobante;

        if ($empresaCodigo !== null) {
            $where .= ' AND com_empresa = '.(int) $empresaCodigo;
        }

        if ($tipoArca === '' && $tipoAbrev !== '') {
            $where .= " AND com_tipo = '".$tipoAbrev."'";
        }

        if ($excluirAnitaNroInterno !== null && $excluirAnitaNroInterno > 0) {
            $where .= ' AND com_nro_interno <> '.(int) $excluirAnitaNroInterno;
        }

        // Descripción al final por convención del bridge (corrimiento por |).
        $campos = implode(', ', [
            'com_proveedor',
            'com_tipo',
            'com_letra',
            'com_sucursal',
            'com_nro',
            'com_nro_interno',
            'com_fecha',
            'com_empresa',
            'com_monto',
            'com_cuit_prov',
            'com_nombre_prov',
        ]);

        try {
            $api = new ApiAnita;
            $filas = ApiAnita::decodificarListaFilas($api->apiCall([
                'acc' => 'list',
                'sistema' => 'compras',
                'tabla' => 'compra',
                'campos' => $campos,
                'whereArmado' => $where,
            ]));
        } catch (\Throwable $e) {
            Log::warning('comprobante_proveedor.anita_compra_existencia_error', [
                'proveedor' => $proveedorCodigo,
                'tipo_arca' => $tipoArca,
                'tipo' => $tipoAbrev,
                'letra' => $letraNorm,
                'sucursal' => $sucursal,
                'nro' => $numerocomprobante,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'No se pudo consultar Anita (tabla compra) para verificar si la factura ya existe. '
                .'Reintente; si el problema continúa, avise a sistemas. Detalle: '.$e->getMessage()
            );
        }

        if ($filas === []) {
            return null;
        }

        $mapa = self::mapaCodigoAfipPorAbreviatura();
        $codigoPropio = ComprobanteProveedorUnicidadSupport::codigoAfipDesdeTipoId($tipotransaccionCompraId);
        if ($tipoAbrev !== '' && $codigoPropio !== '' && ! isset($mapa[$tipoAbrev])) {
            $mapa[$tipoAbrev] = $codigoPropio;
        }

        return self::seleccionarFilaDuplicada(
            $filas,
            $tipoArca,
            $letraNorm,
            $mapa,
            $excluirAnitaNroInterno,
            $tipoAbrev,
        );
    }

    /**
     * Tipo ARCA de 3 dígitos (001 factura A, 002 ND A, 003 NC A, 006 factura B, …).
     */
    public static function tipoArca(string $codigoAfip, string $letra, ?string $abreviatura = null): string
    {
        $letraNorm = strtoupper(substr(trim($letra), 0, 1));
        if ($letraNorm === '') {
            return '';
        }

        $codigoNorm = ComprobanteProveedorUnicidadSupport::normalizarCodigoAfip($codigoAfip);
        if ($codigoNorm === '' && ! LibroIvaDigitalMapeosSupport::esSinCaeInformable($abreviatura)) {
            return '';
        }

        $tipo = LibroIvaDigitalMapeosSupport::tipoComprobanteVentas(
            $codigoAfip,
            $letraNorm,
            $abreviatura
        );
        $digits = preg_replace('/\D/', '', $tipo) ?? '';
        if ($digits === '' || (int) $digits === 0) {
            return '';
        }

        return str_pad((string) (int) $digits, 3, '0', STR_PAD_LEFT);
    }

    public static function tipoArcaDesdeTipoId(int $tipotransaccionCompraId, string $letra): string
    {
        if ($tipotransaccionCompraId <= 0) {
            return '';
        }

        return self::tipoArca(
            ComprobanteProveedorUnicidadSupport::codigoAfipDesdeTipoId($tipotransaccionCompraId),
            $letra,
            self::tipoAbreviaturaAnita($tipotransaccionCompraId),
        );
    }

    /**
     * @param  list<array<string, mixed>|object>  $filas
     * @param  array<string, string>  $codigoAfipPorAbreviatura
     * @return array<string, mixed>|null
     */
    public static function seleccionarFilaDuplicada(
        array $filas,
        string $tipoArcaEsperado,
        string $letra,
        array $codigoAfipPorAbreviatura,
        ?int $excluirAnitaNroInterno = null,
        string $abreviaturaPropia = '',
    ): ?array {
        foreach ($filas as $filaRaw) {
            $fila = self::filaAsArray($filaRaw);
            $nroInterno = (int) ($fila['com_nro_interno'] ?? 0);
            if ($excluirAnitaNroInterno !== null && $excluirAnitaNroInterno > 0 && $nroInterno === $excluirAnitaNroInterno) {
                continue;
            }

            if (self::filaCoincideTipoArca(
                $fila,
                $tipoArcaEsperado,
                $letra,
                $codigoAfipPorAbreviatura,
                $abreviaturaPropia,
            )) {
                return $fila;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, string>  $codigoAfipPorAbreviatura
     */
    public static function filaCoincideTipoArca(
        array $fila,
        string $tipoArcaEsperado,
        string $letra,
        array $codigoAfipPorAbreviatura,
        string $abreviaturaPropia = '',
    ): bool {
        $abrevFila = strtoupper(substr(trim((string) ($fila['com_tipo'] ?? '')), 0, 3));
        if ($abrevFila === '') {
            return false;
        }

        $letraNorm = strtoupper(substr(trim($letra), 0, 1));
        $letraFila = strtoupper(substr(trim((string) ($fila['com_letra'] ?? '')), 0, 1));
        if ($letraFila !== '' && $letraNorm !== '' && $letraFila !== $letraNorm) {
            return false;
        }

        $abrevPropia = strtoupper(substr(trim($abreviaturaPropia), 0, 3));
        if ($abrevPropia !== '' && $abrevFila === $abrevPropia) {
            return true;
        }

        $tipoArcaNorm = self::normalizarTipoArca($tipoArcaEsperado);
        if ($tipoArcaNorm === '') {
            return false;
        }

        $codigoAfip = (string) ($codigoAfipPorAbreviatura[$abrevFila] ?? '');
        if ($codigoAfip === '') {
            return false;
        }

        $letraParaArca = $letraFila !== '' ? $letraFila : $letraNorm;

        return self::tipoArca($codigoAfip, $letraParaArca, $abrevFila) === $tipoArcaNorm;
    }

    public static function formatearSucursalNumero(int $sucursal, int $numero): string
    {
        return str_pad((string) $sucursal, 4, '0', STR_PAD_LEFT)
            .'-'
            .str_pad((string) $numero, 8, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function mensajeDuplicado(
        array $fila,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        ?string $tipoArca = null,
    ): string {
        $tipo = strtoupper(substr(trim((string) ($fila['com_tipo'] ?? '')), 0, 3));
        $letraFila = strtoupper(substr(trim((string) ($fila['com_letra'] ?? $letra)), 0, 1));
        $suc = (int) ($fila['com_sucursal'] ?? $sucursal);
        $nro = (int) ($fila['com_nro'] ?? $numerocomprobante);
        $nroInterno = (int) ($fila['com_nro_interno'] ?? 0);
        $fecha = trim((string) ($fila['com_fecha'] ?? ''));
        $fechaFmt = self::formatearFechaAnita($fecha);
        $cuit = preg_replace('/\D/', '', (string) ($fila['com_cuit_prov'] ?? '')) ?? '';
        $cuitFmt = strlen($cuit) === 11
            ? ComprobanteProveedorUnicidadSupport::formatearCuit($cuit)
            : trim((string) ($fila['com_cuit_prov'] ?? ''));
        $nombre = trim((string) ($fila['com_nombre_prov'] ?? ''));

        $tipoArcaNorm = self::normalizarTipoArca((string) ($tipoArca ?? ''));
        if ($tipoArcaNorm === '' && $tipo !== '') {
            $codigoAfip = self::mapaCodigoAfipPorAbreviatura()[$tipo] ?? '';
            $tipoArcaNorm = self::tipoArca($codigoAfip, $letraFila, $tipo);
        }

        $identificacion = trim(sprintf(
            '%s%s %s %s',
            $tipoArcaNorm !== '' ? 'tipo ARCA '.$tipoArcaNorm.' · ' : '',
            $tipo !== '' ? $tipo : 'FAC',
            $letraFila,
            self::formatearSucursalNumero($suc, $nro),
        ));

        $detalle = [];
        $detalle[] = 'nro. interno Anita '.($nroInterno > 0 ? (string) $nroInterno : 's/d');
        if ($fechaFmt !== '') {
            $detalle[] = 'fecha '.$fechaFmt;
        }
        if ($cuitFmt !== '') {
            $detalle[] = 'CUIT '.$cuitFmt.($nombre !== '' ? ' '.$nombre : '');
        }

        return 'Factura ya existente en Anita para este proveedor: '.$identificacion
            .' ('.implode(', ', $detalle).'). '
            .'No se puede repetir esa identificación fiscal (proveedor + tipo ARCA + letra + sucursal + número) desde el ERP.';
    }

    public static function normalizarTipoArca(string $tipoArca): string
    {
        $digits = preg_replace('/\D/', '', $tipoArca) ?? '';
        if ($digits === '' || (int) $digits === 0) {
            return '';
        }

        return str_pad((string) (int) $digits, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>|object  $fila
     * @return array<string, mixed>
     */
    private static function filaAsArray(array|object $fila): array
    {
        if (is_array($fila)) {
            return $fila;
        }

        return get_object_vars($fila);
    }

    /**
     * @return array<string, string>
     */
    private static function mapaCodigoAfipPorAbreviatura(): array
    {
        if (self::$cacheCodigoAfipPorAbreviatura !== null) {
            return self::$cacheCodigoAfipPorAbreviatura;
        }

        $mapa = [];
        foreach (Tipotransaccion_Compra::query()->get(['abreviatura', 'codigoafip']) as $tipo) {
            $abrev = strtoupper(substr(trim((string) $tipo->abreviatura), 0, 3));
            if ($abrev === '') {
                continue;
            }
            $mapa[$abrev] = (string) $tipo->codigoafip;
        }

        return self::$cacheCodigoAfipPorAbreviatura = $mapa;
    }

    private static function proveedorCodigoAnita(int $proveedorId): string
    {
        $codigo = Proveedor::query()->whereKey($proveedorId)->value('codigo');
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return '';
        }

        return str_pad($codigo, 6, '0', STR_PAD_LEFT);
    }

    private static function tipoAbreviaturaAnita(int $tipotransaccionCompraId): string
    {
        if ($tipotransaccionCompraId <= 0) {
            return '';
        }

        $abrev = Tipotransaccion_Compra::query()->whereKey($tipotransaccionCompraId)->value('abreviatura');

        return strtoupper(substr(trim((string) $abrev), 0, 3));
    }

    private static function empresaCodigoAnita(int $empresaId): ?int
    {
        if ($empresaId <= 0) {
            return null;
        }

        $codigo = Empresa::query()->whereKey($empresaId)->value('codigo');
        if ($codigo === null || $codigo === '') {
            return $empresaId;
        }

        return (int) $codigo;
    }

    private static function formatearFechaAnita(string $ymd): string
    {
        $digits = preg_replace('/\D/', '', $ymd) ?? '';
        if (strlen($digits) !== 8) {
            return '';
        }

        return substr($digits, 6, 2).'/'.substr($digits, 4, 2).'/'.substr($digits, 0, 4);
    }
}
