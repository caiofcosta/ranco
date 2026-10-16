<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convites_de_amizade', function (Blueprint $tabela) {
            $tabela->foreignId('para')->nullable()->constrained('users')->nullOnDelete();
            $tabela->index(['para', 'aceito_em', 'recusado_em', 'expira_em'], 'convites_recebidos');
        });

        $migrados = 0;
        $apagados = 0;
        DB::table('convites_de_amizade')->orderBy('id')->chunkById(500, function ($convites) use (&$migrados, &$apagados): void {
            foreach ($convites as $convite) {
                $pessoa = DB::table('users')->where('email', mb_strtolower(trim($convite->email)))->value('id');
                if ($convite->aceito_por !== null) {
                    $pessoa = DB::table('users')->where('id', $convite->aceito_por)->value('id');
                }
                if ($pessoa === null && $convite->aceito_em === null && $convite->recusado_em === null) {
                    $apagados += DB::table('convites_de_amizade')->where('id', $convite->id)->delete();

                    continue;
                }
                DB::table('convites_de_amizade')->where('id', $convite->id)->update(['para' => $pessoa]);
                if ($pessoa !== null) {
                    $migrados++;
                }
            }
        });

        Schema::table('convites_de_amizade', function (Blueprint $tabela) {
            $tabela->dropIndex(['email']);
            $tabela->dropUnique(['codigo_hash']);
            $tabela->dropColumn(['email', 'codigo_hash']);
        });

        Log::info('Convites de amizade migrados para contas.', ['migrados' => $migrados, 'apagados' => $apagados]);
        if (app()->runningInConsole()) {
            echo "Convites de amizade: {$migrados} migrados; {$apagados} apagados.\n";
        }
    }

    public function down(): void
    {
        throw new LogicException('Migração somente para frente: e-mails e códigos removidos não podem ser recuperados.');
    }
};
