<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carga `provincia_id` en los conceptos de IVA compras de IIBB que hoy lo tienen nulo.
 *
 * La jurisdicción de la provincia es lo que permite cotejar una percepción IIBB contra el
 * padrón (ARBA 902 / AGIP 901) y detectar cuando el concepto elegido no corresponde. Se
 * resuelve por el nombre del concepto, que es el único dato disponible.
 *
 * SIRCREB queda sin provincia a propósito: es un régimen multijurisdiccional, no de una
 * jurisdicción única. Solo se tocan filas con `provincia_id` nulo, así que no pisa nada
 * cargado a mano.
 */
return new class extends Migration
{
    /** Patrón sobre el nombre del concepto => jurisdicción IIBB. */
    private const REGLAS = [
        ['jurisdiccion' => '901', 'patron' => '/CAPITAL|\bCABA\b|\bAGIP\b/u'],
        ['jurisdiccion' => '902', 'patron' => '/\bARBA\b|\bBS\.?\s*AS\.?\b|\bBSAS\b|BUENOS AIRES/u'],
    ];

    public function up(): void
    {
        foreach ($this->resolverAsignaciones() as $asignacion) {
            DB::table('concepto_ivacompra')
                ->where('id', $asignacion['concepto_id'])
                ->whereNull('provincia_id')
                ->update([
                    'provincia_id' => $asignacion['provincia_id'],
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // No se revierte: dejar el concepto sin provincia rompería el cotejo con el padrón
        // y no hay forma de distinguir lo cargado por esta migración de lo cargado a mano.
    }

    /**
     * @return list<array{concepto_id: int, provincia_id: int, nombre: string, jurisdiccion: string}>
     */
    private function resolverAsignaciones(): array
    {
        $provinciaPorJurisdiccion = [];
        foreach (self::REGLAS as $regla) {
            $provinciaId = DB::table('provincia')
                ->where('jurisdiccion', $regla['jurisdiccion'])
                ->orderBy('id')
                ->value('id');
            if ($provinciaId !== null) {
                $provinciaPorJurisdiccion[$regla['jurisdiccion']] = (int) $provinciaId;
            }
        }

        $conceptos = DB::table('concepto_ivacompra')
            ->select('id', 'codigo', 'nombre', 'tipoconcepto')
            ->whereIn('tipoconcepto', ['B', 'S', 'A'])
            ->whereNull('provincia_id')
            ->orderBy('codigo')
            ->get();

        $asignaciones = [];
        foreach ($conceptos as $concepto) {
            $nombre = $this->normalizar((string) $concepto->nombre);

            // SIRCREB no pertenece a una jurisdicción única.
            if (str_contains($nombre, 'SIRCREB')) {
                continue;
            }

            foreach (self::REGLAS as $regla) {
                if (! preg_match($regla['patron'], $nombre)) {
                    continue;
                }
                if (! isset($provinciaPorJurisdiccion[$regla['jurisdiccion']])) {
                    break;
                }
                $asignaciones[] = [
                    'concepto_id' => (int) $concepto->id,
                    'provincia_id' => $provinciaPorJurisdiccion[$regla['jurisdiccion']],
                    'nombre' => (string) $concepto->nombre,
                    'jurisdiccion' => $regla['jurisdiccion'],
                ];
                break;
            }
        }

        return $asignaciones;
    }

    private function normalizar(string $texto): string
    {
        return str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'],
            ['A', 'E', 'I', 'O', 'U', 'U', 'N'],
            mb_strtoupper($texto)
        );
    }
};
