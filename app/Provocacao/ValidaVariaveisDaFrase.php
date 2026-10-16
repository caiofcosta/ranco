<?php

declare(strict_types=1);

namespace App\Provocacao;

use App\Models\MomentoDeProvocacao;

/**
 * Confere se as variáveis do texto cabem em algum momento que aceite os temas da frase.
 * O sorteio do domínio já descarta o que não cabe; a curadoria impede gravar o que nunca sairia.
 */
final class ValidaVariaveisDaFrase
{
    /**
     * @param  list<string>  $temas
     * @return array{ok: bool, disponiveis: list<string>}
     */
    public static function conferir(string $texto, array $temas): array
    {
        $usadas = self::noTexto($texto);
        $momentos = $temas === []
            ? collect()
            : MomentoDeProvocacao::query()
                ->whereHas(
                    'temas',
                    static fn ($consulta) => $consulta->whereIn('temas_de_provocacao.codigo', $temas),
                )
                ->get();

        $disponiveis = [];
        $algumMomentoCobre = $usadas === [];

        foreach ($momentos as $momento) {
            $oferecidas = $momento->variaveisOferecidas();
            $disponiveis = array_merge($disponiveis, $oferecidas);

            if (array_diff($usadas, $oferecidas) === []) {
                $algumMomentoCobre = true;
            }
        }

        $disponiveis = array_values(array_unique($disponiveis));
        sort($disponiveis);

        return [
            'ok' => $algumMomentoCobre,
            'disponiveis' => $disponiveis,
        ];
    }

    /** @return list<string> */
    public static function noTexto(string $texto): array
    {
        preg_match_all('/\{(\w+)\}/u', $texto, $achados);

        return array_values(array_unique($achados[1]));
    }

    /** @param list<string> $temas */
    public static function mensagemDeErro(string $texto, array $temas): ?string
    {
        $resultado = self::conferir($texto, $temas);
        if ($resultado['ok']) {
            return null;
        }

        $lista = $resultado['disponiveis'] === []
            ? 'nenhuma'
            : implode(', ', array_map(
                static fn (string $nome): string => '{'.$nome.'}',
                $resultado['disponiveis'],
            ));

        return "Essas variáveis não são oferecidas pelos momentos dos temas escolhidos. Variáveis disponíveis: {$lista}.";
    }
}
