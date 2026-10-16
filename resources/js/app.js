import './navegacao-mobile.js';
import { configuracaoDoJogo } from './mesa/configuracao-do-jogo.js';
import { inicializarOnboarding } from './onboarding-modal-mobile.js';
import { ouvirConvitesDaConta } from './convites-em-tempo-real.js';
import { ouvirSuasMesas } from './suas-mesas-em-tempo-real.js';

window.configuracaoDoJogo = configuracaoDoJogo;
window.inicializarOnboarding = inicializarOnboarding;

const pessoaId = document.querySelector('meta[name="pessoa-id"]')?.content;
ouvirConvitesDaConta({ pessoaId });
ouvirSuasMesas({ pessoaId });
