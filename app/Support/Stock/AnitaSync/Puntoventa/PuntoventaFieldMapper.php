<?php

namespace App\Support\Stock\AnitaSync\Puntoventa;

use App\Models\Configuracion\Actividad_Arca;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Localidad;
use App\Models\Ventas\Puntoventa;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Mapeo sucursal (Anita) → puntoventa (ERP), alineado a la lógica histórica del repositorio de ventas
 * (switch suc_fiscal, nombre = suc_empresa, leyenda/pathafip, división/póliza/remito, reglas por texto en suc_empresa).
 * Correcciones: estado A/S (no ACTIVA/SUSPENDIDA); empresa por suc_nroemp → empresa.codigo cuando exista.
 *
 * En Anita (módulo ventas), suc_direccion almacena el código de actividad ARCA (ej. 561012, 524120),
 * no la calle del local. Se resuelve contra actividad_arca.codigoarca → puntoventa.actividad_arca_id.
 *
 * Calzados Ferli (suc_fiscal distinto a AGG): E = electrónica CAE, X = exportación WSFEX.
 * pathafip (afip.php en disco) es legacy; con transporte SOAP no se sincroniza desde suc_leyenda2.
 */
final class PuntoventaFieldMapper
{
    public static function mapCodigo(object $row): ?string
    {
        $n = (int) ($row->suc_numero ?? 0);

        return $n > 0 ? Puntoventa::normalizarCodigoArca((string) $n) : null;
    }

    public static function mapNombre(object $row): string
    {
        $nombre = trim((string) ($row->suc_empresa ?? ''));

        return $nombre !== '' ? $nombre : 'Sucursal '.(string) ($row->suc_numero ?? '');
    }

    public static function mapEmpresaId(object $row): int
    {
        $nroEmp = (int) ($row->suc_nroemp ?? 0);
        if ($nroEmp > 0) {
            $empresa = Empresa::query()->where('codigo', (string) $nroEmp)->first();
            if ($empresa) {
                return (int) $empresa->id;
            }
        }

        $cuit = preg_replace('/\D+/', '', (string) ($row->suc_cuit ?? '')) ?? '';
        if (strlen($cuit) >= 11) {
            foreach (Empresa::query()->get(['id', 'nroinscripcion']) as $empresa) {
                $empCuit = preg_replace('/\D+/', '', (string) $empresa->nroinscripcion) ?? '';
                if ($empCuit !== '' && $empCuit === $cuit) {
                    return (int) $empresa->id;
                }
            }
        }

        $empresaTxt = strtoupper((string) ($row->suc_empresa ?? ''));
        $id = (int) config('puntoventa_anita.empresa_id_default', 3);
        foreach (config('puntoventa_anita.empresa_por_fragmento_suc_empresa', []) as $rule) {
            $frag = strtoupper(trim((string) ($rule['fragmento'] ?? '')));
            if ($frag !== '' && str_contains($empresaTxt, $frag)) {
                $id = (int) ($rule['empresa_id'] ?? $id);
            }
        }

        return $id;
    }

    /**
     * AGG / histórico: N→M, E→E, L→C, A→A, R→R, M→L, O→O, I→I.
     * Ferli: E→C (electrónica CAE), X→E (exportación), F/N→M.
     */
    public static function mapModoFacturacion(object $row): string
    {
        $fiscal = strtoupper(trim((string) ($row->suc_fiscal ?? '')));

        if (EntornoEmpresaSupport::esFerli()) {
            return match ($fiscal) {
                'E' => 'C',
                'X' => 'E',
                'L' => 'C',
                'A' => 'A',
                'R' => 'R',
                'M' => 'L',
                'O' => 'O',
                'I' => 'I',
                'N', 'F' => 'M',
                default => 'M',
            };
        }

        return match ($fiscal) {
            'N' => 'M',
            'E' => 'E',
            'L' => 'C',
            'A' => 'A',
            'R' => 'R',
            'M' => 'L',
            'O' => 'O',
            'I' => 'I',
            default => 'M',
        };
    }

    public static function mapWebservice(object $row): string
    {
        if (EntornoEmpresaSupport::esFerli() && self::mapModoFacturacion($row) === 'E') {
            return 'wsfex_v1';
        }

        return 'wsfev1';
    }

    /** suc_empresa == 'BAJA' → suspendido (repositorio usaba texto equivocado para el enum). */
    public static function mapEstado(object $row): string
    {
        if (trim((string) ($row->suc_empresa ?? '')) === 'BAJA') {
            return 'S';
        }

        return 'A';
    }

    private static function strProp(object $row, string $key): string
    {
        return trim((string) ($row->{$key} ?? ''));
    }

    /**
     * suc_direccion en Anita = código actividad ARCA (numérico, típicamente 6 dígitos).
     */
    public static function codigoActividadDesdeSucDireccion(object $row): ?string
    {
        $raw = self::strProp($row, 'suc_direccion');
        if ($raw === '' || $raw === '-') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '' || strlen($digits) < 4) {
            return null;
        }

        return str_pad($digits, 6, '0', STR_PAD_LEFT);
    }

    public static function mapActividadArcaId(object $row): ?int
    {
        $codigo = self::codigoActividadDesdeSucDireccion($row);
        if ($codigo === null) {
            return null;
        }

        return self::resolverActividadArcaIdPorCodigo($codigo);
    }

    /**
     * Domicilio postal solo si suc_direccion no es un código de actividad ARCA.
     * Devuelve null cuando corresponde conservar el domicilio ya cargado en el ERP.
     */
    public static function mapDomicilio(object $row): ?string
    {
        if (self::codigoActividadDesdeSucDireccion($row) !== null) {
            return null;
        }

        $domicilio = self::strProp($row, 'suc_direccion');

        return $domicilio !== '' ? $domicilio : '-';
    }

    private static function resolverActividadArcaIdPorCodigo(string $codigoNormalizado): ?int
    {
        static $cache = null;
        if (! is_array($cache)) {
            $cache = [];
            foreach (Actividad_Arca::query()->get(['id', 'codigoarca']) as $actividad) {
                $clave = str_pad(preg_replace('/\D+/', '', (string) $actividad->codigoarca) ?: '0', 6, '0', STR_PAD_LEFT);
                $cache[$clave] = (int) $actividad->id;
            }
        }

        return $cache[$codigoNormalizado] ?? null;
    }

    private static function localidadIdDefaultSiExiste(): ?int
    {
        $locId = (int) config('puntoventa_anita.default_localidad_id', 108);
        // Ferli: casa central Villa Madero (evita CABRAL SARGENTO / id 108 del maestro).
        if (EntornoEmpresaSupport::esFerli() && $locId === 108) {
            $locId = (int) config('puntoventa_anita.default_localidad_id_ferli', 4070);
        }
        if ($locId <= 0 || ! Localidad::query()->whereKey($locId)->exists()) {
            return null;
        }

        return $locId;
    }

    private static function provinciaIdDefault(): int
    {
        $provId = (int) config('puntoventa_anita.default_provincia_id', 3);
        // Ferli: Buenos Aires (no Catamarca id 3 del default histórico).
        if (EntornoEmpresaSupport::esFerli() && $provId === 3) {
            return (int) config('puntoventa_anita.default_provincia_id_ferli', 2);
        }

        return $provId;
    }

    /**
     * Domicilio fiscal de la empresa del PV (AGG multiempresa: BSA Avellaneda, KSA Wilde, RSA F. Varela).
     * Preferir siempre esto sobre el default histórico 108/Catamarca del sync.
     *
     * @return array{localidad_id: ?int, provincia_id: ?int, codigopostal: ?string}|null
     */
    private static function domicilioFiscalEmpresa(int $empresaId): ?array
    {
        if ($empresaId <= 0) {
            return null;
        }

        static $cache = [];
        if (! array_key_exists($empresaId, $cache)) {
            $empresa = Empresa::query()
                ->whereKey($empresaId)
                ->first(['localidad_id', 'provincia_id', 'codigopostal']);
            $cache[$empresaId] = $empresa
                ? [
                    'localidad_id' => (int) ($empresa->localidad_id ?? 0) ?: null,
                    'provincia_id' => (int) ($empresa->provincia_id ?? 0) ?: null,
                    'codigopostal' => trim((string) ($empresa->codigopostal ?? '')) ?: null,
                ]
                : null;
        }

        return $cache[$empresaId];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapAll(object $row): array
    {
        $codigo = self::mapCodigo($row);
        $domicilio = self::mapDomicilio($row);
        if ($domicilio === null) {
            $domicilio = '-';
        }

        $empresaId = self::mapEmpresaId($row);
        $fiscal = self::domicilioFiscalEmpresa($empresaId);

        $localidadId = ($fiscal['localidad_id'] ?? null)
            ?: self::localidadIdDefaultSiExiste();
        $provinciaId = ($fiscal['provincia_id'] ?? null)
            ?: self::provinciaIdDefault();

        $codPostal = self::strProp($row, 'suc_cod_postal');
        if ($codPostal === '') {
            $codPostal = $fiscal['codigopostal']
                ?? (EntornoEmpresaSupport::esFerli()
                    ? (string) config('puntoventa_anita.default_codigopostal_ferli', '1768')
                    : null);
        }

        $division = isset($row->suc_division) ? $row->suc_division : null;
        $numeropoliza = self::strProp($row, 'suc_poliza');
        $numeropoliza = $numeropoliza !== '' ? $numeropoliza : null;
        $puntoventaRemito = isset($row->suc_suc_remito) ? $row->suc_suc_remito : null;

        return [
            'nombre' => self::mapNombre($row),
            'codigo' => $codigo,
            'empresa_id' => $empresaId,
            'domicilio' => $domicilio,
            'provincia_id' => $provinciaId,
            'localidad_id' => $localidadId,
            'pais_id' => (int) config('puntoventa_anita.default_pais_id', 1),
            'codigopostal' => $codPostal !== '' && $codPostal !== null ? $codPostal : null,
            'telefono' => self::strProp($row, 'suc_telefono') ?: null,
            'email' => null,
            'leyenda' => self::strProp($row, 'suc_leyenda1') ?: null,
            'modofacturacion' => self::mapModoFacturacion($row),
            'estado' => self::mapEstado($row),
            'webservice' => self::mapWebservice($row),
            // Legacy afip.php: no mapear emails de suc_leyenda2 en Ferli (SOAP no usa pathafip).
            'pathafip' => EntornoEmpresaSupport::esFerli()
                ? null
                : (self::strProp($row, 'suc_leyenda2') ?: null),
            'actividad_arca_id' => self::mapActividadArcaId($row),
            'division' => $division,
            'numeropoliza' => $numeropoliza,
            'puntoventa_remito' => $puntoventaRemito,
        ];
    }
}
