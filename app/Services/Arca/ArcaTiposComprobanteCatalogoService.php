<?php

namespace App\Services\Arca;

use App\Support\Database\SqlDialectSupport;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\ArcaTipoComprobante;
use App\Models\Ventas\Puntoventa;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo AFIP de tipos de comprobante para ABM tipotransaccion.
 * Usa WSMTXCA o WSFE según ARCA_TIPOS_CBTE_WEBSERVICE o el webservice de los PV en modo CAE.
 */
class ArcaTiposComprobanteCatalogoService
{
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
        if (! \Illuminate\Support\Facades\Schema::hasTable('arca_tipo_comprobante')) {
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
     * @return list<int>
     */
    public function empresasConCertificadoArca(): array
    {
        $wsfe = array_map('intval', array_keys(config('arca_wsfe.empresas', [])));
        $mtxca = array_map('intval', array_keys(config('arca_mtxca.empresas', [])));

        return array_values(array_unique(array_merge($wsfe, $mtxca)));
    }
}
