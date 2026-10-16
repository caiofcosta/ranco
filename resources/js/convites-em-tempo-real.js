export function ouvirConvitesDaConta({ pessoaId }) {
    if (!pessoaId) return;

    import('./echo.js').then(() => {
        window.Echo.private(`App.Models.User.${pessoaId}`)
            .listen('.convite.recebido', () => window.Livewire?.dispatch('convites-mudaram'));
    });
}
