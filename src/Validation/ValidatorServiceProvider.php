<?php

declare(strict_types=1);

namespace Zen\Validation;

use Zen\Application;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers the validator factory as a protected closure in the container.
 */
class ValidatorServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds 'validator' as a protected factory callable so callers receive the
     * closure itself rather than its return value.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['validator'] = $app->protect(static function (array $data, array $rules, array $messages = []): Validator {
            return Validator::make($data, $rules, $messages);
        });
    }
}
