<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateActionPricesTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
     public function change(): void
    {
        $this->table('action_prices', ['primary_key' => ["id"]])
            ->addColumn('post_price', 'decimal', [
                'precision' => 10,
                'scale'     => 4,
                'default'   => 0.00,
                'null'      => false
            ])
            ->addColumn('like_price', 'decimal', [
                'precision' => 10,
                'scale'     => 4,
                'default'   => 0.00,
                'null'      => false
            ])
            ->addColumn('dislike_price', 'decimal', [
                'precision' => 10,
                'scale'     => 4,
                'default'   => 0.00,
                'null'      => false
            ])
            ->addColumn('comment_price', 'decimal', [
                'precision' => 10,
                'scale'     => 4,
                'default'   => 0.00,
                'null'      => false
            ])
            ->addColumn('currency', 'string', [
                'limit'   => 10,
                'default' => 'EUR',
                'null'    => true
            ])
            ->addColumn('createdat', 'timestamp', [
                'default' => 'CURRENT_TIMESTAMP',
                'null'    => false
            ])
            ->addColumn('updatedat', 'timestamp', [
                'default' => 'CURRENT_TIMESTAMP',
                'null'    => false
            ])
            ->create();
    }
}



// 📝 Notes: - id => false avoids the default "id" column, as it’s not in your original schema. - CURRENT_TIMESTAMP as a default is supported by Phinx when you use timestamp and the string 'CURRENT_TIMESTAMP'. - 