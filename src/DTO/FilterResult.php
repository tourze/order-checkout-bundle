<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

readonly class FilterResult
{
    /**
     * @param string[] $filteredWords
     */
    public function __construct(
        public string $originalContent,
        public string $filteredContent,
        public bool $hasFilteredContent,
        public array $filteredWords,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'originalContent' => $this->originalContent,
            'filteredContent' => $this->filteredContent,
            'hasFilteredContent' => $this->hasFilteredContent,
            'filteredWords' => $this->filteredWords,
        ];
    }
}
