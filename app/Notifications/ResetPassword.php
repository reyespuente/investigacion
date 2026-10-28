<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseReset;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPassword extends BaseReset
{
    public function toMail($notifiable)
    {
        return (new MailMessage)->subject('Restablecer tu contraseña · Investigación ITC')->view('emails.notification', ['heading' => 'Restablece tu contraseña', 'bodyText' => 'Recibimos una solicitud para restablecer la contraseña de tu cuenta. Si no la hiciste, puedes ignorar este mensaje.', 'action' => 'Elegir una nueva contraseña', 'url' => route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]), 'note' => 'El enlace vence en '.config('auth.passwords.users.expire').' minutos. Nunca te pediremos tu contraseña por correo.']);
    }
}
