<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class LinkDeAcesso extends Notification
{
    use Queueable;

    /** `pediuCadastro`: a pessoa tentou criar uma conta com este e-mail, que já tem conta. */
    public function __construct(
        public readonly string $url,
        public readonly bool $pediuCadastro = false,
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Seu link de acesso ao RANÇO')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line($this->pediuCadastro
                ? 'Pediram uma conta nova com este e-mail, mas ele já tem conta no RANÇO. Use o botão abaixo para entrar.'
                : 'Use o botão abaixo para entrar sem senha.')
            ->action('Entrar no RANÇO', $this->url)
            ->line('O link vale por 30 minutos e funciona uma única vez.')
            ->line('Se não foi você quem pediu, é só ignorar este e-mail.');
    }
}
