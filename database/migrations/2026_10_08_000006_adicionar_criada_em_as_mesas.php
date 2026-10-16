<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data de criação da mesa em coluna (UTC), para as contagens do dashboard do Painel. A fonte original
 * continua sendo `Mesa::criadaEm()` dentro do `estado` serializado.
 *
 * Backfill: só quando o estado traz uma data comprovadamente da própria mesa. Sem data, estado
 * ilegível ou data impossível: `criada_em` fica NULL. `atualizada_em` NUNCA vira data de criação
 * (só serve de teto: uma mesa não nasce depois da última gravação). O `estado` não é alterado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $tabela) {
            $tabela->dateTime('criada_em', 3)->nullable()->index();
        });

        $this->preencherCriadaEm();
    }

    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $tabela) {
            $tabela->dropColumn('criada_em');
        });
    }

    /** @return int Quantas mesas receberam a data. */
    public function preencherCriadaEm(): int
    {
        $preenchidas = 0;

        DB::table('mesas')->whereNull('criada_em')->select('id', 'estado', 'atualizada_em')
            ->chunkById(100, function ($mesas) use (&$preenchidas): void {
                foreach ($mesas as $mesa) {
                    $criada = $this->criacaoNoEstado((string) $mesa->estado);
                    if ($criada === null || $criada > $mesa->atualizada_em) {
                        continue;
                    }
                    // Sem tocar em `versao`: não atrapalha a concorrência otimista da mesa.
                    $preenchidas += DB::table('mesas')->where('id', $mesa->id)->whereNull('criada_em')
                        ->update(['criada_em' => $criada]);
                }
            });

        return $preenchidas;
    }

    /**
     * Lê só o `criadaEm` da própria mesa, sem carregar nenhuma classe do domínio: só as datas são
     * instanciadas; a Mesa e tudo dentro dela viram __PHP_Incomplete_Class (o criadaEm de cada partida
     * fica aninhado e nunca é confundido com o da mesa). Nulo quando não houver valor confiável.
     */
    public function criacaoNoEstado(string $estado): ?string
    {
        $mesa = @unserialize($estado, ['allowed_classes' => [DateTimeImmutable::class, DateTimeZone::class]]);
        if (! is_object($mesa)) {
            return null;
        }

        $propriedades = (array) $mesa;
        if (($propriedades['__PHP_Incomplete_Class_Name'] ?? null) !== 'App\Dominio\Mesa\Mesa') {
            return null;
        }

        $criada = $propriedades['criadaEm'] ?? null;

        return $criada instanceof DateTimeImmutable
            ? $criada->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v')
            : null;
    }
};
