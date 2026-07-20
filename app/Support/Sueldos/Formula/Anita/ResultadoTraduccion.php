<?php

namespace App\Support\Sueldos\Formula\Anita;

/**
 * Resultado de traducir un concepto Anita (una o varias líneas de habformula)
 * a la sintaxis del motor de fórmulas del ERP.
 */
class ResultadoTraduccion
{
    /** Fórmula de importe ERP (última línea sin ':='). */
    public ?string $formula = null;

    /** Fórmula de cantidad ERP (línea CA:=). */
    public ?string $formulaCantidad = null;

    /** Fórmula de valor ERP (línea VA:=). */
    public ?string $formulaValor = null;

    /** true si se pudo parsear todo (sin errores de sintaxis). */
    public bool $traducible = true;

    /**
     * true si hay aproximaciones o funciones/variables sin equivalente exacto:
     * el operador debe revisar antes de usar en liquidación real.
     */
    public bool $requiereRevision = false;

    /** @var list<string> Mensajes de aviso (aproximaciones, líneas descartadas, etc.). */
    public array $advertencias = [];

    /**
     * @var list<string> Funciones/variables Anita sin equivalente en el ERP
     *                   (requieren implementar dominio o cargar datos).
     */
    public array $noTraducibles = [];

    /** @var list<string> Funciones Anita usadas (nombres en mayúsculas). */
    public array $funcionesUsadas = [];

    /** @var list<string> Variables Anita usadas (nombres en mayúsculas). */
    public array $variablesUsadas = [];

    /** @var list<string> Líneas originales de Anita (para el reporte). */
    public array $lineasOriginales = [];

    public function agregarAdvertencia(string $msg): void
    {
        if (! in_array($msg, $this->advertencias, true)) {
            $this->advertencias[] = $msg;
        }
    }

    public function agregarNoTraducible(string $msg): void
    {
        if (! in_array($msg, $this->noTraducibles, true)) {
            $this->noTraducibles[] = $msg;
        }
        $this->requiereRevision = true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'formula' => $this->formula,
            'formula_cantidad' => $this->formulaCantidad,
            'formula_valor' => $this->formulaValor,
            'traducible' => $this->traducible,
            'requiere_revision' => $this->requiereRevision,
            'advertencias' => $this->advertencias,
            'no_traducibles' => $this->noTraducibles,
            'funciones_usadas' => $this->funcionesUsadas,
            'variables_usadas' => $this->variablesUsadas,
            'lineas_originales' => $this->lineasOriginales,
        ];
    }
}
