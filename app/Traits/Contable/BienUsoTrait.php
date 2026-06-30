<?php

namespace App\Traits\Contable;

trait BienUsoTrait
{
    /** @var list<array{id: string, valor: string, nombre: string}> */
    public static array $enumEstado = [
        ['id' => '1', 'valor' => 'A', 'nombre' => 'Activo'],
        ['id' => '2', 'valor' => 'I', 'nombre' => 'Inactivo'],
    ];

    /** @var list<array{id: string, valor: string, nombre: string}> */
    public static array $enumTipoBien = [
        ['id' => '1', 'valor' => 'I', 'nombre' => 'Instalaciones'],
        ['id' => '2', 'valor' => 'M', 'nombre' => 'Máquinas'],
        ['id' => '3', 'valor' => 'P', 'nombre' => 'PCs'],
    ];

    public static function labelEnum(string $valor, array $enum): string
    {
        foreach ($enum as $item) {
            if (($item['valor'] ?? '') === $valor) {
                return (string) ($item['nombre'] ?? $valor);
            }
        }

        return $valor;
    }

    public static function labelEstado(?string $valor): string
    {
        return self::labelEnum((string) $valor, self::$enumEstado);
    }

    public static function labelTipoBien(?string $valor): string
    {
        return self::labelEnum((string) $valor, self::$enumTipoBien);
    }

    public function etiqueta(): string
    {
        return self::construirEtiqueta(
            $this->tipo_bien,
            $this->uid,
            $this->codigo_inventario,
            $this->hostname,
            $this->modelo,
            $this->vendor,
            $this->tema,
            (int) $this->id
        );
    }

    public static function construirEtiqueta(
        ?string $tipoBien,
        ?string $uid,
        int|string|null $codigoInventario,
        ?string $hostname,
        ?string $modelo = null,
        ?string $vendor = null,
        ?string $tema = null,
        int $id = 0
    ): string {
        if ($tipoBien === 'M' && trim((string) $uid) !== '') {
            $partes = array_filter([
                trim((string) $uid),
                $vendor,
                $modelo,
                $tema,
            ]);

            return implode(' — ', $partes) ?: ('Bien #'.$id);
        }

        $partes = array_filter([
            $codigoInventario ? '#'.$codigoInventario : null,
            $hostname,
            $modelo,
        ]);

        return implode(' — ', $partes) ?: ('Bien #'.$id);
    }
}
