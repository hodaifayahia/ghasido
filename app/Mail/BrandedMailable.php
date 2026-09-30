<?php

namespace App\Mail;

use App\Services\Mail\MailDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Factory as Queue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Every GHASIDO email (client request 2026-09-29): the branded layout in
 * resources/views/mail/layout.blade.php, an HTML and a plain-text part, and
 * "Send emails immediately" from Settings → Email.
 *
 * When that switch is on, queue() delivers on the `sync` connection so the
 * email leaves now even with no queue worker (MailDelivery); a failure
 * falls back to the database queue instead of breaking the request.
 */
abstract class BrandedMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @return mixed
     */
    public function queue(Queue $queue)
    {
        $default = $this->connection;

        return app(MailDelivery::class)->push($default, function (?string $connection) use ($queue) {
            $this->connection = $connection;

            return parent::queue($queue);
        }, static::class);
    }

    /**
     * A delayed email is sent at once too when there is no worker to wait
     * for; the sync connection ignores the delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return mixed
     */
    public function later($delay, Queue $queue)
    {
        $default = $this->connection;

        return app(MailDelivery::class)->push($default, function (?string $connection) use ($delay, $queue) {
            $this->connection = $connection;

            return parent::later($delay, $queue);
        }, static::class);
    }
}
