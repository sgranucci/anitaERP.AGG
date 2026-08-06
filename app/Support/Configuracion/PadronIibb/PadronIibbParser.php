<?php

declare(strict_types=1);

namespace App\Support\Configuracion\PadronIibb;

/**
 * Lee el archivo de una provincia y lo traduce a líneas normalizadas.
 *
 * El motor de carga (PadronIibbTasaCargaService) no conoce el layout de cada
 * provincia: toda la particularidad del formato vive en el parser.
 */
interface PadronIibbParser
{
    /** Código de jurisdicción IIBB (901, 902, 904, 908, 914, 921, 924…). */
    public function jurisdiccion(): int;

    /** Nombre para logs, mails y pantalla. */
    public function etiqueta(): string;

    /** @return list<string> Extensiones aceptadas para el archivo de entrada. */
    public function extensiones(): array;

    /** Descripción del layout esperado, para mostrar en la pantalla de importación. */
    public function formatoEsperado(): string;

    /**
     * Los padrones que traen la percepción y la retención en líneas distintas se
     * recorren en dos pasadas: primero las de percepción y después las de retención.
     * Así el resultado no depende del orden en que vengan dentro del archivo.
     */
    public function separaPercepcionRetencion(): bool;

    /**
     * True cuando el archivo es la foto mensual del padrón y todas sus líneas
     * comparten vigencia: la carga reemplaza ese período y descarta lo que no
     * coincida. False cuando cada contribuyente trae su propia vigencia, en cuyo
     * caso se reemplaza el padrón completo de la provincia.
     */
    public function periodoUnico(): bool;

    /** Devuelve null cuando la línea es cabecera, está vacía o es inválida. */
    public function parseLinea(string $raw): ?PadronIibbLinea;
}
