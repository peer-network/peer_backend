<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PopulateUserSettingsForExistingUsers extends AbstractMigration
{
   public function up()
    {
        $this->execute("
            INSERT INTO user_preferences (userid)
            SELECT uid
            FROM users
            WHERE uid NOT IN (
                SELECT userid FROM user_preferences
            );
        ");
    }

    public function down()
    {
        // Optional: Remove the inserted rows if rolling back
        $this->execute("
            DELETE FROM user_preferences
            WHERE userid IN (
                SELECT uid FROM users
            );
        ");
    }
}
