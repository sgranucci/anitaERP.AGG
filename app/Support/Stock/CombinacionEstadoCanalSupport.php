<?php

namespace App\Support\Stock;

use App\Models\Ventas\Canal;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Schema;

/**
 * Estados fábrica/local del maestro de combinaciones (Ferli).
 *
 * Valores: A / I (igual que combinacion.estado legacy y Anita comb_estado).
 *
 * Ámbito por programa (no por “dónde está sentado” el usuario):
 * - POS / stock local / precios local: estado_local
 * - Pedidos / OT / remito / factura fábrica / mov. stock: estado_fabrica
 * - Anita fábrica: solo lee/escribe estado_fabrica (+ deriva estado legacy)
 */
final class CombinacionEstadoCanalSupport
{
    public const ESTADO_ACTIVO = 'A';

    public const ESTADO_INACTIVO = 'I';

    public const AMBITO_FABRICA = 'FABRICA';

    public const AMBITO_LOCAL = 'LOCAL';

    public const AMBITO_AMBOS = 'AMBOS';

    public static function columnasEstadoDisponibles(): bool
    {
        return Schema::hasTable('combinacion')
            && Schema::hasColumn('combinacion', 'estado_fabrica')
            && Schema::hasColumn('combinacion', 'estado_local');
    }

    public static function uiFerliActiva(): bool
    {
        return EntornoEmpresaSupport::esFerli() && self::columnasEstadoDisponibles();
    }

    public static function normalizarEstado(?string $estado): string
    {
        $v = strtoupper(trim((string) $estado));

        return $v === self::ESTADO_INACTIVO ? self::ESTADO_INACTIVO : self::ESTADO_ACTIVO;
    }

    /**
     * estado legacy = A si fábrica o local están activos.
     */
    public static function derivarEstadoLegacy(string $estadoFabrica, string $estadoLocal): string
    {
        $fab = self::normalizarEstado($estadoFabrica);
        $loc = self::normalizarEstado($estadoLocal);

        return ($fab === self::ESTADO_ACTIVO || $loc === self::ESTADO_ACTIVO)
            ? self::ESTADO_ACTIVO
            : self::ESTADO_INACTIVO;
    }

    /**
     * Columna de filtro según ámbito (con fallback a estado legacy).
     */
    public static function columnaPorAmbito(string $ambito): string
    {
        if (! self::columnasEstadoDisponibles()) {
            return 'combinacion.estado';
        }

        $ambito = strtoupper(trim($ambito));

        return $ambito === self::AMBITO_LOCAL || $ambito === Canal::CODIGO_LOCAL
            ? 'combinacion.estado_local'
            : 'combinacion.estado_fabrica';
    }

    /**
     * @param  Builder<\App\Models\Stock\Combinacion>|QueryBuilder  $query
     * @return Builder<\App\Models\Stock\Combinacion>|QueryBuilder
     */
    public static function scopeActivasEnAmbito($query, string $ambito = self::AMBITO_FABRICA)
    {
        $col = self::columnaPorAmbito($ambito);

        return $query->where($col, self::ESTADO_ACTIVO);
    }

    /**
     * Replica un estado único a ambos ámbitos (toggle legacy / no Ferli).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function aplicarEstadoUnicoEnData(array $data, string $estado): array
    {
        $norm = self::normalizarEstado($estado);
        $data['estado'] = $norm;

        if (self::columnasEstadoDisponibles()) {
            $data['estado_fabrica'] = $norm;
            $data['estado_local'] = $norm;
        }

        return $data;
    }

    /**
     * Persistencia desde form Ferli (estados por ámbito) o radio legacy `estado`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizarDataFormulario(array $data): array
    {
        if (! self::columnasEstadoDisponibles()) {
            if (array_key_exists('estado', $data)) {
                $data['estado'] = self::normalizarEstado($data['estado'] ?? self::ESTADO_ACTIVO);
            }

            return $data;
        }

        if (self::uiFerliActiva()
            && (array_key_exists('estado_fabrica', $data) || array_key_exists('estado_local', $data))) {
            $fab = self::normalizarEstado($data['estado_fabrica'] ?? ($data['estado'] ?? self::ESTADO_ACTIVO));
            $loc = self::normalizarEstado($data['estado_local'] ?? ($data['estado'] ?? self::ESTADO_ACTIVO));
            $data['estado_fabrica'] = $fab;
            $data['estado_local'] = $loc;
            $data['estado'] = self::derivarEstadoLegacy($fab, $loc);

            return $data;
        }

        return self::aplicarEstadoUnicoEnData($data, (string) ($data['estado'] ?? self::ESTADO_ACTIVO));
    }

    /**
     * Payload de updateState AJAX: ámbito FABRICA | LOCAL | AMBOS (default AMBOS legacy).
     *
     * @return array{estado:string,estado_fabrica?:string,estado_local?:string}
     */
    public static function payloadUpdateState(string $estado, ?string $ambito = null): array
    {
        $norm = self::normalizarEstado($estado);
        $ambito = strtoupper(trim((string) $ambito));

        if (! self::columnasEstadoDisponibles() || $ambito === '' || $ambito === self::AMBITO_AMBOS) {
            return self::aplicarEstadoUnicoEnData([], $norm);
        }

        if ($ambito === self::AMBITO_LOCAL || $ambito === Canal::CODIGO_LOCAL) {
            return [
                'estado_local' => $norm,
                'estado' => self::derivarEstadoLegacy(
                    self::ESTADO_INACTIVO, // placeholder; el caller debe recalcular con fila actual
                    $norm
                ),
            ];
        }

        return [
            'estado_fabrica' => $norm,
            'estado' => self::derivarEstadoLegacy(
                $norm,
                self::ESTADO_INACTIVO
            ),
        ];
    }

    /**
     * Arma el update de un registro existente según ámbito, recalculando estado legacy.
     *
     * @param  object{estado?:mixed,estado_fabrica?:mixed,estado_local?:mixed}  $row
     * @return array<string, string>
     */
    public static function updateDesdeAmbito(object $row, string $estado, ?string $ambito = null): array
    {
        $norm = self::normalizarEstado($estado);
        $ambito = strtoupper(trim((string) $ambito));

        if (! self::columnasEstadoDisponibles() || $ambito === '' || $ambito === self::AMBITO_AMBOS) {
            return self::aplicarEstadoUnicoEnData([], $norm);
        }

        $fabActual = self::normalizarEstado(
            (string) ($row->estado_fabrica ?? $row->estado ?? self::ESTADO_INACTIVO)
        );
        $locActual = self::normalizarEstado(
            (string) ($row->estado_local ?? $row->estado ?? self::ESTADO_INACTIVO)
        );

        if ($ambito === self::AMBITO_LOCAL || $ambito === Canal::CODIGO_LOCAL) {
            $locActual = $norm;
        } else {
            $fabActual = $norm;
        }

        return [
            'estado_fabrica' => $fabActual,
            'estado_local' => $locActual,
            'estado' => self::derivarEstadoLegacy($fabActual, $locActual),
        ];
    }

    /**
     * Fragmento SQL para whereRaw / exists (columna según ámbito).
     */
    public static function sqlColumnaActiva(string $ambito = self::AMBITO_FABRICA): string
    {
        return self::columnaPorAmbito($ambito)." = '".self::ESTADO_ACTIVO."'";
    }

    /**
     * Columna corta sin alias de tabla (queries sobre modelo Combinacion).
     */
    public static function columnaCortaPorAmbito(string $ambito): string
    {
        if (! self::columnasEstadoDisponibles()) {
            return 'estado';
        }

        $ambito = strtoupper(trim($ambito));

        return $ambito === self::AMBITO_LOCAL || $ambito === Canal::CODIGO_LOCAL
            ? 'estado_local'
            : 'estado_fabrica';
    }

    /**
     * Valor a enviar a Anita (solo fábrica).
     */
    public static function estadoParaAnita(object $row): string
    {
        if (self::columnasEstadoDisponibles() && isset($row->estado_fabrica)) {
            return self::normalizarEstado((string) $row->estado_fabrica);
        }

        return self::normalizarEstado((string) ($row->estado ?? self::ESTADO_ACTIVO));
    }
}
