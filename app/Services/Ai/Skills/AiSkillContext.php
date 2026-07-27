<?php

namespace App\Services\Ai\Skills;

use App\Models\Seguridad\Usuario;

/**
 * Contexto de ejecución de una Skill (capa "Context" de SAP: datos + identidad + alcance).
 * La Skill arma el grounding a partir de esto; nunca del prompt libre del usuario.
 */
final class AiSkillContext
{
    /**
     * @param  array<string,mixed>  $entradas   Insumos de la skill (texto OCR, ids, etc.).
     * @param  int|null             $empresaId  Empresa del comprobante/operación.
     * @param  Usuario|null         $usuario    Identidad que ejecuta (principal propagation).
     * @param  string|null          $entidadTipo Tipo de entidad de negocio afectada.
     * @param  int|null             $entidadId  Id de la entidad (si ya existe).
     */
    public function __construct(
        public readonly array $entradas = [],
        public readonly ?int $empresaId = null,
        public readonly ?Usuario $usuario = null,
        public readonly ?string $entidadTipo = null,
        public readonly ?int $entidadId = null,
    ) {}

    public function entrada(string $clave, mixed $default = null): mixed
    {
        return $this->entradas[$clave] ?? $default;
    }

    public function usuario(): ?Usuario
    {
        return $this->usuario ?? auth()->user();
    }

    public function usuarioId(): ?int
    {
        $u = $this->usuario();

        return $u?->getKey() !== null ? (int) $u->getKey() : (auth()->id() ?: null);
    }
}
