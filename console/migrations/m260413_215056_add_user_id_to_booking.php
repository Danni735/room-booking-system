<?php

use yii\db\Migration;

class m260413_215056_add_user_id_to_booking extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('booking', 'user_id', $this->integer()->null());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m260413_215056_add_user_id_to_booking cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m260413_215056_add_user_id_to_booking cannot be reverted.\n";

        return false;
    }
    */
}
