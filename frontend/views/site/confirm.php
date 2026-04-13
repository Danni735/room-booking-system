<?php

use yii\helpers\Html;

$this->title = 'Buchungsbestätigung';

$name  = Yii::$app->request->get('name',  '');
$start = Yii::$app->request->get('start', '');
$end   = Yii::$app->request->get('end',   '');
$token = Yii::$app->request->get('token', '');

$startFormatted = date('d.m.Y H:i', strtotime($start)) . ' Uhr';
$endFormatted   = date('H:i', strtotime($end)) . ' Uhr';

$cancelUrl = 'http://localhost:8081/bookings/cancel?token=' . urlencode($token);
?>

<div style="max-width:500px;margin:60px auto;text-align:center;">
    <h2>✅ Wir haben Ihre Buchung registriert!</h2>
    <div style="background:#f9f9f9;border-radius:8px;padding:24px;margin:24px 0;text-align:left;">
        <p><strong>Name:</strong> <?= Html::encode($name) ?></p>
        <p><strong>Beginn:</strong> <?= $startFormatted ?></p>
        <p><strong>Ende:</strong> <?= $endFormatted ?></p>
    </div>

    <p style="color:#666;font-size:0.9em;">
        Möchten Sie Ihre Buchung stornieren?
    </p>

    <button id="btn-storno" style="
        background:#e74c3c;
        color:white;
        border:none;
        padding:12px 28px;
        border-radius:4px;
        cursor:pointer;
        font-size:1em;
    ">
        Buchung stornieren
    </button>

    <div id="storno-msg" style="margin-top:16px;"></div>
</div>

<script>
    document.getElementById('btn-storno').addEventListener('click', function () {
        if (!confirm('Möchten Sie die Buchung wirklich stornieren?')) return;

        fetch('<?= $cancelUrl ?>', { method: 'DELETE' })
            .then(r => r.json())
            .then(data => {
                if (data.message) {
                    document.getElementById('storno-msg').innerHTML =
                        '<p style="color:green;">✅ ' + data.message + '</p>';
                    document.getElementById('btn-storno').style.display = 'none';
                } else {
                    document.getElementById('storno-msg').innerHTML =
                        '<p style="color:red;">❌ ' + (data.error || 'Fehler beim Stornieren.') + '</p>';
                }
            })
            .catch(() => {
                document.getElementById('storno-msg').innerHTML =
                    '<p style="color:red;">❌ Verbindungsfehler.</p>';
            });
    });
</script>