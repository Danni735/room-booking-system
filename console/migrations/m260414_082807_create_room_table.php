<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%room}}`.
 */
class m260414_082807_create_room_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%room}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'capacity' => $this->integer(),
            'description' => $this->string(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%room}}');
    }
}
