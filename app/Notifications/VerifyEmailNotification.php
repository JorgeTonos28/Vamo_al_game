<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends BaseVerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verifica tu correo en Vamo al Game')
            ->greeting('Activa tu cuenta')
            ->line('Recibimos un registro en Vamo al Game con este correo: '.$notifiable->email)
            ->line('Para entrar a la app, primero debes confirmar que ese correo te pertenece.')
            ->action('Verificar correo', $verificationUrl)
            ->line('Si no creaste esta cuenta, puedes ignorar este mensaje.')
            ->salutation('Equipo de Vamo al Game');
    }
}
