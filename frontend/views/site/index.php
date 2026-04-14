<?php

/** @var yii\web\View $this */

$this->title = 'Room Booking System - Daniela Porrello';

use frontend\assets\CalendarAsset;
use yii\helpers\Html;

CalendarAsset::register($this);

$currentUser   = Yii::$app->user->isGuest ? '' : Html::encode(Yii::$app->user->identity->username);
$currentUserId = Yii::$app->user->isGuest ? null : Yii::$app->user->id;

if (Yii::$app->user->isGuest) {
    echo '<div class="alert alert-warning" role="alert">Bitte melden Sie sich an, um eine Buchung zu tätigen.</div>';
}

// Bootstrap zuerst, danach booking.css (damit Overrides greifen)
$this->registerCssFile('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
$this->registerCssFile('@web/css/booking.css');
?>

<!-- Overlay (hinter Popup, vor Kalender) -->
<div id="popup-overlay" class="d-none"></div>

<!-- Kalender -->
<div id="calendar" class="mb-4"></div>

<!-- Popup: Sichtbarkeit nur über d-none gesteuert -->
<div id="booking-popup" class="d-none position-fixed top-50 start-50 translate-middle bg-white p-4">

    <h5 class="mb-3">Raum buchen</h5>

    <div id="popup-error" class="alert alert-danger d-none py-2" role="alert"></div>

    <!-- Name -->
    <div class="mb-3">
        <label for="input-name" class="form-label">Name <span class="text-danger">*</span></label>
        <input id="input-name" type="text" class="form-control" placeholder="Ihr Name" readonly />
    </div>

    <!-- Anmerkung -->
    <div class="mb-3">
        <label for="input-annotation" class="form-label">
            Anmerkung <small class="text-muted fw-normal">(optional)</small>
        </label>
        <input id="input-annotation" type="text" class="form-control"
               placeholder="z.B. Teammeeting, Schulung, Präsentation …" />
    </div>

    <!-- Datum -->
    <div class="mb-3">
        <label for="input-date" class="form-label">Datum</label>
        <input id="input-date" type="text" class="form-control" readonly />
    </div>

    <!-- Start- und Endzeit -->
    <div class="row g-3 mb-3">
        <div class="col">
            <label for="input-start" class="form-label">Startzeit</label>
            <select id="input-start" class="form-select"></select>
        </div>
        <div class="col">
            <label for="input-end" class="form-label">
                Endzeit <small class="text-muted fw-normal">(1–6 Std.)</small>
            </label>
            <select id="input-end" class="form-select"></select>
        </div>
    </div>

    <!-- Buttons -->
    <div class="d-flex gap-2 mt-3">
        <button id="btn-submit" class="btn btn-primary flex-fill">Buchen</button>
        <button id="btn-cancel" class="btn btn-secondary flex-fill">Abbrechen</button>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const API_URL       = 'http://localhost:8081/bookings';
        const currentUser   = '<?= $currentUser ?>';
        const currentUserId = <?= $currentUserId ?? 'null' ?>;

        const MIN_DURATION_MIN = 60;
        const MAX_DURATION_MIN = 360;
        const SLOT_MAX_MIN     = 21 * 60;

        const today   = new Date();
        today.setHours(0, 0, 0, 0);
        const maxDate = new Date(today);
        maxDate.setDate(today.getDate() + 8);

        const timeSlots = <?= json_encode(array_map(function($m) {
            return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }, range(7 * 60, 21 * 60, 60))) ?>;

        const startSelect = document.getElementById('input-start');
        const endSelect   = document.getElementById('input-end');
        const popup       = document.getElementById('booking-popup');
        const overlay     = document.getElementById('popup-overlay');
        const errBox      = document.getElementById('popup-error');

        // ── Hilfsfunktionen ──────────────────────────────────

        function toTimeStr(minutes) {
            const h = Math.floor(minutes / 60);
            const m = minutes % 60;
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }

        /*function sprintf(fmt, ...args) {
            return fmt.replace(/%02d/g, () => String(args.shift()).padStart(2, '0'));
        }*/

        function roundToSlot(time) {
            const [h, m] = time.split(':').map(Number);
            const rounded = Math.round((h * 60 + m) / 60) * 60;
            const clamped = Math.min(Math.max(rounded, 7 * 60), 21 * 60);
            return toTimeStr(clamped);
        }

        function showError(msg) {
            errBox.textContent = msg;
            errBox.classList.remove('d-none');
        }

        // ── Selects befüllen ─────────────────────────────────

        function updateStartOptions(dateStr, preferredStart) {
            const now     = new Date();
            const isToday = dateStr === now.toISOString().substring(0, 10);
            const nowMin  = now.getHours() * 60 + now.getMinutes();

            startSelect.innerHTML = '';
            timeSlots.forEach(t => {
                const [h, m] = t.split(':').map(Number);
                const tMin   = h * 60 + m;
                if (tMin + MIN_DURATION_MIN > SLOT_MAX_MIN) return;
                if (isToday && tMin <= nowMin) return;
                startSelect.innerHTML += `<option value="${t}">${t}</option>`;
            });

            if (preferredStart && [...startSelect].some(o => o.value === preferredStart)) {
                startSelect.value = preferredStart;
            } else {
                startSelect.selectedIndex = 0;
            }
        }

        function updateEndOptions(startTime, preferredEnd) {
            const [sh, sm] = startTime.split(':').map(Number);
            const startMin = sh * 60 + sm;
            const minEnd   = startMin + MIN_DURATION_MIN;
            const maxEnd   = Math.min(startMin + MAX_DURATION_MIN, SLOT_MAX_MIN);

            endSelect.innerHTML = '';
            timeSlots.forEach(t => {
                const [h, m] = t.split(':').map(Number);
                const tMin   = h * 60 + m;
                if (tMin >= minEnd && tMin <= maxEnd) {
                    endSelect.innerHTML += `<option value="${t}">${t}</option>`;
                }
            });

            if (preferredEnd) {
                const [ph, pm] = preferredEnd.split(':').map(Number);
                const prefMin  = ph * 60 + pm;
                if (prefMin >= minEnd && prefMin <= maxEnd) {
                    endSelect.value = preferredEnd;
                    return;
                }
            }
            endSelect.value = toTimeStr(minEnd);
        }

        startSelect.addEventListener('change', function () {
            updateEndOptions(this.value);
        });

        // ── Popup öffnen / schließen ─────────────────────────

        function showPopup(dateStr, startTime, endTime) {
            document.getElementById('input-date').value       = dateStr;
            document.getElementById('input-name').value       = currentUser;
            document.getElementById('input-annotation').value = '';
            errBox.classList.add('d-none');
            errBox.textContent = '';

            updateStartOptions(dateStr, startTime);
            updateEndOptions(startSelect.value, endTime);

            // Overlay einblenden
            overlay.classList.remove('d-none');
            // Popup einblenden
            popup.classList.remove('d-none');

            document.getElementById('input-annotation').focus();
        }

        function hidePopup() {
            popup.classList.add('d-none');
            overlay.classList.add('d-none');
            calendar.unselect();
        }

        document.getElementById('btn-cancel').addEventListener('click', hidePopup);
        overlay.addEventListener('click', hidePopup);

        // ── FullCalendar ─────────────────────────────────────

        const calendarEl = document.getElementById('calendar');
        const calendar = new FullCalendar.Calendar(calendarEl, {
            schedulerLicenseKey: 'CC-Attribution-NonCommercial-NoDerivatives',
            initialView: 'timeGridWeek',
            slotDuration: '01:00:00',
            slotMinTime: '07:00:00',
            slotMaxTime: '21:00:00',
            allDaySlot: false,
            locale: 'de',
            selectable: <?= Yii::$app->user->isGuest ? 'false' : 'true' ?>,
            selectMirror: true,
            selectOverlap: false,

            /*validRange: {
                start: today,
                end:   maxDate,
            },*/

            headerToolbar: {
                left:   'prev,next today',
                center: 'title',
                right:  'timeGridWeek,timeGridDay'
            },

            selectAllow: function(selectInfo) {
                return selectInfo.start >= new Date() && selectInfo.start < maxDate;
            },

            select: function(info) {
                const dateStr  = info.startStr.substring(0, 10);
                const startStr = info.startStr.substring(11, 16);
                const endStr   = info.endStr.substring(11, 16);

                showPopup(dateStr, roundToSlot(startStr), roundToSlot(endStr));
            },

            events: function(fetchInfo, successCallback, failureCallback) {
                fetch(API_URL)
                    .then(r => r.json())
                    .then(data => {
                        const events = data.map(ev => {
                            const isOwn = currentUserId !== null && ev.user_id === currentUserId;
                            return {
                                ...ev,
                                classNames: [isOwn ? 'own-slot' : 'booked-slot'],
                                editable: false,
                                extendedProps: {
                                    isOwn,
                                    annotation: isOwn ? (ev.annotation || '') : ''
                                }
                            };
                        });
                        successCallback(events);
                    })
                    .catch(failureCallback);
            },

            eventDidMount: function(info) {
                const { isOwn, annotation } = info.event.extendedProps;
                if (isOwn) {
                    info.el.title = annotation
                        ? `Meine Buchung\nAnmerkung: ${annotation}`
                        : 'Meine Buchung';
                } else {
                    info.el.title = 'Belegt';
                }
            },

            eventClick: function(info) {
                const { isOwn, annotation } = info.event.extendedProps;
                if (isOwn) {
                    alert(annotation ? `Ihre Buchung\nAnmerkung: ${annotation}` : 'Ihre Buchung');
                } else {
                    alert('Dieser Zeitraum ist bereits gebucht.');
                }
            },
        });

        calendar.render();

        // ── Buchen ───────────────────────────────────────────

        document.getElementById('btn-submit').addEventListener('click', function () {
            const name       = document.getElementById('input-name').value.trim();
            const annotation = document.getElementById('input-annotation').value.trim();
            const date       = document.getElementById('input-date').value;
            const start      = startSelect.value;
            const end        = endSelect.value;

            errBox.classList.add('d-none');

            const bookingDate = new Date(date);
            bookingDate.setHours(0, 0, 0, 0);
            if (bookingDate >= maxDate) {
                showError('Buchungen sind nur bis maximal 7 Tage im Voraus möglich.');
                return;
            }

            if (!name) {
                showError('Bitte geben Sie Ihren Namen ein.');
                return;
            }

            const [sh, sm] = start.split(':').map(Number);
            const [eh, em] = end.split(':').map(Number);
            const durationMin = (eh * 60 + em) - (sh * 60 + sm);

            if (durationMin < MIN_DURATION_MIN) {
                showError('Die Buchungsdauer muss mindestens 1 Stunde betragen.');
                return;
            }
            if (durationMin > MAX_DURATION_MIN) {
                showError('Die Buchungsdauer darf maximal 6 Stunden betragen.');
                return;
            }

            fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name:       name,
                    annotation: annotation,
                    start_time: date + ' ' + start + ':00',
                    end_time:   date + ' ' + end   + ':00',
                }),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.errors) {
                        showError(Object.values(data.errors).flat().join(' '));
                    } else {
                        window.location.href = '/site/confirm'
                            + '?name='       + encodeURIComponent(data.name)
                            + '&start='      + encodeURIComponent(data.start)
                            + '&end='        + encodeURIComponent(data.end)
                            + '&token='      + encodeURIComponent(data.cancel_token)
                            + '&annotation=' + encodeURIComponent(data.annotation || '');
                    }
                })
                .catch(() => showError('Verbindungsfehler zum Server.'));
        });

    });
</script>