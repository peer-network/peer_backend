<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MigrationTest extends AbstractMigration
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
     public function up(): void
    {
        // 1. Rename column
        $this->execute("ALTER TABLE posts RENAME COLUMN contenttype TO contenttype_tmp");

        // 2. Add new column with array type
        $this->execute("ALTER TABLE posts ADD COLUMN contenttype text[]");

        // 3. Convert existing values to single-item arrays
        $this->execute("UPDATE posts SET names = ARRAY[names_tmp]");

        // 4. Drop old column
        $this->execute("ALTER TABLE your_table DROP COLUMN names_tmp");
    }

    public function down(): void
    {
        // 1. Add back old column
        $this->execute("ALTER TABLE your_table ADD COLUMN name text");

        // 2. Restore first array element as original string
        $this->execute("UPDATE your_table SET name = names[1]");

        // 3. Drop array column
        $this->execute("ALTER TABLE your_table DROP COLUMN names");
    }
}
