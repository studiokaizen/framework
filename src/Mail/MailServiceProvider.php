<?php

declare(strict_types=1);

namespace Zen\Mail;

use InvalidArgumentException;
use Zen\Application;
use Zen\DependencyInjection\ServiceProviderInterface;
use Zen\Mail\Transport\LogTransport;
use Zen\Mail\Transport\SendmailTransport;
use Zen\Mail\Transport\SmtpTransport;

/**
 * Registers the Mailer service in the application container.
 */
class MailServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds the 'mailer' service, selecting the transport from
     * config('mail.driver').
     *
     * @param  Application $app
     *
     * @throws InvalidArgumentException If config('mail.driver') names an
     *                                  unknown transport.
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['mailer'] = function (Application $app): Mailer {
            $mail = $app->config('mail', []);
            $driver = $mail['driver'] ?? 'log';
            $from   = $mail['from']   ?? ['address' => 'noreply@example.com', 'name' => ''];

            $transport = match ($driver) {
                'smtp' => new SmtpTransport(
                    host:       $mail['smtp']['host']       ?? '127.0.0.1',
                    port:       (int) ($mail['smtp']['port'] ?? 587),
                    username:   $mail['smtp']['username']   ?? '',
                    password:   $mail['smtp']['password']   ?? '',
                    encryption: $mail['smtp']['encryption'] ?? 'tls',
                ),
                'sendmail' => new SendmailTransport($mail['sendmail'] ?? '/usr/sbin/sendmail -bs'),
                'log'      => new LogTransport($app->logsPath('mail.log')),
                default    => throw new InvalidArgumentException("Unknown mail driver: {$driver}"),
            };

            return new Mailer($transport, $from);
        };
    }
}
