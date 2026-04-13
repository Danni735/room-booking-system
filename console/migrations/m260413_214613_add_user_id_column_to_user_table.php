<?php

use yii\db\Migration;

/**
 * Handles adding columns to table `{{%user}}`.
 */
class m260413_214613_add_user_id_column_to_user_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user', 'student_id', $this->string(20)->null()->defaultValue(null));
        $this->addColumn('user', 'personal_id', $this->string(20)->null()->defaultValue(null));

        $this->createIndex('idx_user_student_id', 'user', 'student_id', true);
        $this->createIndex('idx_user_personal_id', 'user', 'personal_id', true);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx_user_student_id', 'user');
        $this->dropIndex('idx_user_personal_id', 'user');
        $this->dropColumn('user', 'student_id');
        $this->dropColumn('user', 'personal_id');
    }
}
