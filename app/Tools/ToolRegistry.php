<?php

namespace App\Tools;

use App\Tools\Contracts\ToolInterface;
use RuntimeException;

class ToolRegistry
{
    /**
     * @param  array<int, ToolInterface>  $tools
     */
    public function __construct(private readonly array $tools) {}

    /**
     * @return array<string, ToolInterface>
     */
    public function all(): array
    {
        $indexed = [];

        foreach ($this->tools as $tool) {
            $indexed[$tool->name()] = $tool;
        }

        return $indexed;
    }

    public function find(string $name): ?ToolInterface
    {
        return $this->all()[$name] ?? null;
    }

    public function findOrFail(string $name): ToolInterface
    {
        $tool = $this->find($name);

        if ($tool === null) {
            throw new RuntimeException("Tool '{$name}' tidak terdaftar.");
        }

        return $tool;
    }

    /**
     * Tools formatted for the Ollama function-calling API.
     *
     * @return array<int, array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>
     */
    public function schemas(): array
    {
        return array_map(
            fn (ToolInterface $tool): array => [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => $tool->schema(),
                ],
            ],
            array_values($this->all())
        );
    }
}
