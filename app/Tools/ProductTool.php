<?php

namespace App\Tools;

use App\Models\SalesOrder;
use App\Tools\Contracts\ToolInterface;
use Illuminate\Support\Facades\Validator;

class ProductTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_best_selling_product';
    }

    public function description(): string
    {
        return 'Mengambil produk paling laku berdasarkan total jumlah terjual untuk bulan tertentu (opsional). Argumen month berformat YYYY-MM.';
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
                    'description' => 'Bulan dalam format YYYY-MM (opsional).',
                ],
            ],
            'required' => [],
        ];
    }

    public function permission(): ?string
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments): array
    {
        $validated = Validator::make($arguments, [
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ], ['month.regex' => 'Bulan harus berformat YYYY-MM.'])->validate();

        $month = $validated['month'] ?? null;

        $query = SalesOrder::query()
            ->selectRaw('product_name, SUM(quantity) as quantity_sold, SUM(amount) as total_revenue')
            ->whereNotNull('product_name');

        if ($month !== null) {
            $query->where('order_date', 'like', "{$month}%");
        }

        $top = $query->groupBy('product_name')
            ->orderByDesc('quantity_sold')
            ->first();

        return [
            'month' => $month,
            'product' => $top?->product_name,
            'quantity_sold' => (int) ($top?->quantity_sold ?? 0),
            'total_revenue' => (int) ($top?->total_revenue ?? 0),
        ];
    }
}
