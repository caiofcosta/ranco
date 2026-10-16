const linhasPadrao = [
    '#############',
    '#I....#.....#',
    '#####.#.###.#',
    '#.....#...#.#',
    '#.#######.#.#',
    '#.........#.#',
    '#.#########.#',
    '#.....#.....#',
    '#####.#.###.#',
    '#.....#...#.#',
    '#.#######.#.#',
    '#.........#C#',
    '#############',
];

export function criarMapa(definicao = {}) {
    const linhas = definicao.linhas ?? linhasPadrao;
    const tamanhoCelula = definicao.tamanho_celula ?? 40;
    const raioBolinha = definicao.raio_bolinha ?? 10;
    const velocidade = definicao.velocidade ?? 120;


    function localizar(marca) {
        const linha = linhas.findIndex((texto) => texto.includes(marca));
        const coluna = linhas[linha].indexOf(marca);
        return { x: (coluna + 0.5) * tamanhoCelula, y: (linha + 0.5) * tamanhoCelula };
    }

    const inicio = localizar('I');
    const chegada = localizar('C');
    const largura = linhas[0].length * tamanhoCelula;
    const altura = linhas.length * tamanhoCelula;

    function colide(posicao) {
        for (let linha = Math.floor((posicao.y - raioBolinha) / tamanhoCelula); linha <= Math.floor((posicao.y + raioBolinha) / tamanhoCelula); linha++) {
            for (let coluna = Math.floor((posicao.x - raioBolinha) / tamanhoCelula); coluna <= Math.floor((posicao.x + raioBolinha) / tamanhoCelula); coluna++) {
                if (linhas[linha]?.[coluna] !== '#' && linhas[linha]?.[coluna] !== undefined) continue;
                const pertoX = Math.max(coluna * tamanhoCelula, Math.min(posicao.x, (coluna + 1) * tamanhoCelula));
                const pertoY = Math.max(linha * tamanhoCelula, Math.min(posicao.y, (linha + 1) * tamanhoCelula));
                if (Math.hypot(posicao.x - pertoX, posicao.y - pertoY) < raioBolinha) return true;
            }
        }
        return false;
    }

    function chegou(posicao) {
        return Math.hypot(posicao.x - chegada.x, posicao.y - chegada.y) <= 12;
    }

    return { linhas, tamanhoCelula, raioBolinha, velocidade, localizar, inicio, chegada, largura, altura, colide, chegou };
}

// O fluxo de mesa mantém seu mapa original até a integração das rodadas.
export const mapaPadrao = criarMapa();
export const { linhas, tamanhoCelula, raioBolinha, velocidade, localizar, inicio, chegada, largura, altura, colide, chegou } = mapaPadrao;
