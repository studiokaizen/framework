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
    /**
     * Absolute path to the views directory with no trailing separator.
     *
     * @var string
     */
    private string $viewsPath;

    /**
     * File extension appended when resolving template names.
     *
     * @var string
     */
    private string $extension;

    /**
     * Data shared across all rendered templates.
     *
     * @var array<string, mixed>
     */
    private array $shared = [];

    /**
     * Layout template name set by the currently rendering view, or null.
     *
     * @var string|null
     */
    private ?string $layout = null;

    /**
     * Named section content captured during template rendering.
     *
     * @var array<string, string>
     */
    private array $sections = [];

    /**
     * Name of the section currently being captured via output buffering.
     *
     * @var string|null
     */
    private ?string $currentSection = null;

    /**
     * Named stacks of content fragments (e.g. CSS or JS snippets).
     *
     * @var array<string, list<string>>
     */
    private array $stacks = [];

    /**
     * Name of the stack currently being captured, or null.
     *
     * @var string|null
     */
    private ?string $currentStack = null;

    /**
     * When true the current stack buffer will be prepended; otherwise appended.
     *
     * @var bool
     */
    private bool $stackPrepend = false;

    /**
     * Stores the views path and file extension.
     *
     * @param  string $viewsPath Absolute path to the views directory.
     * @param  string $extension File extension without the leading dot.
     *
     * @return void
     */
    public function __construct(string $viewsPath, string $extension = 'php')
    {
        $this->viewsPath = rtrim($viewsPath, '/\\');
        $this->extension = ltrim($extension, '.');
    }

    /**
     * Renders a template with the merged shared and per-call data, wrapping
     * the output in a layout template when one is declared inside the view.
     *
     * @param  string               $template Template name relative to views directory.
     * @param  array<string, mixed> $data     Variables extracted into the template scope.
     *
     * @throws RuntimeException If the template file does not exist.
     *
     * @return string Rendered HTML string.
     */
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

    /**
     * Sets the layout template to wrap the current view's output.  Called
     * from within a view file.
     *
     * @param  string $layout Layout template name relative to the views dir.
     *
     * @return void
     */
    public function layout(string $layout): void
    {
        $this->layout = $layout;
    }

    // -------------------------------------------------------------------------
    // Sections
    // -------------------------------------------------------------------------

    /**
     * Defines or retrieves a named section.
     *
     * When called with a non-null $value the section is set inline (no
     * output buffering required) and an empty string is returned so the call
     * is safe inside an echo expression.  When called without $value the
     * stored content for the section is returned (getter).
     *
     * @param  string      $name  Section name.
     * @param  string|null $value Inline content to store, or null to read.
     *
     * @return string
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
     * Returns the captured content for the named section, or the default
     * string when the section was never defined.  Intended for use in layout
     * templates as the counterpart to section() / startSection().
     *
     * @param  string $name    Section name.
     * @param  string $default Fallback string when the section is absent.
     *
     * @return string
     */
    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Begins capturing output into a named section.
     *
     * @param  string $name Section name.
     *
     * @throws RuntimeException If a section is already open.
     *
     * @return void
     */
    public function start(string $name): void
    {
        if ($this->currentSection !== null) {
            throw new RuntimeException(
                sprintf('Cannot nest sections. Call end() before starting "%s".', $name),
            );
        }

        $this->currentSection = $name;

        ob_start();
    }

    /**
     * Alias for start() — begins capturing output into a named section.
     *
     * @param  string $name Section name.
     *
     * @return void
     */
    public function startSection(string $name): void
    {
        $this->start($name);
    }

    /**
     * Closes the currently open section and stores the captured output.
     *
     * @throws RuntimeException If no section is currently open.
     *
     * @return void
     */
    public function end(): void
    {
        if ($this->currentSection === null) {
            throw new RuntimeException('No open section. Call start() before end().');
        }

        $this->sections[$this->currentSection] = ob_get_clean() ?: '';
        $this->currentSection                  = null;
    }

    /**
     * Alias for end() — closes the currently open section.
     *
     * @return void
     */
    public function endSection(): void
    {
        $this->end();
    }

    // -------------------------------------------------------------------------
    // Stacks
    // -------------------------------------------------------------------------

    /**
     * Begins capturing output to append to a named stack.
     *
     * @param  string $name Stack name.
     *
     * @throws RuntimeException If a stack capture is already open.
     *
     * @return void
     */
    public function append(string $name): void
    {
        if ($this->currentStack !== null) {
            throw new RuntimeException(
                sprintf('Cannot nest stack pushes. Call endStack() before starting "%s".', $name),
            );
        }

        $this->currentStack = $name;
        $this->stackPrepend = false;

        ob_start();
    }

    /**
     * Begins capturing output to prepend to a named stack.
     *
     * @param  string $name Stack name.
     *
     * @throws RuntimeException If a stack capture is already open.
     *
     * @return void
     */
    public function prepend(string $name): void
    {
        if ($this->currentStack !== null) {
            throw new RuntimeException(
                sprintf('Cannot nest stack pushes. Call endStack() before starting "%s".', $name),
            );
        }

        $this->currentStack = $name;
        $this->stackPrepend = true;

        ob_start();
    }

    /**
     * Closes the currently open stack capture and stores the buffered content.
     *
     * @throws RuntimeException If no stack capture is currently open.
     *
     * @return void
     */
    public function endStack(): void
    {
        if ($this->currentStack === null) {
            throw new RuntimeException('No open stack. Call append() or prepend() before endStack().');
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

    /**
     * Returns all content pushed onto a named stack, joined by newlines.
     * Call this from a layout template to output the accumulated fragments.
     *
     * @param  string $name Stack name.
     *
     * @return string
     */
    public function stack(string $name): string
    {
        return implode(PHP_EOL, $this->stacks[$name] ?? []);
    }

    // -------------------------------------------------------------------------
    // Shared data & helpers
    // -------------------------------------------------------------------------

    /**
     * Adds a variable to the shared data injected into every template.
     *
     * @param  string $key
     * @param  mixed  $value
     *
     * @return static
     */
    public function share(string $key, mixed $value): static
    {
        $this->shared[$key] = $value;

        return $this;
    }

    /**
     * Returns true when the resolved path for the given template name exists.
     *
     * @param  string $template
     *
     * @return bool
     */
    public function exists(string $template): bool
    {
        return file_exists($this->resolve($template));
    }

    /**
     * Escapes a string for safe HTML output.
     *
     * @param  string $value
     *
     * @return string HTML-escaped string.
     */
    public function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Includes a template file inside an output buffer and returns the
     * captured output, extracting data into the local scope.
     *
     * @param  string               $template
     * @param  array<string, mixed> $data
     *
     * @throws RuntimeException If the template file does not exist.
     *
     * @return string
     */
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

    /**
     * Resolves a template name to an absolute filesystem path by appending
     * the configured extension.
     *
     * @param  string $template
     *
     * @return string
     */
    private function resolve(string $template): string
    {
        return $this->viewsPath
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $template)
            . '.' . $this->extension;
    }
}
