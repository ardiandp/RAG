<?php

namespace App\Tools\Contracts;

interface ToolInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * OpenAI-style JSON schema (object) of the accepted parameters.
     *
     * @return array<string, mixed>
     */
    public function schema(): array;

    /**
     * Validate and execute the tool.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments): array;

    public function permission(): ?string;
}
