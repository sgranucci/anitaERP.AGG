<?php

namespace App\Services\Arca;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\ArcaTipoComprobante;
use App\Models\Ventas\Puntoventa;
use App\Support\Database\SqlDialectSupport;
use App\Support\Ventas\ArcaPuntoventaWebserviceSupport;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo AFIP de tipos de comprobante para ABM tipotransaccion (ventas y compras).
 * Usa WSMTXCA o WSFE según ARCA_TIPOS_CBTE_WEBSERVICE o el webservice de los PV en modo CAE.
 * Compras combina los catálogos ya sincronizados de los web services con puntos de venta activos.
 */
class ArcaTiposComprobanteCatalogoService
{
    /** @var array<string, string>|null */
    private static ?array $mapaDescripciones = null;
    public const WS_WSFE = 'wsfev1';

    public const WS_MTXCA = 'wsmtxca';

    public function __construct(
        private ArcaWsfeFacturaElectronicaService $arcaWsfe,
        private ArcaMtxcaFacturaElectronicaService $arcaMtxca,
    ) {}

    /**
     * Catálogo AFIP: BD local (si hay sync previa) o consulta ARCA + persistencia opcional.
     *
     * @return array{
     *     tipos: list<array{id: int, codigo: string, descripcion: string}>,
     *     origen: string,
     *     sincronizado_at: ?string,
     *     persistido: bool,
     *     registros_guardados: int
     * }
     */
    public function obtenerTiposComprobante(int $empresaId, bool $refresh = false): array
    {
        $webservice = $this->webserviceParaEmpresa($empresaId);

        if (
            ! $refresh
            && $this->debeUsarBdSinRefresh()
            && $this->tieneCatalogoEnBd($empresaId, $webservice)
        ) {
            $tiposBd = $this->listarDesdeBd($empresaId, $webservice);

            return [
                'tipos' => $tiposBd,
                'origen' => 'bd',
                'sincronizado_at' => $this->ultimaSincronizacion($empresaId, $webservice)?->toIso8601String(),
                'persistido' => false,
                'registros_guardados' => count($tiposBd),
            ];
        }

        $tipos = $this->consultarArca($empresaId, $webservice);
        $registrosGuardados = 0;
        $sincronizadoAt = null;

        if ($this->debePersistirEnBd() && $tipos !== []) {
            $sincronizadoAt = $this->persistirCatalogo($empresaId, $webservice, $tipos);
            $registrosGuardados = $this->contarEnBd($empresaId, $webservice);
        }

        return [
            'tipos' => $tipos,
            'origen' => 'arca',
            'sincronizado_at' => $sincronizadoAt?->toIso8601String(),
            'persistido' => $registrosGuardados > 0,
            'registros_guardados' => $registrosGuardados,
        ];
    }

    /**
     * @return list<array{id: int, codigo: string, descripcion: string}>
     */
    public function listarTiposComprobante(int $empresaId): array
    {
        return $this->obtenerTiposComprobante($empresaId, true)['tipos'];
    }

    /**
     * @return list<array{id: int, codigo: string, descripcion: string}>
     */
    public function listarDesdeBd(int $empresaId, string $webservice): array
    {
        return ArcaTipoComprobante::query()
            ->where('empresa_id', $empresaId)
            ->where('webservice', $webservice)
            ->orderBy('codigo_numerico')
            ->get()
            ->map(static fn (ArcaTipoComprobante $row): array => [
                'id' => (int) $row->codigo_numerico,
                'codigo' => (string) $row->codigo_afip,
                'descripcion' => (string) $row->descripcion,
            ])
            ->values()
            ->all();
    }

    public function tieneCatalogoEnBd(int $empresaId, string $webservice): bool
    {
        return ArcaTipoComprobante::query()
            ->where('empresa_id', $empresaId)
            ->where('webservice', $webservice)
            ->exists();
    }

    public function ultimaSincronizacion(int $empresaId, string $webservice): ?Carbon
    {
        $max = ArcaTipoComprobante::query()
            ->where('empresa_id', $empresaId)
            ->where('webservice', $webservice)
            ->max('sincronizado_at');

        return $max !== null ? Carbon::parse($max) : null;
    }

    /**
     * @param  list<array{id: int, codigo: string, descripcion: string}>  $tipos
     */
    public function persistirCatalogo(int $empresaId, string $webservice, array $tipos): Carbon
    {
        if (! Schema::hasTable('arca_tipo_comprobante')) {
            throw new Exception(
                'La tabla arca_tipo_comprobante no existe. Ejecute: php artisan migrate --path=database/migrations/2026_05_21_160000_crear_tabla_arca_tipo_comprobante.php'
            );
        }

        $ahora = Carbon::now();
        $timestamp = $ahora->format('Y-m-d H:i:s');
        $filas = [];

        foreach ($tipos as $tipo) {
            $codigoNumerico = (int) ($tipo['id'] ?? 0);
            $codigoAfip = trim((string) ($tipo['codigo'] ?? ''));
            if ($codigoNumerico < 1 || $codigoAfip === '') {
                continue;
            }
            $filas[] = [
                'empresa_id' => $empresaId,
                'webservice' => $webservice,
                'codigo_numerico' => $codigoNumerico,
                'codigo_afip' => $codigoAfip,
                'descripcion' => mb_substr(trim((string) ($tipo['descripcion'] ?? '')), 0, 255),
                'sincronizado_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($filas === []) {
            throw new Exception('ARCA devolvió tipos de comprobante vacíos; no hay nada para guardar.');
        }

        DB::transaction(function () use ($empresaId, $webservice, $filas): void {
            ArcaTipoComprobante::query()
                ->where('empresa_id', $empresaId)
                ->where('webservice', $webservice)
                ->delete();

            foreach (array_chunk($filas, 100) as $lote) {
                DB::table('arca_tipo_comprobante')->insert($lote);
            }
        });

        return $ahora;
    }

    public function contarEnBd(int $empresaId, string $webservice): int
    {
        return (int) ArcaTipoComprobante::query()
            ->where('empresa_id', $empresaId)
            ->where('webservice', $webservice)
            ->count();
    }

    /**
     * @return list<array{id: int, codigo: string, descripcion: string}>
     */
    private function consultarArca(int $empresaId, string $webservice): array
    {
        $this->assertEmpresaConfigurada($empresaId, $webservice);

        if ($webservice === self::WS_MTXCA) {
            return $this->arcaMtxca->consultarTiposComprobante($empresaId);
        }

        return $this->arcaWsfe->feParamGetTiposCbte($empresaId);
    }

    private function debePersistirEnBd(): bool
    {
        return filter_var(config('arca.tipos_cbte.persistir_en_bd', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function debeUsarBdSinRefresh(): bool
    {
        return filter_var(config('arca.tipos_cbte.usar_bd_sin_refresh', true), FILTER_VALIDATE_BOOLEAN);
    }

    public static function normalizarCodigoAfip(string $codigo): string
    {
        $digits = preg_replace('/\D+/', '', trim($codigo)) ?? '';
        if ($digits === '') {
            return trim($codigo);
        }

        return str_pad($digits, 3, '0', STR_PAD_LEFT);
    }

    public static function descripcionCodigo(?string $codigo): string
    {
        $norm = self::normalizarCodigoAfip((string) $codigo);
        if ($norm === '') {
            return '';
        }

        return (string) (self::mapaDescripciones()[$norm] ?? '');
    }

    /**
     * Web services con puntos de venta activos que publican el catálogo de tipos (WSFE / WSMTXCA).
     *
     * @return list<string>
     */
    public function webservicesActivos(int $empresaId): array
    {
        $aliases = array_values(array_unique(array_merge(
            ArcaPuntoventaWebserviceSupport::ALIASES_WSFE,
            ArcaPuntoventaWebserviceSupport::ALIASES_MTXCA,
        )));

        $raw = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', 'A')
            ->whereIn('webservice', $aliases)
            ->distinct()
            ->pluck('webservice');

        $activos = [];
        foreach ($raw as $ws) {
            $norm = ArcaPuntoventaWebserviceSupport::normalizar((string) $ws);
            if (in_array($norm, [self::WS_WSFE, self::WS_MTXCA], true) && ! in_array($norm, $activos, true)) {
                $activos[] = $norm;
            }
        }

        if ($activos === []) {
            return [$this->webserviceParaEmpresa($empresaId)];
        }

        return $activos;
    }

    /**
     * Unión de catálogos locales de los web services activos. Si el mismo código
     * está en más de uno, queda la descripción de la sincronización más reciente.
     *
     * @return list<array{id: int, codigo: string, descripcion: string}>
     */
    public function listarDesdeBdActivos(int $empresaId): array
    {
        $webservices = $this->webservicesActivos($empresaId);
        $filas = ArcaTipoComprobante::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('webservice', $webservices)
            ->orderByDesc('sincronizado_at')
            ->orderBy('codigo_numerico')
            ->get();

        $porCodigo = [];
        foreach ($filas as $row) {
            $codigo = (string) $row->codigo_afip;
            if ($codigo === '' || isset($porCodigo[$codigo])) {
                continue;
            }
            $porCodigo[$codigo] = [
                'id' => (int) $row->codigo_numerico,
                'codigo' => $codigo,
                'descripcion' => (string) $row->descripcion,
            ];
        }

        $tipos = array_values($porCodigo);
        usort($tipos, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return $tipos;
    }

    public function tieneCatalogoActivoEnBd(int $empresaId): bool
    {
        return ArcaTipoComprobante::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('webservice', $this->webservicesActivos($empresaId))
            ->exists();
    }

    public function ultimaSincronizacionActivos(int $empresaId): ?Carbon
    {
        $max = ArcaTipoComprobante::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('webservice', $this->webservicesActivos($empresaId))
            ->max('sincronizado_at');

        return $max !== null ? Carbon::parse($max) : null;
    }

    /**
     * Catálogo de compras: lee las tablas ya sincronizadas de los web services activos,
     * o las vuelve a pedir a ARCA si $refresh es verdadero (o todavía no hay filas).
     *
     * @return array{
     *     tipos: list<array{id: int, codigo: string, descripcion: string}>,
     *     origen: string,
     *     sincronizado_at: ?string,
     *     persistido: bool,
     *     registros_guardados: int,
     *     webservices: list<string>,
     *     advertencias: list<string>
     * }
     */
    public function obtenerTiposComprobanteActivos(int $empresaId, bool $refresh = false): array
    {
        $webservices = $this->webservicesActivos($empresaId);

        if (! $refresh && $this->debeUsarBdSinRefresh() && $this->tieneCatalogoActivoEnBd($empresaId)) {
            $tiposBd = $this->listarDesdeBdActivos($empresaId);

            return [
                'tipos' => $tiposBd,
                'origen' => 'bd',
                'sincronizado_at' => $this->ultimaSincronizacionActivos($empresaId)?->toIso8601String(),
                'persistido' => false,
                'registros_guardados' => count($tiposBd),
                'webservices' => $webservices,
                'advertencias' => [],
            ];
        }

        $porCodigo = [];
        $guardados = 0;
        $sincronizadoAt = null;
        $advertencias = [];

        foreach ($webservices as $webservice) {
            try {
                $this->assertEmpresaConfigurada($empresaId, $webservice);
                $tipos = $this->consultarArca($empresaId, $webservice);
                if ($this->debePersistirEnBd() && $tipos !== []) {
                    $sincronizadoAt = $this->persistirCatalogo($empresaId, $webservice, $tipos);
                    $guardados += $this->contarEnBd($empresaId, $webservice);
                }
                foreach ($tipos as $tipo) {
                    $codigo = (string) ($tipo['codigo'] ?? '');
                    if ($codigo !== '') {
                        $porCodigo[$codigo] = $tipo;
                    }
                }
            } catch (Exception $e) {
                $advertencias[] = $this->etiquetaWebservice($webservice).': '.$e->getMessage();
            }
        }

        if ($porCodigo === []) {
            if ($this->tieneCatalogoActivoEnBd($empresaId)) {
                return [
                    'tipos' => $this->listarDesdeBdActivos($empresaId),
                    'origen' => 'bd',
                    'sincronizado_at' => $this->ultimaSincronizacionActivos($empresaId)?->toIso8601String(),
                    'persistido' => false,
                    'registros_guardados' => 0,
                    'webservices' => $webservices,
                    'advertencias' => $advertencias,
                ];
            }

            throw new Exception(
                $advertencias !== []
                    ? implode(' ', $advertencias)
                    : 'ARCA no devolvió tipos de comprobante para los web services activos.'
            );
        }

        self::$mapaDescripciones = null;
        $tipos = array_values($porCodigo);
        usort($tipos, static fn (array $a, array $b): int => ((int) $a['id']) <=> ((int) $b['id']));

        return [
            'tipos' => $tipos,
            'origen' => 'arca',
            'sincronizado_at' => $sincronizadoAt?->toIso8601String(),
            'persistido' => $guardados > 0,
            'registros_guardados' => $guardados,
            'webservices' => $webservices,
            'advertencias' => $advertencias,
        ];
    }

    /**
     * @param  list<string>  $webservices
     */
    public function etiquetasWebservices(array $webservices): string
    {
        $etiquetas = [];
        foreach ($webservices as $webservice) {
            $etiqueta = $this->etiquetaWebservice((string) $webservice);
            if ($etiqueta !== '' && ! in_array($etiqueta, $etiquetas, true)) {
                $etiquetas[] = $etiqueta;
            }
        }

        return implode(' + ', $etiquetas);
    }

    public function webserviceParaEmpresa(int $empresaId): string
    {
        $forzado = strtolower(trim((string) config('arca.tipos_cbte.webservice', '')));
        if (in_array($forzado, [self::WS_MTXCA, self::WS_WSFE], true)) {
            return $forzado;
        }

        return $this->webserviceDesdePuntosVentaCae($empresaId);
    }

    public function etiquetaWebservice(string $webservice): string
    {
        return match ($webservice) {
            self::WS_MTXCA => 'WSMTXCA (Factura con detalle)',
            self::WS_WSFE => 'WSFE v1 (Comprobantes nacionales)',
            default => $webservice,
        };
    }

    /**
     * Mayoría de puntos de venta activos en modo CAE; ante empate prioriza wsmtxca (p. ej. El Bierzo).
     */
    public function webserviceDesdePuntosVentaCae(int $empresaId): string
    {
        $filas = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', 'A')
            ->where('modofacturacion', 'C')
            ->whereIn('webservice', [self::WS_WSFE, self::WS_MTXCA])
            ->select('webservice', DB::raw('COUNT(*) as total'))
            ->groupBy('webservice')
            ->pluck('total', 'webservice');

        $mtxca = (int) ($filas[self::WS_MTXCA] ?? 0);
        $wsfe = (int) ($filas[self::WS_WSFE] ?? 0);

        if ($mtxca === 0 && $wsfe === 0) {
            $pv = Puntoventa::query()
                ->where('empresa_id', $empresaId)
                ->where('estado', 'A')
                ->whereIn('webservice', [self::WS_WSFE, self::WS_MTXCA])
                ->orderByRaw(SqlDialectSupport::ordenPorLista('webservice', [self::WS_MTXCA, self::WS_WSFE]))
                ->first();

            return (string) ($pv->webservice ?? self::WS_WSFE);
        }

        if ($mtxca >= $wsfe) {
            return self::WS_MTXCA;
        }

        return self::WS_WSFE;
    }

    /**
     * Rutas y CUIT del certificado que usará el SOAP (para diagnóstico).
     *
     * @return array{
     *     cert_path: string,
     *     private_key_path: string,
     *     cuit_certificado: ?string,
     *     cuit_empresa: ?string,
     *     wsaa_service: string,
     *     carpeta: string
     * }
     */
    public function diagnosticoCertificado(int $empresaId, string $webservice): array
    {
        $carpeta = $webservice === self::WS_MTXCA
            ? (string) (config("arca_mtxca.empresas.{$empresaId}.carpeta_cert") ?? '')
            : (string) (config("arca_wsfe.empresas.{$empresaId}.carpeta_cert") ?? '');

        $base = $webservice === self::WS_MTXCA
            ? rtrim((string) config('arca_mtxca.base_storage'), '/')
            : rtrim((string) config('arca_wsfe.base_storage'), '/');

        $certPath = $base.'/certs/'.$carpeta.'/cert.crt';
        $keyPath = $base.'/certs/'.$carpeta.'/privada.key';

        if ($webservice === self::WS_MTXCA && ! is_readable($certPath)) {
            $fallback = rtrim((string) config('arca_wsfe.base_storage'), '/').'/certs/'.$carpeta;
            if (is_readable($fallback.'/cert.crt')) {
                $certPath = $fallback.'/cert.crt';
                $keyPath = $fallback.'/privada.key';
            }
        }

        $empresa = Empresa::query()->find($empresaId);
        $cuitEmpresa = $empresa?->nroinscripcion !== null
            ? preg_replace('/\D+/', '', (string) $empresa->nroinscripcion)
            : null;

        return [
            'cert_path' => $certPath,
            'private_key_path' => $keyPath,
            'cuit_certificado' => $this->cuitDesdeArchivoCertificado($certPath),
            'cuit_empresa' => $cuitEmpresa !== '' ? $cuitEmpresa : null,
            'wsaa_service' => $webservice === self::WS_MTXCA
                ? (string) config('arca_mtxca.wsaa_service_id', 'wsmtxca')
                : (string) config('arca_wsfe.wsaa_service_id', 'wsfe'),
            'carpeta' => $carpeta,
        ];
    }

    public function assertEmpresaConfigurada(int $empresaId, string $webservice): void
    {
        if ($webservice === self::WS_MTXCA) {
            if (! is_array(config("arca_mtxca.empresas.{$empresaId}"))) {
                throw new Exception(
                    "La empresa {$empresaId} no tiene certificados WSMTXCA en storage/app/arca/mtxca/certs/."
                );
            }
            if ((string) config('arca_mtxca.transporte', 'afip_php') !== 'soap') {
                throw new Exception(
                    'ARCA MTXCA: active ARCA_MTXCA_TRANSPORTE=soap en .env para consultar tipos de comprobante.'
                );
            }
        } else {
            if (! is_array(config("arca_wsfe.empresas.{$empresaId}"))) {
                throw new Exception(
                    "La empresa {$empresaId} no tiene certificados WSFE en storage/app/arca/wsfe/certs/."
                );
            }
            if ((string) config('arca_wsfe.transporte', 'afip_php') !== 'soap') {
                throw new Exception(
                    'ARCA WSFE: active ARCA_WSFE_TRANSPORTE=soap en .env para consultar tipos de comprobante.'
                );
            }
        }

        $diag = $this->diagnosticoCertificado($empresaId, $webservice);
        if (! is_readable($diag['cert_path']) || ! is_readable($diag['private_key_path'])) {
            throw new Exception(
                'No se encuentran cert.crt/privada.key en '.$diag['cert_path'].' (carpeta «'.$diag['carpeta'].'»).'
            );
        }

        if (! filter_var(config('arca.tipos_cbte.validar_cuit_certificado', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $cuitCert = $diag['cuit_certificado'];
        $cuitEmp = $diag['cuit_empresa'];
        if ($cuitCert !== null && $cuitEmp !== null && $cuitCert !== $cuitEmp) {
            throw new Exception(
                "El certificado en {$diag['cert_path']} pertenece al CUIT {$cuitCert}, ".
                "pero empresa.nroinscripcion es {$cuitEmp}. ".
                'El módulo afip.php envía el CUIT en cada XML; ARCA SOAP usa empresa.nroinscripcion. '.
                'Copie el certificado correcto o alinee el CUIT en Configuración → Empresa.'
            );
        }
    }

    private function cuitDesdeArchivoCertificado(string $certPath): ?string
    {
        if (! is_readable($certPath)) {
            return null;
        }
        $parsed = @openssl_x509_parse((string) file_get_contents($certPath));
        if (! is_array($parsed)) {
            return null;
        }
        $serial = (string) ($parsed['subject']['serialNumber'] ?? '');
        if (preg_match('/(\d{11})/', $serial, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function mapaDescripciones(): array
    {
        if (self::$mapaDescripciones !== null) {
            return self::$mapaDescripciones;
        }

        self::$mapaDescripciones = [];
        if (! Schema::hasTable('arca_tipo_comprobante')) {
            return self::$mapaDescripciones;
        }

        $filas = ArcaTipoComprobante::query()
            ->orderByDesc('sincronizado_at')
            ->get(['codigo_afip', 'descripcion']);

        foreach ($filas as $fila) {
            $codigo = self::normalizarCodigoAfip((string) $fila->codigo_afip);
            if ($codigo === '' || isset(self::$mapaDescripciones[$codigo])) {
                continue;
            }
            $descripcion = trim((string) $fila->descripcion);
            if ($descripcion !== '') {
                self::$mapaDescripciones[$codigo] = $descripcion;
            }
        }

        return self::$mapaDescripciones;
    }

    /**
     * @return list<int>
     */
    public function empresasConCertificadoArca(): array
    {
        $wsfe = array_map('intval', array_keys(config('arca_wsfe.empresas', [])));
        $mtxca = array_map('intval', array_keys(config('arca_mtxca.empresas', [])));

        return array_values(array_unique(array_merge($wsfe, $mtxca)));
    }
}
