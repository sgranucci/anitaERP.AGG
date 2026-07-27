<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiDriverInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Punto único de acceso a modelos (equivalente al GenAI Hub de SAP).
 * Resuelve el driver, aplica el kill-switch global y normaliza la salida en AiResult.
 * No decide permisos de negocio: eso es responsabilidad de AiPolicy dentro de cada Skill.
 */
final class AiGateway
{
    /** @var array<string,AiDriverInterface> */
    private array $drivers;

    /**
     * @param  iterable<AiDriverInterface>  $drivers
     */
    public function __construct(iterable $drivers, private AiPolicy $policy)
    {
        $this->drivers = [];
        foreach ($drivers as $driver) {
            $this->drivers[$driver->nombre()] = $driver;
        }
    }

    /**
     * Ejecuta el prompt contra el driver indicado (o el default global).
     * Solo el kill-switch corta el acceso a modelos; AI_HABILITADO gobierna Skills,
     * no llamadas de módulos con su propio feature flag (PDF-IA, OCR, etc.).
     * Devuelve siempre un AiResult (ok=false ante kill-switch o error del driver).
     */
    public function generar(AiPrompt $prompt): AiResult
    {
        if ($this->policy->killSwitchActivo()) {
            return AiResult::fallo('IA cortada por AI_KILL_SWITCH');
        }

        $nombre = $prompt->driver ?? (string) config('ai.driver', 'ollama');
        $driver = $this->driver($nombre);

        Log::channel((string) config('ai.log_channel', 'ai'))->debug('ai.gateway_generar', [
            'driver' => $nombre,
            'model' => $prompt->model,
            'espera_json' => $prompt->esperaJson,
            'meta' => $prompt->meta,
        ]);

        return $driver->generar($prompt);
    }

    public function driver(string $nombre): AiDriverInterface
    {
        if (! isset($this->drivers[$nombre])) {
            throw new RuntimeException('Driver IA no registrado: '.$nombre);
        }

        return $this->drivers[$nombre];
    }

    /** @return array<int,string> */
    public function driversDisponibles(): array
    {
        $out = [];
        foreach ($this->drivers as $nombre => $driver) {
            if ($driver->disponible()) {
                $out[] = $nombre;
            }
        }

        return $out;
    }
}
