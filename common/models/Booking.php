<?php

namespace common\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class Booking extends ActiveRecord
{
    public static function tableName()
    {
        return 'booking';
    }

    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules()
    {
        return [
            [['name', 'start_time', 'end_time'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['start_time', 'end_time'], 'date', 'format' => 'php:Y-m-d H:i:s'],
            ['end_time', 'validateTimeRange'],
            ['start_time', 'validateNoOverlap'],
            ['start_time', 'validateNotInPast'],
            [['annotation'], 'string', 'max' => 255],
            // Dauer min 1h, max. 6h
            ['end_time', 'validateDuration'],
            ['start_time', 'validateBookingDate'],
            [['user_id'], 'integer'],
        ];
    }

    public function beforeSave($insert)
    {
        if ($insert && empty($this->cancel_token)) {
            $this->cancel_token = bin2hex(random_bytes(32));
        }
        return parent::beforeSave($insert);
    }

    // Endzeit muss nach Startzeit liegen
    public function validateTimeRange($attribute, $params)
    {
        if ($this->start_time && $this->end_time) {
            if ($this->end_time <= $this->start_time) {
                $this->addError($attribute, 'Endzeit muss nach der Startzeit liegen.');
            }
        }
    }

    // Keine Buchungen in der Vergangenheit
    public function validateNotInPast($attribute, $params)
    {
        if ($this->start_time) {
            if (strtotime($this->start_time) < time()) {
                $this->addError($attribute, 'Buchungen in der Vergangenheit sind nicht möglich.');
            }
        }
    }

    // Doppelbuchungs-Prüfung
    public function validateNoOverlap($attribute, $params)
    {
        if ($this->start_time && $this->end_time) {
            $overlap = self::find()
                ->where(['<', 'start_time', $this->end_time])
                ->andWhere(['>', 'end_time', $this->start_time])
                ->andWhere(['!=', 'id', $this->id ?? 0])
                ->exists();

            if ($overlap) {
                $this->addError($attribute, 'Dieser Zeitraum ist bereits gebucht.');
            }
        }
    }

    // Buchungslänge von mind. 1h bis 6h pro Buchung
    public function validateDuration($attribute)
    {
        $start = strtotime($this->start_time);
        $end   = strtotime($this->end_time);
        $diffMin = ($end - $start) / 60;

        if ($diffMin < 60) {
            $this->addError($attribute, 'Die Buchungsdauer muss mindestens 1 Stunde betragen.');
        }
        if ($diffMin > 360) {
            $this->addError($attribute, 'Die Buchungsdauer darf maximal 6 Stunden betragen.');
        }
    }

    // Räume sind max. 7 Tage im Vorraus buchbar
    public function validateBookingDate($attribute)
    {
        $start   = strtotime($this->start_time);
        $maxDate = strtotime('+7 days', strtotime('today'));

        if ($start >= $maxDate) {
            $this->addError($attribute, 'Buchungen sind nur bis maximal 7 Tage im Voraus möglich.');
        }
    }
}