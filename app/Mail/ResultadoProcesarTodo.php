<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResultadoProcesarTodo extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string, array{estado: string, filas_guardadas: int}> $resultados */
    public function __construct(public string $fecha, public array $resultados) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Proceso automatico {$this->fecha}: ".(in_array('error', array_column($this->resultados, 'estado'), true) ? 'incompleto' : 'finalizado'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.resultado-procesar-todo',
            text: 'emails.resultado-procesar-todo-texto',
        );
    }
}
