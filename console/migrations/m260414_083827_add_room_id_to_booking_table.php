<?php

use yii\db\Migration;

class m260414_083827_add_room_id_to_booking_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('booking', 'room_id', $this->integer()->notNull()->defaultValue(1));
        // Erweiterung der Tabelle für mehrere Räume
        // $this->addForeignKey('fk_booking_room_id', 'booking', 'room_id', 'room', 'id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m260414_083827_add_room_id_to_booking_table cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m260414_083827_add_room_id_to_booking_table cannot be reverted.\n";

        return false;
    }
    */
}
