<?php

namespace App\Services\Contable\Ai;

use App\Services\Ai\AiGateway;
use App\Services\Ai\AiPrompt;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillInterface;
use App\Services\Ai\Skills\AiSkillResult;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaOcCompraResumenSupport;

/**
 * Frase corta de qué se compró en una o varias OC, a partir de los ítems (sin inventar).
 */
final class ResumirCompraOrdencompraSkill implements AiSkillInterface
{
    public const NOMBRE = 'resumir_compra_ordencompra';

    public function __construct(
        private readonly AiGateway $gateway,
    ) {
    }

    public function nombre(): string
    {
        return self::NOMBRE;
    }

    public function ejecutar(AiSkillContext $contexto): AiSkillResult
    {
        $ocs = $contexto->entrada('ocs');
        if (! is_array($ocs) || $ocs === []) {
            return AiSkillResult::fallo('La skill requiere ocs con ítems.');
        }

        $permitidos = [];
        $lineas = [];
        foreach ($ocs as $oc) {
            if (! is_array($oc)) {
                continue;
            }
            $id = (int) ($oc['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $permitidos[$id] = true;
            $items = is_array($oc['items'] ?? null) ? $oc['items'] : [];
            $lineas[] = 'OC '.$id.': '.MayorPlanoCuentaOcCompraResumenSupport::resumenDeterministico($items);
        }

        if ($permitidos === []) {
            return AiSkillResult::fallo('No hay OC válidas para resumir.');
        }

        $system = <<<'SYS'
Sos un analista de compras de un ERP argentino.
Con el listado de ítems (ya agrupados) redactá UNA frase corta en español por cada OC, diciendo qué se compró.
Reglas:
- No inventes artículos ni cantidades que no estén en el listado.
- No pongas montos, ni número de OC, ni "Observación".
- Máximo 160 caracteres por frase.
- Devolvé JSON estricto: {"resumenes":[{"oc":123,"texto":"..."}]}
SYS;

        $result = $this->gateway->generar(new AiPrompt(
            prompt: "Ítems por OC:\n".implode("\n", $lineas)."\n\nRespondé SOLO JSON.",
            system: $system,
            esperaJson: true,
            temperature: 0.2,
            maxTokens: 800,
            timeout: (int) config('ai.skills.resumir_compra_ordencompra.timeout', 40),
            meta: ['skill' => self::NOMBRE, 'ocs' => array_keys($permitidos)],
        ));

        if (! $result->ok || ! is_array($result->json)) {
            return AiSkillResult::fallo($result->error ?? 'El modelo no devolvió JSON válido.');
        }

        $textos = [];
        $filas = $result->json['resumenes'] ?? null;
        if (! is_array($filas)) {
            return AiSkillResult::fallo('JSON sin resumenes.');
        }

        foreach ($filas as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $id = (int) ($fila['oc'] ?? 0);
            $texto = trim((string) ($fila['texto'] ?? ''));
            if ($id <= 0 || $texto === '' || ! isset($permitidos[$id])) {
                continue;
            }
            if (mb_strlen($texto) > 200) {
                $texto = mb_substr($texto, 0, 197).'…';
            }
            $textos[$id] = $texto;
        }

        if ($textos === []) {
            return AiSkillResult::fallo('El modelo no devolvió textos utilizables.');
        }

        return AiSkillResult::sugerencia(['textos' => $textos], 0.7);
    }
}
