<?php

declare(strict_types=1);

namespace Zen\Scheduling;

use DateTimeInterface;

/**
 * A single scheduled task with a cron expression, an optional label, and the
 * callback to invoke when due.
 */
class Event
{
    /**
     * Cron expression defining when the event should run.
     *
     * @var string
     */
    private string $expression = '* * * * *';

    /**
     * Optional human-readable label shown in CLI output.
     *
     * @var string|null
     */
    private ?string $label = null;

    /**
     * Stores the callable that will be invoked when the event runs.
     *
     * @param  mixed $callback Any callable.
     *
     * @return void
     */
    public function __construct(private readonly mixed $callback)
    {
    }

    // ─── Cron expression ─────────────────────────────────────────────────────

    /**
     * Sets an arbitrary cron expression for this event.
     *
     * @param  string $expression Standard 5-field cron expression.
     *
     * @return static
     */
    public function cron(string $expression): static
    {
        $this->expression = $expression;

        return $this;
    }

    /**
     * Schedules the event to run every minute.
     *
     * @return static
     */
    public function everyMinute(): static
    {
        return $this->cron('* * * * *');
    }

    /**
     * Schedules the event to run every two minutes.
     *
     * @return static
     */
    public function everyTwoMinutes(): static
    {
        return $this->cron('*/2 * * * *');
    }

    /**
     * Schedules the event to run every five minutes.
     *
     * @return static
     */
    public function everyFiveMinutes(): static
    {
        return $this->cron('*/5 * * * *');
    }

    /**
     * Schedules the event to run every ten minutes.
     *
     * @return static
     */
    public function everyTenMinutes(): static
    {
        return $this->cron('*/10 * * * *');
    }

    /**
     * Schedules the event to run every fifteen minutes.
     *
     * @return static
     */
    public function everyFifteenMinutes(): static
    {
        return $this->cron('*/15 * * * *');
    }

    /**
     * Schedules the event to run every thirty minutes.
     *
     * @return static
     */
    public function everyThirtyMinutes(): static
    {
        return $this->cron('*/30 * * * *');
    }

    /**
     * Schedules the event to run at the top of every hour.
     *
     * @return static
     */
    public function hourly(): static
    {
        return $this->cron('0 * * * *');
    }

    /**
     * Schedules the event to run at a specific minute of every hour.
     *
     * @param  int $minute Minute offset (0–59).
     *
     * @return static
     */
    public function hourlyAt(int $minute): static
    {
        return $this->cron("{$minute} * * * *");
    }

    /**
     * Schedules the event to run once daily at midnight.
     *
     * @return static
     */
    public function daily(): static
    {
        return $this->cron('0 0 * * *');
    }

    /**
     * Schedules the event to run once daily at the given HH:MM time.
     *
     * @param  string $time Time string in 'HH:MM' format.
     *
     * @return static
     */
    public function dailyAt(string $time): static
    {
        [$hour, $minute] = explode(':', $time) + [0, '00'];

        return $this->cron("{$minute} {$hour} * * *");
    }

    /**
     * Schedules the event to run twice daily at the given hours.
     *
     * @param  int $firstHour  First hour (0–23).
     * @param  int $secondHour Second hour (0–23).
     *
     * @return static
     */
    public function twiceDaily(int $firstHour = 1, int $secondHour = 13): static
    {
        return $this->cron("0 {$firstHour},{$secondHour} * * *");
    }

    /**
     * Schedules the event to run once weekly on Sunday at midnight.
     *
     * @return static
     */
    public function weekly(): static
    {
        return $this->cron('0 0 * * 0');
    }

    /**
     * Schedules the event to run once weekly on the given day and time.
     *
     * @param  int    $day  Day of week (0 = Sunday, 6 = Saturday).
     * @param  string $time Time in 'HH:MM' format.
     *
     * @return static
     */
    public function weeklyOn(int $day, string $time = '00:00'): static
    {
        [$hour, $minute] = explode(':', $time) + [0, '00'];

        return $this->cron("{$minute} {$hour} * * {$day}");
    }

    /**
     * Schedules the event to run on the first day of every month at midnight.
     *
     * @return static
     */
    public function monthly(): static
    {
        return $this->cron('0 0 1 * *');
    }

    /**
     * Schedules the event to run on a specific day of every month.
     *
     * @param  int    $day  Day of month (1–31).
     * @param  string $time Time in 'HH:MM' format.
     *
     * @return static
     */
    public function monthlyOn(int $day = 1, string $time = '00:00'): static
    {
        [$hour, $minute] = explode(':', $time) + [0, '00'];

        return $this->cron("{$minute} {$hour} {$day} * *");
    }

    // ─── Metadata ─────────────────────────────────────────────────────────────

    /**
     * Sets a human-readable description shown in CLI output and logs.
     *
     * @param  string $label
     *
     * @return static
     */
    public function description(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Returns the label if set, otherwise falls back to the cron expression.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->label ?? $this->expression;
    }

    /**
     * Returns the raw cron expression for this event.
     *
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    // ─── Execution ────────────────────────────────────────────────────────────

    /**
     * Returns true if the event's cron expression matches the given time.
     *
     * @param  DateTimeInterface $now Reference time to test against.
     *
     * @return bool
     */
    public function isDue(DateTimeInterface $now): bool
    {
        $parts = explode(' ', $this->expression);

        return $this->matches($parts[0], (int) $now->format('i'))   // minute
            && $this->matches($parts[1], (int) $now->format('G'))   // hour
            && $this->matches($parts[2], (int) $now->format('j'))   // day of month
            && $this->matches($parts[3], (int) $now->format('n'))   // month
            && $this->matches($parts[4], (int) $now->format('w'));  // day of week
    }

    /**
     * Invokes the event's callback.
     *
     * @return void
     */
    public function run(): void
    {
        ($this->callback)();
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    /**
     * Tests a single cron field (wildcard, step, list, range, or literal)
     * against an integer value.
     *
     * @param  string $field Cron field string, e.g. '*', '*/5', '1,15', '1-5'.
     * @param  int    $value Current calendar value to test.
     *
     * @return bool
     */
    private function matches(string $field, int $value): bool
    {
        if ($field === '*') {
            return true;
        }

        if (str_contains($field, '/')) {
            [$range, $step] = explode('/', $field, 2);
            $step  = (int) $step;
            $start = $range === '*' ? 0 : (int) $range;

            return $value >= $start && ($value - $start) % $step === 0;
        }

        if (str_contains($field, ',')) {
            return in_array($value, array_map('intval', explode(',', $field)), true);
        }

        if (str_contains($field, '-')) {
            [$from, $to] = explode('-', $field, 2);

            return $value >= (int) $from && $value <= (int) $to;
        }

        return (int) $field === $value;
    }
}
