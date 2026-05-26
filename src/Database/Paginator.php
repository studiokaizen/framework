<?php

declare(strict_types=1);

namespace Zen\Database;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Holds a page of query results together with pagination metadata such as
 * total item count, current page, and last page number.
 */
class Paginator implements Countable, IteratorAggregate
{
    /**
     * The page number of the final page.
     *
     * @var int
     */
    private int $lastPage;

    /**
     * Creates the paginator with the current page's items and metadata.
     *
     * @param  array<int, mixed> $items
     * @param  int               $total
     * @param  int               $perPage
     * @param  int               $currentPage
     *
     * @return void
     */
    public function __construct(
        private readonly array $items,
        private readonly int   $total,
        private readonly int   $perPage,
        private readonly int   $currentPage,
    ) {
        $this->lastPage = max(1, (int) ceil($total / $perPage));
    }

    /**
     * Returns the items on the current page.
     *
     * @return array<int, mixed>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Returns the total number of items across all pages.
     *
     * @return int
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Returns the number of items shown per page.
     *
     * @return int
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Returns the current page number.
     *
     * @return int
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Returns the last page number.
     *
     * @return int
     */
    public function lastPage(): int
    {
        return $this->lastPage;
    }

    /**
     * Returns the 1-based index of the first item on the current page.
     *
     * @return int
     */
    public function from(): int
    {
        return $this->total === 0 ? 0 : ($this->currentPage - 1) * $this->perPage + 1;
    }

    /**
     * Returns the 1-based index of the last item on the current page.
     *
     * @return int
     */
    public function to(): int
    {
        return min($this->total, $this->currentPage * $this->perPage);
    }

    /**
     * Returns true when there are pages beyond the current one.
     *
     * @return bool
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * Returns true when the current page is the first page.
     *
     * @return bool
     */
    public function onFirstPage(): bool
    {
        return $this->currentPage === 1;
    }

    /**
     * Returns the next page number, or null when on the last page.
     *
     * @return int|null
     */
    public function nextPage(): ?int
    {
        return $this->hasMorePages() ? $this->currentPage + 1 : null;
    }

    /**
     * Returns the previous page number, or null when on the first page.
     *
     * @return int|null
     */
    public function previousPage(): ?int
    {
        return $this->currentPage > 1 ? $this->currentPage - 1 : null;
    }

    /**
     * Returns the pagination metadata and items as an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data'         => $this->items,
            'total'        => $this->total,
            'per_page'     => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page'    => $this->lastPage,
            'from'         => $this->from(),
            'to'           => $this->to(),
        ];
    }

    /**
     * Returns the number of items on the current page.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Returns an iterator over the items on the current page.
     *
     * @return Traversable<int, mixed>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
