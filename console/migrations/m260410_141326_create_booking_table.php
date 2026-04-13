<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%booking}}`.
 */
class m260410_141326_create_booking_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('booking', [
            'id'           => $this->primaryKey(),
            'name'         => $this->string(255)->notNull(),
            'start_time'   => $this->dateTime()->notNull(),
            'end_time'     => $this->dateTime()->notNull(),
            'cancel_token' => $this->string(64)->notNull()->defaultValue(''),
            'created_by'   => $this->integer()->null(),
            'created_at'   => $this->integer()->notNull(),
            'updated_at'   => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_booking_start_end', 'booking', ['start_time', 'end_time']);
        $this->createIndex('idx_booking_cancel_token', 'booking', 'cancel_token', true);
    }

    public function safeDown()
    {
        $this->dropTable('booking');
    }
}
