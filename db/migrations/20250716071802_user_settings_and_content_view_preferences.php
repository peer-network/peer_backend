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
            CREATE TABLE IF NOT EXISTS user_preferences (
                userid UUID PRIMARY KEY,
                content_filtering_severity_level SMALLINT DEFAULT NULL,
                updatedat TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
                CONSTRAINT fk_user_preferences_users FOREIGN KEY (userid) REFERENCES users(uid) ON DELETE CASCADE
            );
        ");

        $this->execute("
            ALTER TABLE user_preferences
            ADD CONSTRAINT chk_content_view_preferences_range
            CHECK (content_filtering_severity_level IS NULL OR (content_filtering_severity_level >= 0 AND content_filtering_severity_level <= 10));
        ");
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS user_preferences CASCADE;");
    }
}