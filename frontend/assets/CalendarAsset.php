<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class CalendarAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';

    public $css = [
        'css/fullcalendar.min.css',
        'css/booking.css',
    ];

    public $js = [
        'js/vue.global.prod.js',
        'js/index.global.min.js',
    ];
}