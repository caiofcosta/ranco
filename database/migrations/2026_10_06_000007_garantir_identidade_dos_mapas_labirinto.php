<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar antes do DDL: não renomear nem excluir mapas existentes.
        $nomes = [];
        $desenhos = [];
        $registrar = function (string $hash, int $id) use (&$desenhos): void {
            if (isset($desenhos[$hash]) && $desenhos[$hash] !== $id) {
                throw new RuntimeException('Existem desenhos repetidos entre mapas. Resolva os conflitos antes de migrar.');
            }
            $desenhos[$hash] = $id;
        };
        foreach (DB::table('mapas_labirinto')->orderBy('id')->get() as $mapa) {
            $chave = $this->chaveNome($mapa->nome);
            if (isset($nomes[$chave])) {
                throw new RuntimeException('Existem nomes repetidos entre mapas. Resolva os conflitos antes de migrar.');
            }
            $nomes[$chave] = (int) $mapa->id;
            $registrar($this->chaveDesenho(json_decode($mapa->rascunho, true, 512, JSON_THROW_ON_ERROR)), (int) $mapa->id);
        }
        foreach (DB::table('versoes_labirinto')->get() as $versao) {
            $desenho = json_decode($versao->desenho, true, 512, JSON_THROW_ON_ERROR);
            $registrar($this->chaveDesenho($desenho['linhas']), (int) $versao->mapa_id);
        }
        Schema::table('mapas_labirinto', function (Blueprint $tabela) {
            $tabela->char('chave_nome', 64)->nullable()->unique();
        });
        foreach ($nomes as $chave => $id) {
            DB::table('mapas_labirinto')->where('id', $id)->update(['chave_nome' => $chave]);
        }
        Schema::table('mapas_labirinto', function (Blueprint $tabela) {
            $tabela->char('chave_nome', 64)->nullable(false)->change();
        });
        Schema::create('desenhos_labirinto', function (Blueprint $tabela) {
            $tabela->char('hash', 64)->primary();
            $tabela->foreignId('mapa_id')->constrained('mapas_labirinto')->restrictOnDelete();
        });
        foreach ($desenhos as $hash => $id) {
            DB::table('desenhos_labirinto')->insert(['hash' => $hash, 'mapa_id' => $id]);
        }
    }

    // Algoritmos congelados desta migração; não depender das classes futuras do domínio.
    private function chaveNome(string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '' || mb_strlen($nome) > 80) {
            throw new RuntimeException('Informe um nome com até 80 caracteres.');
        }

        return hash('sha256', mb_strtolower($nome, 'UTF-8'));
    }

    /** @param array<mixed> $linhas */
    private function chaveDesenho(array $linhas): string
    {
        if (! array_is_list($linhas) || count($linhas) !== 13) {
            throw new RuntimeException('O mapa precisa ter 13 linhas.');
        }
        foreach ($linhas as $linha) {
            if (! is_string($linha) || preg_match('/^[#.IC]{13}$/D', $linha) !== 1) {
                throw new RuntimeException('Cada linha precisa ter 13 células válidas.');
            }
        }

        return hash('sha256', implode("\n", $linhas));
    }

    public function down(): void
    {
        Schema::dropIfExists('desenhos_labirinto');
        Schema::table('mapas_labirinto', function (Blueprint $tabela) {
            $tabela->dropUnique(['chave_nome']);
            $tabela->dropColumn('chave_nome');
        });
    }
};
