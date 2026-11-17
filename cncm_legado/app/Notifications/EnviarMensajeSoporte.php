<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnviarMensajeSoporte extends Notification
{
    use Queueable;

    private $nombre;
    private $email;
    private $mensaje;

    public function __construct($nombre,$email, $mensaje)
    {
        $this->nombre = $nombre;
        $this->email = $email;
        $this->mensaje = $mensaje;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Solicitud de Soporte')
            ->greeting('Hola')
            ->line('Se ha enviado un mensaje de soporte desde '.config('app.name').' con la siguiente información:')
            ->line('Nombre: '.$this->nombre)
            ->line('Correo: '.$this->email)
            ->line('Mensaje: '.$this->mensaje)
            ->line('Recuerda contestar en menos de 24 hrs. Gracias por formar parte de '.config('settings.company.empresa'))
            ->salutation('Saludos');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
