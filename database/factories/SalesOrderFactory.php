<?php

namespace Database\Factories;

use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 20);

        return [
            'order_number' => fake()->unique()->numerify('SO-####'),
            'customer_name' => fake()->name(),
            'product_name' => fake()->randomElement(['Laptop', 'Handphone', 'Tablet', 'Printer', 'Monitor']),
            'quantity' => $quantity,
            'unit_price' => fake()->numberBetween(100_000, 10_000_000),
            'amount' => fn (array $attributes): int => $attributes['quantity'] * $attributes['unit_price'],
            'order_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
        ];
    }
}
