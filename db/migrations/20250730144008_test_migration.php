<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TestMigration extends AbstractMigration
{
    public function up()
    {
        $this->execute("CREATE TABLE IF NOT EXISTS test_table();");
    }

    public function down()
    {
        // Optional: Remove the inserted rows if rolling back
        $this->execute("DROP TABLE IF EXISTS test_table;");
    }
}
