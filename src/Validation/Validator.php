<?php

declare(strict_types=1);

namespace Zen\Validation;

/**
 * Validates an associative data array against a set of rules, accumulating
 * errors and throwing a ValidationException on failure.
 */
class Validator
{
    /**
     * Collected validation error messages keyed by field name.
     *
     * @var array<string, string[]>
     */
    private array $errors = [];

    /**
     * Whether validate() has already been executed for this instance.
     *
     * @var bool
     */
    private bool $ran = false;

    /**
     * Stores the data, rules, and optional custom messages.
     *
     * @param  array<string, mixed>  $data     Input data to validate.
     * @param  array<string, mixed>  $rules    Rule definitions keyed by field.
     * @param  array<string, string> $messages Custom error message overrides.
     *
     * @return void
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
    )
    {
    }

    /**
     * Creates and returns a new Validator instance.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string> $messages
     *
     * @return static
     */
    public static function make(array $data, array $rules, array $messages = []): static
    {
        return new static($data, $rules, $messages);
    }

    /**
     * Runs validation and returns true when there are no errors.
     *
     * @return bool
     */
    public function passes(): bool
    {
        $this->run();

        return $this->errors === [];
    }

    /**
     * Returns true when at least one field has failed validation.
     *
     * @return bool
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Runs validation and returns all accumulated error messages.
     *
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        $this->run();

        return $this->errors;
    }

    /**
     * Runs validation and returns only the keys defined in the rules set.
     * Throws a ValidationException when validation fails.
     *
     * @throws ValidationException
     *
     * @return array<string, mixed> Safe data subset.
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }

        return $this->safe();
    }

    /**
     * Returns a copy of the input data restricted to the keys present in
     * the rules, without triggering validation.
     *
     * @return array<string, mixed>
     */
    public function safe(): array
    {
        return array_intersect_key($this->data, $this->rules);
    }

    /**
     * Iterates over every rule set and runs each applicable check, populating
     * the errors array.  Idempotent — runs only once per instance.
     *
     * @return void
     */
    private function run(): void
    {
        if ($this->ran) {
            return;
        }

        $this->ran    = true;
        $this->errors = [];

        foreach ($this->rules as $field => $ruleSet) {
            $rules    = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value    = $this->getValue($field);
            $present  = $this->isPresent($field);
            $nullable = in_array('nullable', $rules, true);

            if (in_array('required', $rules, true) && (!$present || $this->isEmpty($value))) {
                $this->addError($field, 'required', []);
                continue;
            }

            if (!$present || ($nullable && $this->isEmpty($value))) {
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }

                [$name, $params] = $this->splitRule($rule);
                $method          = 'check' . str_replace('_', '', ucwords($name, '_'));

                if (!method_exists($this, $method)) {
                    continue;
                }

                if (!$this->$method($field, $value, $params)) {
                    $this->addError($field, $name, $params);
                }
            }
        }
    }

    /**
     * Retrieves a value from the data array using dot-notation for nested
     * keys.
     *
     * @param  string $field Dot-delimited field path.
     *
     * @return mixed Null when the path does not exist.
     */
    private function getValue(string $field): mixed
    {
        $data = $this->data;

        foreach (explode('.', $field) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }

            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * Returns true when the dot-notation field path exists in the data array,
     * even when the value is null.
     *
     * @param  string $field
     *
     * @return bool
     */
    private function isPresent(string $field): bool
    {
        $data = $this->data;

        foreach (explode('.', $field) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return false;
            }

            $data = $data[$segment];
        }

        return true;
    }

    /**
     * Returns true when the value is considered empty (null, empty string, or
     * empty array).
     *
     * @param  mixed $value
     *
     * @return bool
     */
    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * Parses a rule string into its name and optional parameters, treating
     * 'regex' specially to avoid splitting on colons inside the pattern.
     *
     * @param  string $rule Rule string, e.g. 'min:3' or 'regex:/^[a-z]+$/'.
     *
     * @return array{0: string, 1: string[]}
     */
    private function splitRule(string $rule): array
    {
        if (!str_contains($rule, ':')) {
            return [$rule, []];
        }

        [$name, $rest] = explode(':', $rule, 2);

        $params = strtolower($name) === 'regex' ? [$rest] : explode(',', $rest);

        return [$name, $params];
    }

    /**
     * Resolves the error message for a rule and appends it to the error list.
     *
     * @param  string   $field
     * @param  string   $rule
     * @param  string[] $params
     *
     * @return void
     */
    private function addError(string $field, string $rule, array $params): void
    {
        $message = $this->resolveMessage($field, $rule, $params);

        $this->errors[$field][] = str_replace(':field', $field, $message);
    }

    /**
     * Looks up a custom message for the field.rule combination or the rule
     * alone, falling back to the built-in default message.
     *
     * @param  string   $field
     * @param  string   $rule
     * @param  string[] $params
     *
     * @return string
     */
    private function resolveMessage(string $field, string $rule, array $params): string
    {
        foreach (["{$field}.{$rule}", $rule] as $key) {
            if (isset($this->messages[$key])) {
                return $this->messages[$key];
            }
        }

        return $this->defaultMessage($rule, $params);
    }

    /**
     * Returns the built-in English message template for the given rule.
     *
     * @param  string   $rule
     * @param  string[] $params
     *
     * @return string
     */
    private function defaultMessage(string $rule, array $params): string
    {
        return match ($rule) {
            'required'  => 'The :field field is required.',
            'string'    => 'The :field field must be a string.',
            'integer'   => 'The :field field must be an integer.',
            'numeric'   => 'The :field field must be numeric.',
            'boolean'   => 'The :field field must be true or false.',
            'array'     => 'The :field field must be an array.',
            'email'     => 'The :field field must be a valid email address.',
            'url'       => 'The :field field must be a valid URL.',
            'confirmed' => 'The :field field confirmation does not match.',
            'min'       => "The :field field must be at least {$params[0]}.",
            'max'       => "The :field field may not be greater than {$params[0]}.",
            'between'   => "The :field field must be between {$params[0]} and {$params[1]}.",
            'size'      => "The :field field must be exactly {$params[0]}.",
            'in'        => 'The :field field must be one of: ' . implode(', ', $params) . '.',
            'not_in'    => 'The :field field must not be one of: ' . implode(', ', $params) . '.',
            'regex'     => 'The :field field format is invalid.',
            'same'      => "The :field field must match {$params[0]}.",
            'different' => "The :field field must differ from {$params[0]}.",
            default     => 'The :field field is invalid.',
        };
    }

    // ─── Rule checks ──────────────────────────────────────────────────────────

    /**
     * Checks that the value is a string.
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params
     *
     * @return bool
     */
    private function checkString(string $field, mixed $value, array $params): bool
    {
        return is_string($value);
    }

    /**
     * Checks that the value is an integer or a numeric integer string.
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params
     *
     * @return bool
     */
    private function checkInteger(string $field, mixed $value, array $params): bool
    {
        return is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1);
    }

    /**
     * Checks that the value is numeric.
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params
     *
     * @return bool
     */
    private function checkNumeric(string $field, mixed $value, array $params): bool
    {
        return is_numeric($value);
    }

    /**
     * Checks that the value is a boolean-like value.
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params
     *
     * @return bool
     */
    private function checkBoolean(string $field, mixed $value, array $params): bool
    {
        return in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true);
    }

    /**
     * Checks that the value is an array.
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params
     *
     * @return bool
     */
    private function checkArray(string $field, mixed $value, array $params): bool
    {
        return is_array($value);
    }

    /**
     * Checks that the value is a valid email address.
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params
     *
     * @return bool
     */
    private function checkEmail(string $field, mixed $value, array $params): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Checks that the value is a valid URL.
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params
     *
     * @return bool
     */
    private function checkUrl(string $field, mixed $value, array $params): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Checks that the value is at least the given minimum (numeric value,
     * string length, or array count).
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params First element is the minimum.
     *
     * @return bool
     */
    private function checkMin(string $field, mixed $value, array $params): bool
    {
        $min = (float) $params[0];

        if (is_numeric($value)) {
            return (float) $value >= $min;
        }

        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }

        return is_array($value) && count($value) >= $min;
    }

    /**
     * Checks that the value does not exceed the given maximum (numeric value,
     * string length, or array count).
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params First element is the maximum.
     *
     * @return bool
     */
    private function checkMax(string $field, mixed $value, array $params): bool
    {
        $max = (float) $params[0];

        if (is_numeric($value)) {
            return (float) $value <= $max;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }

        return is_array($value) && count($value) <= $max;
    }

    /**
     * Checks that the value falls within the given range (inclusive), applied
     * to numeric value, string length, or array count.
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params First two elements are min and max.
     *
     * @return bool
     */
    private function checkBetween(string $field, mixed $value, array $params): bool
    {
        $min = (float) $params[0];
        $max = (float) $params[1];

        if (is_numeric($value)) {
            $v = (float) $value;

            return $v >= $min && $v <= $max;
        }

        if (is_string($value)) {
            $len = mb_strlen($value);

            return $len >= $min && $len <= $max;
        }

        if (is_array($value)) {
            $count = count($value);

            return $count >= $min && $count <= $max;
        }

        return false;
    }

    /**
     * Checks that the value equals exactly the given size (numeric value,
     * string length, or array count).
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params First element is the required size.
     *
     * @return bool
     */
    private function checkSize(string $field, mixed $value, array $params): bool
    {
        $size = (float) $params[0];

        if (is_numeric($value)) {
            return (float) $value === $size;
        }

        if (is_string($value)) {
            return mb_strlen($value) === (int) $size;
        }

        return is_array($value) && count($value) === (int) $size;
    }

    /**
     * Checks that the string value is in the allowed list.
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params Allowed values.
     *
     * @return bool
     */
    private function checkIn(string $field, mixed $value, array $params): bool
    {
        return in_array((string) $value, $params, true);
    }

    /**
     * Checks that the string value is not in the disallowed list.
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params Disallowed values.
     *
     * @return bool
     */
    private function checkNotIn(string $field, mixed $value, array $params): bool
    {
        return !in_array((string) $value, $params, true);
    }

    /**
     * Checks that the string value matches the given regular expression.
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params First element is the regex pattern.
     *
     * @return bool
     */
    private function checkRegex(string $field, mixed $value, array $params): bool
    {
        return is_string($value) && preg_match($params[0], $value) === 1;
    }

    /**
     * Checks that the value equals the corresponding _confirmation field.
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params
     *
     * @return bool
     */
    private function checkConfirmed(string $field, mixed $value, array $params): bool
    {
        return $value === $this->getValue($field . '_confirmation');
    }

    /**
     * Checks that the value equals the value of another field.
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params First element is the other field name.
     *
     * @return bool
     */
    private function checkSame(string $field, mixed $value, array $params): bool
    {
        return $value === $this->getValue($params[0]);
    }

    /**
     * Checks that the value differs from the value of another field.
     *
     * @param  string   $field
     * @param  mixed    $value
     * @param  string[] $params First element is the other field name.
     *
     * @return bool
     */
    private function checkDifferent(string $field, mixed $value, array $params): bool
    {
        return $value !== $this->getValue($params[0]);
    }
}
