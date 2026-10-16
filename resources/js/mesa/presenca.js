export function incluirPresenca(presentes, membro) {
    return [...presentes.filter((atual) => atual.id !== membro.id), membro];
}

export function removerPresenca(presentes, id) {
    return presentes.filter((atual) => atual.id !== id);
}
