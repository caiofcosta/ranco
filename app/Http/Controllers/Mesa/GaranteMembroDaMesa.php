<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mesa;

use App\Dominio\CodigoDaRegra;
use App\Dominio\Mesa\Mesa;
use App\Dominio\NaoEncontrado;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Só traduz para HTTP: quem pode ver ou agir na mesa é decidido pela Plataforma. */
trait GaranteMembroDaMesa
{
    private function mesaDoMembro(Plataforma $plataforma, string $id): Mesa
    {
        return $this->traduzir(fn (string $pessoa): Mesa => $plataforma->mesaDoMembro($id, $pessoa));
    }

    private function mesaDoMembroSemAvancarPrazo(Plataforma $plataforma, string $id): Mesa
    {
        return $this->traduzir(fn (string $pessoa): Mesa => $plataforma->mesaDoMembroSemAvancarPrazo($id, $pessoa));
    }

    private function mesaDoAdmin(Plataforma $plataforma, string $id): Mesa
    {
        return $this->traduzir(fn (string $pessoa): Mesa => $plataforma->mesaDoAdmin($id, $pessoa));
    }

    /** Autoriza consulta; ações continuam usando mesaDoMembro ou mesaDoAdmin. */
    private function mesaParaVisualizar(Plataforma $plataforma, string $id): Mesa
    {
        return $this->traduzir(fn (string $pessoa): Mesa => $plataforma->mesaParaConsulta($id, $pessoa));
    }

    /** @param callable(string): Mesa $buscar */
    private function traduzir(callable $buscar): Mesa
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        try {
            return $buscar($usuario->identificadorNoDominio());
        } catch (NaoEncontrado) {
            throw new NotFoundHttpException();
        } catch (RegraViolada $erro) {
            if ($erro->codigo !== CodigoDaRegra::Invisivel) {
                throw $erro;
            }

            throw new HttpResponseException(redirect()->route('dashboard')->withErrors(['mesa' => $erro->getMessage()]));
        }
    }
}
