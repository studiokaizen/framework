<?php

declare(strict_types=1);

namespace Zen\View;

use RuntimeException;

/**
 * PHP template engine with layout inheritance, named sections, asset stacks,
 * shared data, and HTML escaping helpers.
 */
class Engine
{
    /** @var string */
    private string $viewsPath;

    /** @var string */
    private string $extension;

    /** @var array<string, mixed> */
    private array $shared = [];

    /** @var string|null */
    private ?string $layout = null;

    /** @var array<string, string> */
    private array $sections = [];

    /** @var string|null */
    private ?string $currentSection = null;

    /** @var array<string, list<string>> */
    private array $stacks = [];

    /** @var string|null */
    private ?string $currentStack = null;

    /** @var bool */
    private bool $stackPrepend = false;

    public function __construct(string $viewsPath, string $extension = 'php')
    {
        $this->viewsPath = rtrim($viewsPath, '/\\');
        $this->extension = ltrim($extension, '.');
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function render(string $template, array $data = []): string
    {
        $this->layout         = null;
        $this->sections       = [];
        $this->currentSection = null;
        $this->stacks         = [];
        $this->currentStack   = null;
        $this->stackPrepend   = false;

        $data    = array_merge($this->shared, $data);
        $content = $this->capture($template, $data);

        if ($this->layout === null) {
            return $content;
        }

        if (!isset($this->sections['content']) && $content !== '') {
            $this->sections['content'] = $content;
        }

        return $this->capture($this->layout, $data);
    }

    // -------------------------------------------------------------------------
    // Layout
    // -------------------------------------------------------------------------

    /**
     * Declares the parent layout that wraps this view's output.
     */
    public function extend(string $template): void
    {
        $this->layout = $template;
    }

    // -------------------------------------------------------------------------
    // Sections
    // -------------------------------------------------------------------------

    /**
     * Inline section setter — stores $value under $name with no buffering.
     * When $value is omitted, returns the stored section content (getter).
     */
    public function section(string $name, ?string $value = null): string
    {
        if ($value !== null) {
            $this->sections[$name] = $value;
            return '';
        }

        return $this->sections[$name] ?? '';
    }

    /**
     * Outputs a section in a layout, with an optional default fallback.
     */
    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Begins buffering output into a named section.
     */
    public function startSection(string $name): void
    {
        if ($this->currentSection !== null) {
            throw new RuntimeException(
                sprintf('Cannot nest sections. Call endSection() before starting "%s".', $name),
            );
        }

        $this->currentSection = $name;
        ob_start();
    }

    /**
     * Ends the current section buffer and stores the captured output.
     */
    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new RuntimeException('No open section. Call startSection() before endSection().');
        }

        $this->sections[$this->currentSection] = ob_get_clean() ?: '';
        $this->currentSection                  = null;
    }

    // -------------------------------------------------------------------------
    // Stacks
    // -------------------------------------------------------------------------

    /**
     * Outputs all content pushed onto a named stack.  Call from a layout.
     */
    public function stack(string $name): string
    {
        return implode(PHP_EOL, $this->stacks[$name] ?? []);
    }

    /**
     * Begins buffering output to append to a named stack.
     */
    public function push(string $name): void
    {
        $this->openStack($name, prepend: false);
    }

    /**
     * Ends the current push() buffer and appends the content to the stack.
     */
    public function endPush(): void
    {
        $this->closeStack();
    }

    /**
     * Begins buffering output to prepend to a named stack.
     */
    public function prepend(string $name): void
    {
        $this->openStack($name, prepend: true);
    }

    /**
     * Ends the current prepend() buffer and prepends the content to the stack.
     */
    public function endPrepend(): void
    {
        $this->closeStack();
    }

    // -------------------------------------------------------------------------
    // Shared data & helpers
    // -------------------------------------------------------------------------

    public function share(string $key, mixed $value): static
    {
        $this->shared[$key] = $value;
        return $this;
    }

    public function exists(string $template): bool
    {
        return file_exists($this->resolve($template));
    }

    public function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function openStack(string $name, bool $prepend): void
    {
        if ($this->currentStack !== null) {
            throw new RuntimeException(
                sprintf('Cannot nest stack captures. Call endPush() or endPrepend() before starting "%s".', $name),
            );
        }

        $this->currentStack = $name;
        $this->stackPrepend = $prepend;
        ob_start();
    }

    private function closeStack(): void
    {
        if ($this->currentStack === null) {
            throw new RuntimeException('No open stack. Call push() or prepend() first.');
        }

        $content = ob_get_clean() ?: '';

        if ($this->stackPrepend) {
            array_unshift($this->stacks[$this->currentStack] ??= [], $content);
        } else {
            $this->stacks[$this->currentStack][] = $content;
        }

        $this->currentStack = null;
        $this->stackPrepend = false;
    }

    private function capture(string $template, array $data): string
    {
        $__path = $this->resolve($template);

        if (!file_exists($__path)) {
            throw new RuntimeException(sprintf('View "%s" not found at "%s".', $template, $__path));
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $__path;

        return ob_get_clean() ?: '';
    }

    private function resolve(string $template): string
    {
        return $this->viewsPath
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $template)
            . '.' . $this->extension;
    }
}
