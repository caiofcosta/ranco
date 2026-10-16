export function direcaoDoJoystick(x, y, raio = 48) {
    const distancia = Math.hypot(x, y);
    const zonaMorta = 6;
    if (distancia <= zonaMorta) return { x: 0, y: 0 };
    const intensidade = Math.min(1, (distancia - zonaMorta) / (raio - zonaMorta));
    return { x: x / distancia * intensidade, y: y / distancia * intensidade };
}
