# ZenPHP Framework

A modern, zero-dependency micro-framework for PHP 8.4.

## Requirements

- PHP 8.4+
- PDO extension (SQLite or MySQL)
- OpenSSL extension

## Installation

```bash
composer require studiokaizen/framework
```

---

## Getting Started

Copy `config.example.php` to `config.php` and fill in your values. Generate an encryption key:

```bash
php zen key:generate
```

Run migrations:

```bash
php zen migrate
```

---

## Routing

Routes are defined in `public/index.php`. Every handler receives a `Request` and `Response` and must return a `Response`.

```php
use Zen\Http\Request;
use Zen\Http\Response;

$app->get('/', function (Request $request, Response $response) use ($app): Response {
    return $app->view('home');
});

$app->post('/users', function (Request $request, Response $response) use ($app): Response {
    // ...
});

$app->put('/users/:id', function (Request $request, Response $response) use ($app): Response {
    $id = (int) $request->getRouteParam('id');
    // ...
});

$app->patch('/users/:id', function (Request $request, Response $response) use ($app): Response { /* ... */ });
$app->delete('/users/:id', function (Request $request, Response $response) use ($app): Response { /* ... */ });
```

### Route Parameters

```php
$app->get('/posts/:id/comments/:commentId', function (Request $request, Response $response): Response {
    $postId    = (int) $request->getRouteParam('id');
    $commentId = (int) $request->getRouteParam('commentId');
    // ...
});
```

### Route Middleware

```php
$app->get('/dashboard', $handler)->middleware('csrf', 'auth');
$app->post('/api/items', $handler)->middleware('token', 'throttle:60,1');
```

### Route Groups

```php
$app->group('/admin', function () use ($app): void {
    $app->get('/', $handler);
    $app->get('/users', $handler);
}, ['csrf', 'auth']);
```

---

## Request

```php
$request->all();                          // all input (body + query)
$request->input('name');                  // single input value
$request->input('role', 'user');          // with default
$request->only('name', 'email');          // whitelist
$request->query('page', 1);              // query string value
$request->getRouteParam('id');            // route parameter
$request->getHeader('Authorization');     // request header
$request->isJson();                       // Content-Type: application/json
$request->isAjax();                       // X-Requested-With: XMLHttpRequest
$request->getMethod();                    // GET, POST, etc.
$request->getUri();                       // /path?query
$request->getIp();                        // client IP
```

---

## Response

```php
return $app->view('template', $data);          // render a view (on $app, not $response)
return $response->json($data);                 // JSON response (200)
return $response->json($data, 201);            // JSON with status
return $response->redirect('/path');           // 302 redirect
return $response->status(404)->body('Not found.');
return $response->header('X-Custom', 'value')->json($data);
```

---

## Views

Templates live in `resources/views/` and use plain PHP.

```php
// resources/views/layout.php
<!DOCTYPE html>
<html>
<head>
    <title><?= $this->e($this->yield('title', 'App')) ?></title>
    <?= $this->stack('css') ?>
</head>
<body>
    <?= $this->yield('content') ?>
    <?= $this->stack('scripts') ?>
</body>
</html>
```

```php
// resources/views/home.php
<?php $this->layout('layout'); ?>

<?php $this->section('title', 'Home'); ?>

<?php $this->startSection('content'); ?>
    <h1>Hello, <?= $this->e($name) ?></h1>
<?php $this->endSection(); ?>

<?php $this->append('scripts'); ?>
<script src="/js/page.js"></script>
<?php $this->endStack(); ?>
```

```php
// Render from a route handler
return $app->view('home', ['name' => 'World']);
```

### View API reference

| Method | Description |
|--------|-------------|
| `$this->layout('name')` | Set the parent layout template |
| `$this->yield('name', 'default')` | Output a section (use in layouts) |
| `$this->section('name', 'value')` | Define a section inline (single-line value) |
| `$this->startSection('name')` | Begin buffering a section |
| `$this->endSection()` | End the current section buffer |
| `$this->start('name')` / `$this->end()` | Aliases for startSection / endSection |
| `$this->stack('name')` | Output a stack (use in layouts) |
| `$this->append('name')` | Begin buffering content to append to a stack |
| `$this->prepend('name')` | Begin buffering content to prepend to a stack |
| `$this->endStack()` | End the current stack buffer |
| `$this->e('string')` | HTML-escape a value |
| `$this->share('key', $value)` | Share a variable with all templates |

---

## Validation

```php
use Zen\Validation\ValidationException;

try {
    $data = $app->validator($request->all(), [
        'name'     => 'required|min:2|max:100',
        'email'    => 'required|email|max:255',
        'password' => 'required|min:8',
        'age'      => 'integer|min:18',
        'website'  => 'url',
        'role'     => 'in:admin,editor,viewer',
    ])->validate();
} catch (ValidationException $e) {
    $e->errors(); // ['field' => ['message', ...]]
}
```

Available rules: `required`, `nullable`, `string`, `integer`, `numeric`, `boolean`, `array`, `email`, `url`, `min`, `max`, `between`, `size`, `in`, `not_in`, `same`, `different`, `confirmed`, `regex`.

---

## Database

### Query Builder

```php
// Select
$users = $app['db']->table('users')->get();
$user  = $app['db']->table('users')->find(1);
$user  = $app['db']->table('users')->where('email', 'alice@example.com')->first();

// Chaining
$results = $app['db']->table('posts')
    ->where('user_id', $userId)
    ->where('published', 1)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// Insert / Update / Delete
$id = $app['db']->table('users')->insert(['name' => 'Alice', 'email' => 'alice@example.com']);
$app['db']->table('users')->where('id', $id)->update(['name' => 'Alice Smith']);
$app['db']->table('users')->where('id', $id)->delete();

// Aggregates
$count = $app['db']->table('users')->count();
$count = $app['db']->table('users')->where('active', 1)->count();

// Raw queries
$rows = $app['db']->select('SELECT * FROM posts WHERE user_id = ?', [$userId]);
$row  = $app['db']->selectOne('SELECT COUNT(*) AS total FROM users');
$app['db']->statement('UPDATE users SET active = 1 WHERE id = ?', [$id]);
```

### Pagination

```php
$total = (int) $app['db']->selectOne('SELECT COUNT(*) AS n FROM posts')->n;
$items = $app['db']->select('SELECT * FROM posts ORDER BY id DESC LIMIT ? OFFSET ?', [$perPage, ($page - 1) * $perPage]);

$paginator = new \Zen\Database\Paginator($items, $total, $perPage, $page);

$paginator->items();
$paginator->total();
$paginator->currentPage();
$paginator->lastPage();
$paginator->hasMorePages();
$paginator->nextPage();
$paginator->previousPage();
$paginator->toArray();
```

### Migrations

```bash
php zen make:migration create_posts_table
php zen migrate
php zen migrate:rollback
php zen migrate:fresh --seed
php zen migrate:status
```

Migration files use `-- UP` and `-- DOWN` sections:

```sql
-- UP
CREATE TABLE posts (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL,
    title      VARCHAR(255) NOT NULL,
    body       TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- DOWN
DROP TABLE posts;
```

---

## Authentication

### Session Auth

```php
// Login
$app['auth']->attempt($email, $password); // returns bool
$app['auth']->login($userId);
$app['auth']->logout();

// Current user
$app['auth']->check();         // bool
$app['auth']->id();            // int|null
$app['auth']->user();          // array|null
```

### API Token Auth

```php
// Create a token for a user
$token = $app['tokens']->create($userId, 'api-token');

// Revoke
$app['tokens']->revoke($tokenId);

// Current token (inside a 'token' middleware route)
$app['auth']->token();
```

Use the `token` middleware to protect API routes. Clients send `Authorization: Bearer <token>`.

---

## Middleware

Register aliases in `bootstrap/app.php`:

```php
$app->registerMiddlewareAlias('custom', function ($app) {
    return new \App\Middleware\CustomMiddleware();
});
```

Built-in aliases: `csrf`, `auth`, `guest`, `token`, `throttle`, `cors`.

Attach to routes:

```php
->middleware('csrf', 'auth')
->middleware('throttle:60,1')   // 60 requests per 1 minute
```

### Custom Middleware

```php
use Zen\Middleware\MiddlewareInterface;
use Zen\Http\Request;
use Zen\Http\Response;

class CustomMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        // before
        $response = $next($request, $response);
        // after
        return $response;
    }
}
```

---

## Cache

```php
$app['cache']->set('key', $value, $ttlSeconds);
$app['cache']->get('key');
$app['cache']->get('key', $default);
$app['cache']->has('key');
$app['cache']->forget('key');

// Compute and cache
$value = $app['cache']->remember('key', 300, function () {
    return expensiveOperation();
});
```

---

## Events

```php
use Zen\Events\Event;

class UserRegistered extends Event
{
    public function __construct(
        public readonly int    $userId,
        public readonly string $email,
    ) {}
}
```

```php
// Dispatch
$app['events']->dispatch(new UserRegistered($id, $email));

// Listen
$app['events']->addListener(UserRegistered::class, function (UserRegistered $event) use ($app): void {
    $app['logger']->info("New user #{$event->userId}");
});
```

---

## Queue

```php
use Zen\Queue\Job;

class SendWelcomeEmail extends Job
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}

    public function handle(): void
    {
        // send email
    }
}
```

```php
// Dispatch
$app['queue']->dispatch(new SendWelcomeEmail($name, $email));

// Dispatch to a named queue
$app['queue']->dispatch(new SendWelcomeEmail($name, $email), queue: 'emails');
```

```bash
php zen queue:work
php zen queue:work --queue=emails
php zen queue:work --max-jobs=10
```

---

## Scheduler

```php
// In AppServiceProvider::boot()
$app['scheduler']->call(function () use ($app): void {
    // task logic
})->daily();

$app['scheduler']->call(fn () => /* ... */)->hourly();
$app['scheduler']->call(fn () => /* ... */)->everyFifteenMinutes();
$app['scheduler']->call(fn () => /* ... */)->cron('0 9 * * 1'); // every Monday at 9am
```

```bash
# Add to cron — runs every minute
* * * * * php /path/to/project/zen schedule:run
```

---

## Mail

```php
use Zen\Mail\Message;

$app['mailer']->send(function (Message $message): void {
    $message->to('alice@example.com', 'Alice')
            ->subject('Welcome!')
            ->text('Thanks for signing up.')
            ->html('<p>Thanks for signing up.</p>');
});

// Or pass a Message instance directly
$message = (new Message())
    ->to('alice@example.com', 'Alice')
    ->subject('Welcome!')
    ->text('Thanks for signing up.');

$app['mailer']->send($message);
```

Configure the driver in `config.php` (`log`, `smtp`, or `sendmail`).

---

## Session

```php
$app['session']->start();
$app['session']->set('key', $value);
$app['session']->get('key');
$app['session']->get('key', $default);
$app['session']->has('key');
$app['session']->forget('key');
$app['session']->flash('success', 'Saved!');   // one-time message
$app['session']->flash('success');              // read and clear
$app['session']->regenerate();
$app['session']->destroy();
```

---

## Encryption & Hashing

```php
// Encryption (requires app.key in config.php)
$encrypted = $app['encrypter']->encrypt('secret');
$plain     = $app['encrypter']->decrypt($encrypted);

// Hashing
$hash    = $app['hasher']->make($password);
$isValid = $app['hasher']->verify($password, $hash);
```

---

## Storage

```php
$app['storage']->disk('local')->put('file.txt', 'contents');
$app['storage']->disk('local')->get('file.txt');
$app['storage']->disk('local')->exists('file.txt');
$app['storage']->disk('local')->delete('file.txt');
$app['storage']->disk('local')->files('/');         // list files
```

---

## Logging

```php
$app['logger']->info('User logged in', ['id' => $userId]);
$app['logger']->warning('Disk space low');
$app['logger']->error('Payment failed', ['order' => $orderId]);
```

Logs are written to `storage/logs/app.log`.

---

## Container

The container uses array-access. Every closure binding is a singleton — resolved once on first access, then frozen.

```php
// Register a service (resolved lazily on first access)
$app['myService'] = fn ($app) => new MyService($app['db']);

// Register a raw value
$app['apiKey'] = 'abc123';

// Protect a closure so it is stored as-is, not invoked as a factory
$app['myCallback'] = $app->protect(fn ($value) => strtolower($value));

// Decorate an existing service
$app->extend('mailer', function ($mailer, $app) {
    $mailer->setLogger($app['logger']);
    return $mailer;
});

// Resolve
$service = $app['myService'];

// Check existence
isset($app['myService']);

// Retrieve the original factory closure (before resolution)
$factory = $app->raw('myService');
```

### Service Providers

```php
use Zen\DependencyInjection\ServiceProviderInterface;
use Zen\DependencyInjection\BootableProviderInterface;

class MyServiceProvider implements ServiceProviderInterface, BootableProviderInterface
{
    public function register(Application $app): void
    {
        $app['myService'] = fn ($app) => new MyService();
    }

    public function boot(Application $app): void
    {
        // runs after all providers are registered
    }
}
```

Register in `bootstrap/app.php`:

```php
$app->registerProviders([new MyServiceProvider()]);
```

---

## Support Utilities

### Str

```php
use Zen\Support\Str;

Str::slug('Hello World');           // hello-world
Str::camel('hello_world');          // helloWorld
Str::studly('hello_world');         // HelloWorld
Str::snake('helloWorld');           // hello_world
Str::contains('foobar', 'oba');     // true
Str::startsWith('foobar', 'foo');   // true
Str::endsWith('foobar', 'bar');     // true
Str::limit('Long text...', 10);     // 'Long text…'
Str::random(32);                    // random alphanumeric string
```

### Arr

```php
use Zen\Support\Arr;

Arr::get($array, 'user.address.city', 'Unknown');
Arr::set($array, 'user.name', 'Alice');
Arr::has($array, 'user.email');
Arr::forget($array, 'user.password');
Arr::only($array, ['name', 'email']);
Arr::except($array, ['password']);
Arr::flatten($array);
Arr::pluck($array, 'name');
```

### Collection

```php
use Zen\Support\Collection;

$col = new Collection([1, 2, 3, 4, 5]);

$col->map(fn ($n) => $n * 2);
$col->filter(fn ($n) => $n > 2);
$col->reduce(fn ($carry, $n) => $carry + $n, 0);
$col->first();
$col->last();
$col->count();
$col->toArray();
```

---

## Console Commands

```bash
php zen list                       # list all commands
php zen make:migration <name>      # create a migration
php zen make:seeder <name>         # create a seeder
php zen migrate                    # run pending migrations
php zen migrate:rollback           # roll back last batch
php zen migrate:reset              # roll back all
php zen migrate:fresh              # reset + re-migrate
php zen migrate:fresh --seed       # reset + re-migrate + seed
php zen migrate:status             # show migration status
php zen db:seed                    # run all seeders
php zen queue:work                 # process queued jobs
php zen schedule:run               # run due scheduled tasks
php zen cache:clear                # clear file cache
php zen key:generate               # generate an encryption key
```

### Custom Commands

```php
use Zen\Console\Command;

class GreetCommand extends Command
{
    public function name(): string
    {
        return 'greet';
    }

    public function description(): string
    {
        return 'Greet someone by name.';
    }

    public function handle(array $args, array $options): int
    {
        $name = $args[0] ?? 'World';
        $this->line('Hello, ' . $name . '!');
        return 0;
    }
}
```

Output helpers available inside `handle()`: `$this->line()`, `$this->info()` (green), `$this->warn()` (yellow), `$this->error()` (red).

Register in `bootstrap/app.php`:

```php
$app['console']->add(new GreetCommand());
```

---

## License

MIT
