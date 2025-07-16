<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserSettingsAndContentViewPreferences extends AbstractMigration
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
    public function up()
    {
        $this->execute("
            CREATE TABLE IF NOT EXISTS user_settings (
                userid UUID PRIMARY KEY,
                content_view_preferences INT DEFAULT NULL,
                CONSTRAINT fk_user_settings_users FOREIGN KEY (userid) REFERENCES users(uid) ON DELETE CASCADE
            );
        ");

        $this->execute("
            ALTER TABLE user_settings
            ADD CONSTRAINT chk_content_view_preferences_range
            CHECK (content_view_preferences IS NULL OR (content_view_preferences >= 0 AND content_view_preferences <= 10));
        ");
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS user_settings CASCADE;");
    }
}