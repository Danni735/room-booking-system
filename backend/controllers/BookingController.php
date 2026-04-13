<?php

namespace backend\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\web\Response;
use common\models\Booking;

class BookingController extends ActiveController
{
    public $modelClass = 'common\models\Booking';

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // JSON Response
        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];

        // CORS erlauben (Frontend → Backend)
        $behaviors['corsFilter'] = [
            'class' => \yii\filters\Cors::class,
            'cors' => [
                'Origin' => ['http://localhost:8080'],
                'Access-Control-Request-Method' => ['GET', 'POST', 'DELETE', 'OPTIONS'],
                'Access-Control-Allow-Credentials' => false,
            ],
        ];

        $behaviors['verbs'] = [
            'class' => VerbFilter::class,
            'actions' => [
                'index'  => ['GET'],
                'create' => ['POST'],
                'cancel' => ['DELETE'],
            ],
        ];

        return $behaviors;
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index']);
        unset($actions['create']);
        return $actions;
    }

    // GET /api/bookings → alle Buchungen als FullCalendar-Events
    public function actionIndex()
    {
        $bookings = Booking::find()->all();

        // FullCalendar braucht title um den Text im Event anzuzeigen -> Frontend: bookedBy: ev.title || ev.name || 'Belegt'
        return array_map(function ($b) {
            return [
                'id'    => $b->id,
                'title' => $b->name,
                'start' => str_replace(' ', 'T', $b->start_time),
                'end'   => str_replace(' ', 'T', $b->end_time),
                'annotation' => $b->annotation,
            ];
        }, $bookings);
    }

    // POST /api/bookings → neue Buchung speichern
    public function actionCreate()
    {
        Yii::$app->request->parsers = [
            'application/json' => 'yii\web\JsonParser',
        ];

        $data = Yii::$app->request->getBodyParams();

        $booking = new Booking();
        $booking->name       = $data['name']       ?? '';
        $booking->start_time = $data['start_time']  ?? '';
        $booking->end_time   = $data['end_time']    ?? '';
        $booking->annotation = $data['annotation'] ?? null;

        // Rückgabe
        if ($booking->save()) {
            Yii::$app->response->statusCode = 201;
            return [
                'id'           => $booking->id,
                'name'        => $booking->name,
                'start'        => str_replace(' ', 'T', $booking->start_time),
                'end'          => str_replace(' ', 'T', $booking->end_time),
                'cancel_token' => $booking->cancel_token,
                'annotation' => $booking->annotation,
            ];
        }

        Yii::$app->response->statusCode = 422;
        return ['errors' => $booking->errors];
    }

    // DELETE /bookings/cancel?token=xxx
    public function actionCancel()
    {
        Yii::$app->request->parsers = [
            'application/json' => 'yii\web\JsonParser',
        ];

        $token = Yii::$app->request->get('token');

        if (!$token) {
            Yii::$app->response->statusCode = 400;
            return ['error' => 'Kein Token angegeben.'];
        }

        $booking = Booking::findOne(['cancel_token' => $token]);

        if (!$booking) {
            Yii::$app->response->statusCode = 404;
            return ['error' => 'Buchung nicht gefunden.'];
        }

        // Keine Stornierung in der Vergangenheit
        if (strtotime($booking->start_time) < time()) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'Vergangene Buchungen können nicht storniert werden.'];
        }

        $booking->delete();

        return ['message' => 'Buchung erfolgreich storniert.'];
    }
}