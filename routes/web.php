<?php

use App\Http\Controllers\Acesso\AcessoPorLinkController;
use App\Http\Controllers\Acesso\CadastroController;
use App\Http\Controllers\Acesso\ConfirmacaoDeEmailController;
use App\Http\Controllers\Acesso\ContaGoogleController;
use App\Http\Controllers\Amizade\AmigosController;
use App\Http\Controllers\Amizade\ConviteDeAmizadeController;
use App\Http\Controllers\Conta\ParticipacoesLocaisController;
use App\Http\Controllers\Convite\EntrarPorConviteController;
use App\Http\Controllers\CriarMesaController;
use App\Http\Controllers\HistoricoController;
use App\Http\Controllers\NotificacoesController;
use App\Http\Controllers\Inicio\AceitarConviteController;
use App\Http\Controllers\Inicio\RecusarConviteController;
use App\Http\Controllers\Inicio\SuasMesasController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\Jogos\AssistirVezController;
use App\Http\Controllers\Jogos\JogarVezController;
use App\Http\Controllers\Jogos\LabirintoController;
use App\Http\Controllers\Jogos\MostrarJogoController;
use App\Http\Controllers\Jogos\MostrarJogoIndisponivelController;
use App\Http\Controllers\Mesa\AdmitirNaPartidaController;
use App\Http\Controllers\Mesa\CadastrarParticipanteLocalController;
use App\Http\Controllers\Mesa\CancelarConviteController;
use App\Http\Controllers\Mesa\ConvidarAmigoController;
use App\Http\Controllers\Mesa\CriarPartidaController;
use App\Http\Controllers\Mesa\DespausarPartidaController;
use App\Http\Controllers\Mesa\EncerrarMesaController;
use App\Http\Controllers\Mesa\EncerrarPartidaController;
use App\Http\Controllers\Mesa\FormularioCriarMesaController;
use App\Http\Controllers\Mesa\IniciarPartidaController;
use App\Http\Controllers\Mesa\MostrarMesaController;
use App\Http\Controllers\Mesa\PausarPartidaController;
use App\Http\Controllers\Mesa\PularVezController;
use App\Http\Controllers\Mesa\RemoverDaPartidaController;
use App\Http\Controllers\Mesa\RenomearMesaController;
use App\Http\Controllers\Mesa\SairDaMesaController;
use App\Http\Controllers\Perfil\MostrarPerfilController;
use App\Http\Middleware\GaranteJogoDisponivel;
use App\Http\Middleware\LimitarPedidosDeEmail;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages::inicio')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', InicioController::class)->name('dashboard');
    Route::get('inicio/suas-mesas', SuasMesasController::class)->name('inicio.suas-mesas');
    foreach (['inicio', 'pedidos', 'jogar-juntos', 'mais'] as $area) {
        Route::get($area, InicioController::class)->defaults('areaMobile', $area)->name('mobile.'.$area);
    }
    Route::get('historico', HistoricoController::class)->name('historico');
    Route::get('notificacoes', NotificacoesController::class)->name('notificacoes');
    Route::post('mesas', CriarMesaController::class)->name('mesas.criar');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('mesas/criar', FormularioCriarMesaController::class)->name('mesas.formulario');
    Route::get('mesas/{id}', MostrarMesaController::class)->name('mesas.mostrar');
    Route::patch('mesas/{id}', RenomearMesaController::class)->name('mesas.renomear');
    Route::post('mesas/{id}/participantes-locais', CadastrarParticipanteLocalController::class)->name('mesas.participantes-locais');
    Route::get('mesas/{id}/encerrar', [EncerrarMesaController::class, 'formulario'])->name('mesas.encerrar');
    Route::post('mesas/{id}/encerrar', [EncerrarMesaController::class, 'encerrar']);
    Route::get('mesas/{id}/sair', [SairDaMesaController::class, 'formulario'])->name('mesas.sair');
    Route::post('mesas/{id}/sair', [SairDaMesaController::class, 'sair']);
});

// Cadastro e acesso sem senha (link por e-mail e conta Google). Login e recuperação de senha vêm do Fortify.
Route::middleware('guest')->group(function () {
    Route::get('register', [CadastroController::class, 'formulario'])->name('register');
    Route::post('register', [CadastroController::class, 'cadastrar'])
        ->middleware(LimitarPedidosDeEmail::class)
        ->name('register.store');
    Route::get('cadastro/confira-seu-email', [CadastroController::class, 'confiraSeuEmail'])->name('cadastro.confira-seu-email');
    Route::post('cadastro/reenviar-link', [CadastroController::class, 'reenviarLink'])
        ->middleware(LimitarPedidosDeEmail::class)
        ->name('cadastro.reenviar-link');

    Route::get('entrar-por-link', [AcessoPorLinkController::class, 'formulario'])->name('acesso-por-link');
    Route::post('entrar-por-link', [AcessoPorLinkController::class, 'enviar'])
        ->middleware(LimitarPedidosDeEmail::class)
        ->name('acesso-por-link.enviar');

    Route::get('auth/google', [ContaGoogleController::class, 'redirecionar'])->name('google.redirecionar');
    Route::get('auth/google/callback', [ContaGoogleController::class, 'retorno'])->name('google.retorno');
});

Route::get('entrar-por-link/{usuario}/{codigo}', [AcessoPorLinkController::class, 'entrar'])->name('acesso-por-link.entrar');
Route::get('confirmar-email/{usuario}/{codigo}', [ConfirmacaoDeEmailController::class, 'confirmar'])->name('confirmacao-de-email');

// Amizade: convite por e-mail, aceite pelo link e a tela de amigos.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('amigos', [AmigosController::class, 'index'])->name('amigos.index');
    Route::post('amigos/convites', [AmigosController::class, 'convidar'])
        ->middleware(LimitarPedidosDeEmail::class)
        ->name('amigos.convidar');
    Route::post('amigos/convites/{convite}/aceitar', [AmigosController::class, 'aceitar'])->name('amigos.convites.aceitar');
    Route::post('amigos/convites/{convite}/recusar', [AmigosController::class, 'recusar'])->name('amigos.convites.recusar');
    Route::post('amigos/{amigo}/desfazer', [AmigosController::class, 'desfazer'])->name('amigos.desfazer');
    Route::post('amigos/{amigo}/bloquear', [AmigosController::class, 'bloquear'])->name('amigos.bloquear');
});

// Convite de mesa: o link do e-mail. Quem não está logado passa pelo login ou pelo cadastro e volta.
Route::middleware(['signed', 'auth', 'verified'])->group(function () {
    Route::get('convites/{convite}/entrar', [EntrarPorConviteController::class, 'mostrar'])
        ->where('convite', '[0-9a-f]{16}')
        ->name('convites.entrar');
    Route::post('convites/{convite}/entrar', [EntrarPorConviteController::class, 'aceitar'])
        ->where('convite', '[0-9a-f]{16}')
        ->name('convites.aceitar');
});

// Convite de mesa na tela da mesa e na home. Não reusa o nome convites.aceitar (esse é o do e-mail).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('mesas/{id}/convites', ConvidarAmigoController::class)->name('mesas.convites.criar');
    Route::post('mesas/{id}/convites/{convite}/cancelar', CancelarConviteController::class)->name('mesas.convites.cancelar');
    Route::post('inicio/convites/{convite}/aceitar', AceitarConviteController::class)->name('inicio.convites.aceitar');
    Route::post('inicio/convites/{convite}/recusar', RecusarConviteController::class)->name('inicio.convites.recusar');
});

require __DIR__.'/settings.php';

Route::view('/jogar', 'jogos.index')->middleware(['auth', 'verified'])->name('jogar.index');
Route::post('/painel/labirinto/previa', [LabirintoController::class, 'previa'])->middleware(['auth', 'verified'])->name('painel.labirinto.previa');

// Jogo solo: independente de mesa e de partida.
Route::middleware(['auth', 'verified'])->prefix('jogar/labirinto')->name('jogar.labirinto')->group(function () {
    Route::get('/', [LabirintoController::class, 'escolher'])->middleware(GaranteJogoDisponivel::class.':labirinto');
    Route::post('/iniciar', [LabirintoController::class, 'iniciar'])->middleware(['throttle:60,1', GaranteJogoDisponivel::class.':labirinto'])->name('.iniciar');
    Route::get('/tentativas/{tentativa}', [LabirintoController::class, 'jogar'])->whereUuid('tentativa')->name('.tentativa');
    Route::post('/resultado', [LabirintoController::class, 'salvar'])->name('.salvar');
    Route::get('/resultado', [LabirintoController::class, 'resultado'])->name('.resultado');
});

Route::get('/jogar/{jogo}', MostrarJogoIndisponivelController::class)
    ->middleware(['auth', 'verified'])
    ->name('jogar.indisponivel');

// Partida dentro da mesa. A tela de jogar a vez (mesas.jogar) fica com o outro fluxo.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('mesas/{id}/partidas', CriarPartidaController::class)->name('mesas.partidas.criar');
    Route::post('mesas/{id}/partida/iniciar', IniciarPartidaController::class)->name('mesas.partida.iniciar');
    Route::post('mesas/{id}/partida/pausar', PausarPartidaController::class)->name('mesas.partida.pausar');
    Route::post('mesas/{id}/partida/despausar', DespausarPartidaController::class)->name('mesas.partida.despausar');
    Route::post('mesas/{id}/partida/pular', PularVezController::class)->name('mesas.partida.pular');
    Route::post('mesas/{id}/partida/admitir', AdmitirNaPartidaController::class)->name('mesas.partida.admitir');
    Route::post('mesas/{id}/partida/remover', RemoverDaPartidaController::class)->name('mesas.partida.remover');
    Route::get('mesas/{id}/partida/encerrar', [EncerrarPartidaController::class, 'formulario'])->name('mesas.partida.encerrar');
    Route::post('mesas/{id}/partida/encerrar', [EncerrarPartidaController::class, 'encerrar']);
});

// Área da conta: participações locais pendentes do e-mail, para reconhecer ou rejeitar.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('conta/participacoes-locais', [ParticipacoesLocaisController::class, 'index'])->name('conta.participacoes-locais');
    Route::post('conta/participacoes-locais/{participacao}/reconhecer', [ParticipacoesLocaisController::class, 'reconhecer'])->name('conta.participacoes-locais.reconhecer');
    Route::post('conta/participacoes-locais/{participacao}/rejeitar', [ParticipacoesLocaisController::class, 'rejeitar'])->name('conta.participacoes-locais.rejeitar');
});

// Jogada da partida: o domínio inicia e conclui a tentativa dentro da mesa.
Route::middleware(['auth', 'verified'])->prefix('mesas/{id}/jogar')->name('mesas.jogar')->group(function () {
    Route::get('/', [JogarVezController::class, 'mostrar']);
    Route::post('/iniciar', [JogarVezController::class, 'iniciar'])->name('.iniciar');
    Route::post('/resultado', [JogarVezController::class, 'resultado'])->name('.resultado');
});

// Perfil: medalhas da própria conta ou de uma amizade aceita.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('perfil', MostrarPerfilController::class)->name('perfil.mostrar');
    Route::get('perfil/{pessoa}', MostrarPerfilController::class)->name('perfil.amigo');
});

Route::get('/mesas/{id}/assistir', AssistirVezController::class)->middleware(['auth', 'verified'])->name('mesas.assistir');

Route::get('/mesas/{id}/jogo', MostrarJogoController::class)->middleware(['auth', 'verified'])->name('mesas.jogo');

// API: Modais de notificação
Route::middleware(['auth', 'verified'])->prefix('api')->group(function () {
    Route::post('modal-notificacao/visualizar', [\App\Http\Controllers\API\ModalDeNotificacaoController::class, 'visualizar']);
    Route::post('modal-notificacao/dismissar', [\App\Http\Controllers\API\ModalDeNotificacaoController::class, 'dismissar']);
});

require __DIR__.'/aceite.php';

// Links antigos de amizade: página pública sem dados do convite.
Route::get('amizade/{codigo}', [ConviteDeAmizadeController::class, 'mostrar'])->name('amizade.convite');
Route::post('amizade/{codigo}', [ConviteDeAmizadeController::class, 'aceitar'])->name('amizade.aceitar');
