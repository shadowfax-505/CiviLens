<?php

namespace App\Contracts\Search;

interface Searchable
{
    public function searchTitle(): string;

    public function searchDescription(): ?string;

    /**
     * @return array<int, string>
     */
    public function searchKeywords(): array;

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function searchRelations(): array;

    public function searchModule(): string;

    public function searchUrl(): string;

    public function searchStatus(): ?string;

    public function searchVisibility(): string;

    /**
     * @return array<string, mixed>
     */
    public function searchMetadata(): array;
}
