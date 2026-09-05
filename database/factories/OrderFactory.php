<?php

namespace Database\Factories;

use App\Models\ListCountry;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * customer_orders carries 30 NOT NULL columns with no defaults — faithful to
 * the source schema, which never used defaults. Every one is filled here so
 * tests can create an order without restating the whole table.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Order deliberately keeps status, money and courier columns out of
     * $fillable, so a factory has to write them the same way the application
     * does — explicitly, rather than through mass assignment.
     */
    public function newModel(array $attributes = []): Order
    {
        return (new Order)->forceFill($attributes);
    }

    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 4);
        $unit = $this->faker->randomFloat(2, 15, 250);
        $total = round($qty * $unit, 2);
        $postage = $this->faker->randomFloat(2, 5, 20);

        return [
            'session_id' => $this->faker->uuid(),
            'order_to' => 1,
            'product_var_id' => (string) $this->faker->numberBetween(1, 50),
            'total_qty' => $qty,
            'total_price' => $total,
            'postage_cost' => $postage,
            'currency_sign' => 'MYR',
            'country_id' => ListCountry::MALAYSIA_ID,
            'country' => 'Malaysia',
            'state' => $this->faker->randomElement(['Selangor', 'Johor', 'Pulau Pinang', 'Sabah']),
            'city' => $this->faker->city(),
            'postcode' => (string) $this->faker->numberBetween(10000, 98000),
            'address_1' => $this->faker->streetAddress(),
            'address_2' => '',
            'customer_name' => $this->faker->firstName(),
            'customer_name_last' => $this->faker->lastName(),
            'customer_phone' => '+60'.$this->faker->numerify('#########'),
            'customer_email' => $this->faker->safeEmail(),
            'payment_channel' => $this->faker->randomElement(['senangpay', 'bayarcash', 'cod']),
            'payment_code' => $this->faker->bothify('??######'),
            'payment_url' => '',
            'ship_channel' => 'courier',
            'courier_service' => $this->faker->randomElement(['J&T Express', 'DHL eCommerce', 'NinjaVan']),
            'awb_number' => '',
            'tracking_url' => '',
            'remark_comment' => '',
            'tracking_milestone' => '',
            'to_myr_rate' => 1.00,
            'myr_value_include_postage' => round($total + $postage, 2),
            'myr_value_without_postage' => $total,
            'status' => Order::STATUS_NEW,
            'printed_awb' => 0,
        ];
    }

    public function status(int $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function withAwb(string $awb = '631838533514'): static
    {
        return $this->state(fn () => [
            'awb_number' => $awb,
            'tracking_url' => 'https://www.jtexpress.my/tracking?awb='.$awb,
        ]);
    }
}
