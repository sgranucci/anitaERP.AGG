<?php

namespace App\Support\Configuracion;

use App\Models\Compras\Requisicion_Estado;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Sala\RequisicionSalaEstado;
use App\Support\Compras\OrdencompraEstados;

/**
 * Estado del documento al aprobar un nivel (`documento_estado_al_aprobar`).
 * Solo aplica a tipos que cambian el estado del comprobante al firmar (RE/RS/OC/SU).
 * PE/OV/SP/PP/AR no lo usan: el circuito cierra por código propio.
 */
final class ArbolaprobacionNivelDocumentoEstadoSupport
{
    /**
     * Códigos de tipoarbol (valor del enum) que usan «Estado doc.».
     *
     * @var list<string>
     */
    private const CODIGOS_CON_ESTADO_DOC = ['RE', 'RS', 'OC', 'SU'];

    public static function codigoTipo(?string $nombreTipo): ?string
    {
        $nombre = trim((string) $nombreTipo);
        if ($nombre === '') {
            return null;
        }
        foreach (Arbolaprobacion::$enumTipoArbol as $row) {
            if (($row['nombre'] ?? '') === $nombre) {
                return (string) ($row['valor'] ?? '');
            }
        }

        return null;
    }

    public static function usaEstadoDocumento(?string $nombreTipo): bool
    {
        $codigo = self::codigoTipo($nombreTipo);

        return $codigo !== null && in_array($codigo, self::CODIGOS_CON_ESTADO_DOC, true);
    }

    /**
     * @return list<string> Nombres de tipoarbol que muestran la columna Estado doc.
     */
    public static function nombresTipoConEstadoDocumento(): array
    {
        $out = [];
        foreach (Arbolaprobacion::$enumTipoArbol as $row) {
            if (in_array((string) ($row['valor'] ?? ''), self::CODIGOS_CON_ESTADO_DOC, true)) {
                $out[] = (string) $row['nombre'];
            }
        }

        return $out;
    }

    /**
     * Opciones del select para un tipo (vacío si el tipo no usa el campo).
     *
     * @return list<array{nombre: string}>
     */
    public static function opcionesParaTipo(?string $nombreTipo): array
    {
        $codigo = self::codigoTipo($nombreTipo);
        if ($codigo === null || ! in_array($codigo, self::CODIGOS_CON_ESTADO_DOC, true)) {
            return [];
        }

        return match ($codigo) {
            'RE' => Requisicion_Estado::estadosArbolRequisicionConfigurables(),
            'RS' => RequisicionSalaEstado::estadosArbolConfigurables(),
            'OC', 'SU' => OrdencompraEstados::estadosArbolConfigurables(),
            default => [],
        };
    }

    /**
     * Mapa nombreTipo → opciones, para el JS del ABM.
     *
     * @return array<string, list<array{nombre: string}>>
     */
    public static function mapaOpcionesPorTipo(): array
    {
        $mapa = [];
        foreach (Arbolaprobacion::$enumTipoArbol as $row) {
            $nombre = (string) ($row['nombre'] ?? '');
            if ($nombre === '') {
                continue;
            }
            $mapa[$nombre] = self::opcionesParaTipo($nombre);
        }

        return $mapa;
    }

    public static function defaultParaTipo(?string $nombreTipo): ?string
    {
        if (self::codigoTipo($nombreTipo) === 'RE') {
            $idx = array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'), true);

            return $idx === false ? null : (string) Requisicion_Estado::$enumEstado[$idx]['nombre'];
        }

        return null;
    }
}
