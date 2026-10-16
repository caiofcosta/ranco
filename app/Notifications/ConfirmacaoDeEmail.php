<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ConfirmacaoDeEmail extends Notification
{
    use Queueable;

    public function __construct(public readonly string $url)
    {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Confirme seu e-mail no RANÇO')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Falta só confirmar o e-mail. O botão abaixo confirma e já entra na sua conta.')
            ->action('Confirmar e-mail', $this->url)
            ->line('O link vale por 48 horas e funciona uma única vez.')
            ->line('Se não foi você quem criou a conta, é só ignorar este e-mail.');
    }
}
