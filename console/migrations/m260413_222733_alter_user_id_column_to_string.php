<?php

use yii\db\Migration;

class m260413_222733_alter_user_id_column_to_string extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->alterColumn('booking', 'user_id', $this->string(20)->null());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m260413_222733_alter_user_id_column_to_string cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m260413_222733_alter_user_id_column_to_string cannot be reverted.\n";

        return false;
    }
    */
}
