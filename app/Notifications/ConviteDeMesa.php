<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail HTML do convite para uma mesa. Dados e layout: docs/email-convite-de-mesa.md. */
final class ConviteDeMesa extends Notification
{
    use Queueable;

    public const PREHEADER = 'Parece que a paz acabou.';

    public function __construct(
        public readonly string $nomeConvidante,
        public readonly string $nomeMesa,
        public readonly string $urlConvite,
        public readonly string $mensagemTom,
        public readonly string $emailDestinatario,
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $dados = [
            'nomeConvidante' => $this->nomeConvidante,
            'nomeMesa' => $this->nomeMesa,
            'urlConvite' => $this->urlConvite,
            'mensagemTom' => $this->mensagemTom,
            'emailDestinatario' => $this->emailDestinatario,
            'preheader' => self::PREHEADER,
            'urlTom' => url('images/tom-email.png'),
        ];

        return (new MailMessage())
            ->subject($this->nomeConvidante.' te chamou para “'.$this->nomeMesa.'”')
            ->view([
                'html' => 'emails.convite-de-mesa',
                'text' => 'emails.convite-de-mesa-texto',
            ], $dados);
    }
}
