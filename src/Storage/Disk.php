<?php

declare(strict_types=1);

namespace Zen\Storage;

use DirectoryIterator;
use FilesystemIterator;

/**
 * Local filesystem disk abstraction that resolves all paths relative to a
 * configured root directory.
 */
class Disk
{
    /**
     * Initialises the disk, ensuring the root directory exists.
     *
     * @param  string $root    Absolute path to the disk's root directory.
     * @param  string $baseUrl Base URL used to generate public URLs for files.
     *
     * @return void
     */
    public function __construct(
        private readonly string $root,
        private readonly string $baseUrl = '',
    ) {
        $this->ensureDirectory($this->root);
    }

    /**
     * Writes content to a file, creating parent directories as needed.
     *
     * @param  string $path     Path relative to the disk root.
     * @param  string $contents File content to write.
     *
     * @return bool True on success, false on failure.
     */
    public function put(string $path, string $contents): bool
    {
        $full = $this->fullPath($path);

        $this->ensureDirectory(dirname($full));

        return file_put_contents($full, $contents) !== false;
    }

    /**
     * Returns the contents of a file, or false when it does not exist.
     *
     * @param  string $path Path relative to the disk root.
     *
     * @return string|false
     */
    public function get(string $path): string|false
    {
        return file_get_contents($this->fullPath($path));
    }

    /**
     * Returns true when the file or directory exists on disk.
     *
     * @param  string $path Path relative to the disk root.
     *
     * @return bool
     */
    public function exists(string $path): bool
    {
        return file_exists($this->fullPath($path));
    }

    /**
     * Deletes a file and returns true on success, false when the file does
     * not exist.
     *
     * @param  string $path Path relative to the disk root.
     *
     * @return bool
     */
    public function delete(string $path): bool
    {
        $full = $this->fullPath($path);

        return file_exists($full) && unlink($full);
    }

    /**
     * Moves a file from one disk-relative path to another, creating the
     * destination directory as needed.
     *
     * @param  string $from Source path relative to the disk root.
     * @param  string $to   Destination path relative to the disk root.
     *
     * @return bool
     */
    public function move(string $from, string $to): bool
    {
        $dest = $this->fullPath($to);

        $this->ensureDirectory(dirname($dest));

        return rename($this->fullPath($from), $dest);
    }

    /**
     * Copies a file from one disk-relative path to another.
     *
     * @param  string $from Source path relative to the disk root.
     * @param  string $to   Destination path relative to the disk root.
     *
     * @return bool
     */
    public function copy(string $from, string $to): bool
    {
        $dest = $this->fullPath($to);

        $this->ensureDirectory(dirname($dest));

        return copy($this->fullPath($from), $dest);
    }

    /**
     * Returns the size of a file in bytes.
     *
     * @param  string $path Path relative to the disk root.
     *
     * @return int
     */
    public function size(string $path): int
    {
        return (int) filesize($this->fullPath($path));
    }

    /**
     * Returns the public URL for the given disk-relative path.
     *
     * @param  string $path Path relative to the disk root.
     *
     * @return string
     */
    public function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Returns the absolute filesystem path for the given disk-relative path.
     *
     * @param  string $path Path relative to the disk root.
     *
     * @return string
     */
    public function path(string $path): string
    {
        return $this->fullPath($path);
    }

    /**
     * Returns a sorted list of filenames in the given disk-relative directory.
     *
     * @param  string $directory Path relative to the disk root; empty for root.
     *
     * @return string[]
     */
    public function files(string $directory = ''): array
    {
        $dir = $this->fullPath($directory);

        if (!is_dir($dir)) {
            return [];
        }

        $files = [];

        foreach (new DirectoryIterator($dir) as $item) {
            if ($item->isFile()) {
                $files[] = $item->getFilename();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Returns a sorted list of subdirectory names in the given disk-relative
     * directory.
     *
     * @param  string $directory Path relative to the disk root; empty for root.
     *
     * @return string[]
     */
    public function directories(string $directory = ''): array
    {
        $dir = $this->fullPath($directory);

        if (!is_dir($dir)) {
            return [];
        }

        $dirs = [];

        foreach (new DirectoryIterator($dir) as $item) {
            if ($item->isDir() && !$item->isDot()) {
                $dirs[] = $item->getFilename();
            }
        }

        sort($dirs);

        return $dirs;
    }

    /**
     * Creates a directory at the given disk-relative path.
     *
     * @param  string $path
     *
     * @return bool
     */
    public function makeDirectory(string $path): bool
    {
        return $this->ensureDirectory($this->fullPath($path));
    }

    /**
     * Recursively deletes a directory and all of its contents.
     *
     * @param  string $path Path relative to the disk root.
     *
     * @return bool True on success, false when the directory does not exist.
     */
    public function deleteDirectory(string $path): bool
    {
        $full = $this->fullPath($path);

        if (!is_dir($full)) {
            return false;
        }

        $this->deleteRecursive($full);

        return true;
    }

    /**
     * Moves an uploaded file ($_FILES entry) to the given disk-relative
     * directory, prepending a unique ID to avoid collisions.
     *
     * @param  string               $directory Destination directory relative
     *                                         to the disk root.
     * @param  array<string, mixed> $file      A single $_FILES entry.
     *
     * @return string|false The stored disk-relative path, or false on error.
     */
    public function putUpload(string $directory, array $file): string|false
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return false;
        }

        $filename = uniqid('', true) . '_' . basename($file['name']);
        $path     = rtrim($directory, '/') . '/' . $filename;
        $dest     = $this->fullPath($path);

        $this->ensureDirectory(dirname($dest));

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return false;
        }

        return $path;
    }

    /**
     * Resolves a disk-relative path to an absolute filesystem path.
     *
     * @param  string $path
     *
     * @return string
     */
    private function fullPath(string $path): string
    {
        return rtrim($this->root, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    /**
     * Creates a directory recursively if it does not already exist.
     *
     * @param  string $path Absolute filesystem path.
     *
     * @return bool True on success or when the directory already exists.
     */
    private function ensureDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return mkdir($path, 0755, recursive: true);
        }

        return true;
    }

    /**
     * Recursively deletes all files and subdirectories inside the given
     * absolute directory path, then removes the directory itself.
     *
     * @param  string $dir Absolute path to the directory.
     *
     * @return void
     */
    private function deleteRecursive(string $dir): void
    {
        foreach (new FilesystemIterator($dir) as $item) {
            if ($item->isDir()) {
                $this->deleteRecursive($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
