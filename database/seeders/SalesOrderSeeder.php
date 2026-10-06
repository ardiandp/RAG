<?php

namespace Database\Seeders;

use App\Models\SalesOrder;
use Illuminate\Database\Seeder;

class SalesOrderSeeder extends Seeder
{
    /**
     * Seed demo sales data used by the sample tools.
     */
    public function run(): void
    {
        $orders = [
            ['SO-2026-08-001', 'Budi Santoso', 'Laptop', 2, 8_000_000, '2026-08-05'],
            ['SO-2026-08-002', 'Siti Aminah', 'Handphone', 5, 3_500_000, '2026-08-12'],
            ['SO-2026-08-003', 'Agus Wijaya', 'Laptop', 1, 8_000_000, '2026-08-20'],
            ['SO-2026-09-001', 'Dewi Lestari', 'Handphone', 10, 3_500_000, '2026-09-03'],
            ['SO-2026-09-002', 'Rahmat Hidayat', 'Tablet', 4, 4_200_000, '2026-09-10'],
            ['SO-2026-09-003', 'Siti Aminah', 'Laptop', 3, 8_000_000, '2026-09-15'],
            ['SO-2026-09-004', 'Budi Santoso', 'Handphone', 2, 3_500_000, '2026-09-22'],
            ['SO-2026-09-005', 'Agus Wijaya', 'Monitor', 6, 1_800_000, '2026-09-28'],
        ];

        foreach ($orders as [$number, $customer, $product, $quantity, $price, $date]) {
            SalesOrder::create([
                'order_number' => $number,
                'customer_name' => $customer,
                'product_name' => $product,
                'quantity' => $quantity,
                'unit_price' => $price,
                'amount' => $quantity * $price,
                'order_date' => $date,
            ]);
        }
    }
}
