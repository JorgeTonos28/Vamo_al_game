<?php

namespace App\Notifications;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly UserInvitation $invitation,
        private readonly string $plainToken,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = route('invitations.accept', [
            'invitation' => $this->invitation,
            'token' => $this->plainToken,
        ]);

        return (new MailMessage)
            ->subject('Tienes una invitacion a Vamo al Game')
            ->greeting('Hola '.$notifiable->name)
            ->line('Se te ha enviado una invitacion para entrar a Vamo al Game.')
            ->line('Desde el enlace puedes definir tu contrasena o continuar con Google para completar tu acceso.')
            ->action('Aceptar invitacion', $acceptUrl)
            ->line('Este enlace vence el '.$this->invitation->expires_at->format('d/m/Y H:i').'.');
    }
}
