<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class ActionPricesSeeder extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run(): void
    {
        $data = [
            [
                'post_price'    => 0.1000,
                'like_price'    => 0.0500,
                'dislike_price' => 0.0200,
                'comment_price' => 0.1500,
                'currency'      => 'EUR',
                'createdat'     => date('Y-m-d H:i:s'),
                'updatedat'     => date('Y-m-d H:i:s'),
            ],
            // Add more rows as needed
        ];

        $this->table('action_prices')->insert($data)->saveData();
    }
}
