<?php

namespace App\Support\Contable\MayorPlanoCuenta;

final class MayorPlanoCuentaCentrocostoFiltroSupport
{
    /** @var list<string> */
    private array $codigos;

    private string $desde;

    private string $hasta;

    private bool $incluirSinCc;

    /**
     * @param  string|array<int, string|int>|null  $codigos
     */
    public function __construct(
        string|array|null $codigos = null,
        ?string $desde = null,
        ?string $hasta = null,
        ?bool $incluirSinCc = null,
    ) {
        $this->codigos = self::parsearCodigos($codigos);
        $this->desde = self::normalizarCodigo($desde);
        $this->hasta = self::normalizarCodigo($hasta);

        if ($this->desde !== '' && $this->hasta !== ''
            && self::compararCodigos($this->desde, $this->hasta) > 0) {
            [$this->desde, $this->hasta] = [$this->hasta, $this->desde];
        }

        $this->incluirSinCc = $incluirSinCc ?? ! $this->tieneFiltro();
    }

    /**
     * @param  string|array<int, string|int>|null  $valor
     * @return list<string>
     */
    public static function parsearCodigos(string|array|null $valor): array
    {
        $tokens = is_array($valor)
            ? $valor
            : (preg_split('/[,;\s]+/', trim((string) $valor)) ?: []);
        $codigos = [];

        foreach ($tokens as $token) {
            $codigo = self::normalizarCodigo((string) $token);
            if ($codigo !== '') {
                $codigos[$codigo] = $codigo;
            }
        }

        $lista = array_values($codigos);
        usort($lista, self::compararCodigos(...));

        return $lista;
    }

    public function pasaFiltro(?string $codigoCc): bool
    {
        $codigo = self::normalizarCodigo($codigoCc);
        if ($codigo === '') {
            return $this->incluirSinCc;
        }

        if (! $this->tieneFiltro()) {
            return true;
        }

        if (in_array($codigo, $this->codigos, true)) {
            return true;
        }

        if ($this->desde === '' && $this->hasta === '') {
            return false;
        }
        if ($this->desde !== '' && self::compararCodigos($codigo, $this->desde) < 0) {
            return false;
        }
        if ($this->hasta !== '' && self::compararCodigos($codigo, $this->hasta) > 0) {
            return false;
        }

        return true;
    }

    public function tieneFiltro(): bool
    {
        return $this->codigos !== [] || $this->desde !== '' || $this->hasta !== '';
    }

    public function incluirSinCc(): bool
    {
        return $this->incluirSinCc;
    }

    /** @return list<string> */
    public function codigos(): array
    {
        return $this->codigos;
    }

    public function metaTexto(): string
    {
        $partes = [];
        if ($this->codigos !== []) {
            $partes[] = 'Lista CC: '.implode(', ', $this->codigos);
        }
        if ($this->desde !== '' || $this->hasta !== '') {
            $partes[] = 'Rango CC: '.($this->desde !== '' ? $this->desde : '…')
                .' a '.($this->hasta !== '' ? $this->hasta : '…');
        }
        if ($partes === []) {
            return 'Todos los centros de costo';
        }

        $partes[] = $this->incluirSinCc ? 'incluye sin CC' : 'excluye sin CC';

        return implode(' · ', $partes);
    }

    private static function normalizarCodigo(?string $codigo): string
    {
        $codigo = trim((string) $codigo);
        if ($codigo !== '' && ctype_digit($codigo)) {
            $codigo = (string) ((int) $codigo);
        }

        return $codigo === '0' ? '' : $codigo;
    }

    private static function compararCodigos(string $a, string $b): int
    {
        if (ctype_digit($a) && ctype_digit($b)) {
            return ((int) $a) <=> ((int) $b);
        }

        return strnatcasecmp($a, $b);
    }
}
