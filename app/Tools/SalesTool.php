<?php

namespace App\Tools;

use App\Models\SalesOrder;
use App\Tools\Contracts\ToolInterface;
use Illuminate\Support\Facades\Validator;

class SalesTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_sales_summary';
    }

    public function description(): string
    {
        return 'Mengambil ringkasan total penjualan untuk bulan tertentu. Argumen month berformat YYYY-MM, contoh: 2026-09.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'month' => [
                    'type' => 'string',
                    'description' => 'Bulan dalam format YYYY-MM.',
                ],
            ],
            'required' => ['month'],
        ];
    }

    public function permission(): ?string
    {
        return 'sales.view';
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments): array
    {
        $validated = Validator::make($arguments, [
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ], ['month.regex' => 'Bulan harus berformat YYYY-MM.'])->validate();

        $month = $validated['month'];

        return [
            'month' => $month,
            'total_sales' => (int) SalesOrder::where('order_date', 'like', "{$month}%")->sum('amount'),
            'order_count' => (int) SalesOrder::where('order_date', 'like', "{$month}%")->count(),
        ];
    }
}
