<?php

namespace App\Tools;

use App\Models\User;
use App\Tools\Contracts\ToolInterface;

class CustomerTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_customer_count';
    }

    public function description(): string
    {
        return 'Menghitung jumlah customer (pengguna terdaftar) pada sistem.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
            'required' => [],
        ];
    }

    public function permission(): ?string
    {
        return 'customer.view';
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments): array
    {
        return [
            'customer_count' => User::count(),
        ];
    }
}
