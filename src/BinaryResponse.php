<?php

declare(strict_types=1);

namespace Omnifox;

/** A file answered by the API instead of JSON (the quote PDF). */
final class BinaryResponse
{
    public function __construct(
        /** Raw bytes. */
        public readonly string $data,
        public readonly string $contentType,
        public readonly ?string $fileName = null,
    ) {
    }

    /** Write the bytes to disk and return the number of bytes written. */
    public function saveTo(string $path): int
    {
        $written = file_put_contents($path, $this->data);
        if ($written === false) {
            throw new Exception\InvalidArgumentException("Could not write to {$path}");
        }

        return $written;
    }
}
