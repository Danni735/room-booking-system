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
?>

<!-- Kalender -->
<div id="calendar" class="mb-4"></div>

<!-- Popup komplett mit Bootstrap-Klassen -->
<div id="booking-popup" class="d-none position-fixed top-50 start-50 translate-middle bg-white rounded shadow p-4" style="z-index:9999;">

    <h5 class="mb-3">Raum buchen</h5>

    <div id="popup-error" class="alert alert-danger d-none py-2" role="alert"></div>

    <!-- Name -->
    <div class="mb-3">
        <label for="input-name" class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
        <input id="input-name" type="text" class="form-control bg-light" placeholder="Ihr Name" readonly />
    </div>

    <!-- Anmerkung -->
    <div class="mb-3">
        <label for="input-annotation" class="form-label fw-semibold">
            Anmerkung <small class="text-muted fw-normal">(optional)</small>
        </label>
        <input id="input-annotation" type="text" class="form-control"
               placeholder="z.B. Teammeeting, Schulung, Präsentation …" />
    </div>

    <!-- Datum -->
    <div class="mb-3">
        <label for="input-date" class="form-label fw-semibold">Datum</label>
        <input id="input-date" type="text" class="form-control bg-light" readonly />
    </div>

    <!-- Start- und Endzeit -->
    <div class="row g-3 mb-3">
        <div class="col">
            <label for="input-start" class="form-label fw-semibold">Startzeit</label>
            <select id="input-start" class="form-select"></select>
        </div>
        <div class="col">
            <!-- Hinweis auf min/max Dauer -->
            <label for="input-end" class="form-label fw-semibold">
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

        const API_URL      = 'http://localhost:8081/bookings';
        const currentUser   = '<?= $currentUser ?>';
        const currentUserId = <?= $currentUserId ?? 'null' ?>; // ← eingeloggter User

        // Grenzen als Konstanten
        const MIN_DURATION_MIN = 60;   // 1 Stunde
        const MAX_DURATION_MIN = 360;  // 6 Stunden
        const SLOT_MAX_MIN     = 21 * 60;

        // Maximales Buchungsdatum (heute + 7 Tage)
        const today   = new Date();
        today.setHours(0, 0, 0, 0);
        // +8 weil validRange.end exklusiv ist → Tag 7 (z.B. 19.04) bleibt wählbar
        const maxDate = new Date(today);
        maxDate.setDate(today.getDate() + 8);

        // Zeitslots 07:00 - 21:00 in 60-Minuten-Schritten
        const timeSlots = <?= json_encode(array_map(function($m) {
            return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }, range(7 * 60, 21 * 60, 60))) ?>;

        const startSelect = document.getElementById('input-start');
        const endSelect   = document.getElementById('input-end');

        // Startzeit-Select befüllen – abhängig vom gewählten Datum
        // Wenn heute: vergangene Slots ausblenden; sonst alle erlaubten Slots
        function updateStartOptions(dateStr, preferredStart) {
            const now        = new Date();
            const isToday    = dateStr === now.toISOString().substring(0, 10);
            const nowMin     = now.getHours() * 60 + now.getMinutes();

            startSelect.innerHTML = '';
            timeSlots.forEach(t => {
                const [h, m] = t.split(':').map(Number);
                const tMin   = h * 60 + m;
                // Slot muss mind. 1h Restzeit bis 21:00 lassen
                if (tMin + MIN_DURATION_MIN > SLOT_MAX_MIN) return;
                // Bei heutigem Datum: vergangene/aktuelle Slots ausblenden
                if (isToday && tMin <= nowMin) return;
                startSelect.innerHTML += `<option value="${t}">${t}</option>`;
            });

            // Bevorzugte Startzeit setzen, falls noch verfügbar
            if (preferredStart && [...startSelect.options].some(o => o.value === preferredStart)) {
                startSelect.value = preferredStart;
            } else {
                startSelect.selectedIndex = 0;
            }
        }

        // Endzeit-Select dynamisch befüllen
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

        function toTimeStr(minutes) {
            const h = Math.floor(minutes / 60);
            const m = minutes % 60;
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }

        startSelect.addEventListener('change', function () {
            updateEndOptions(this.value);
        });

        // Popup Elemente
        const popup  = document.getElementById('booking-popup');
        const errBox = document.getElementById('popup-error');

        // bootstrap d-none statt style.display
        function showPopup(dateStr, startTime, endTime) {
            document.getElementById('input-date').value       = dateStr;
            document.getElementById('input-name').value       = currentUser;
            document.getElementById('input-annotation').value = '';
            errBox.classList.add('d-none');
            errBox.textContent = '';

            // Startzeit-Optionen abhängig vom Datum befüllen (heute → keine Vergangenheit)
            updateStartOptions(dateStr, startTime);
            updateEndOptions(startSelect.value, endTime);

            popup.classList.remove('d-none');
            document.getElementById('input-name').focus();
        }

        function hidePopup() {
            popup.classList.add('d-none');
            calendar.unselect(); // Selektion erst beim Schließen aufheben
        }

        function showError(msg) {
            errBox.textContent = msg;
            errBox.classList.remove('d-none');
        }

        function sprintf(fmt, ...args) {
            return fmt.replace(/%02d/g, () => String(args.shift()).padStart(2, '0'));
        }

        document.getElementById('btn-cancel').addEventListener('click', hidePopup);

        // FullCalendar
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

            // Kalender auf heute bis heute+7 Tage begrenzen
            validRange: {
                start: today,
                end:   maxDate,
            },

            // Keine Selektion über bestehende Buchungen möglich
            selectOverlap: false,

            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'timeGridWeek,timeGridDay'
            },

            // Vergangene Zeiten und Daten > 7 Tage nicht auswählbar
            selectAllow: function(selectInfo) {
                return selectInfo.start >= new Date() && selectInfo.start < maxDate;
            },

            select: function(info) {
                const dateStr  = info.startStr.substring(0, 10);
                const startStr = info.startStr.substring(11, 16);
                const endStr   = info.endStr.substring(11, 16);

                const start = roundToSlot(startStr);
                const end   = roundToSlot(endStr);

                showPopup(dateStr, start, end);
            },

            // Events aus API laden + CSS-Klasse "booked-slot" setzen
            events: function(fetchInfo, successCallback, failureCallback) {
                fetch(API_URL)
                    .then(r => r.json())
                    .then(data => {
                        const events = data.map(ev => {
                            // Eigene Buchung erkennen
                            const isOwn = currentUserId !== null && ev.user_id === currentUserId;

                            return {
                                ...ev,
                                // Eigene Buchungen: grün, fremde: rot
                                classNames: [isOwn ? 'own-slot' : 'booked-slot'],
                                editable: false,
                                extendedProps: {
                                    isOwn,
                                    bookedBy:   isOwn ? ev.title : 'Belegt',
                                    annotation: isOwn ? (ev.annotation || '') : ''
                                }
                            };
                        });
                        successCallback(events);
                    })
                    .catch(failureCallback);
            },

            // Tooltip mit Name und Anmerkung beim Hover
            eventDidMount: function(info) {
                const { isOwn, bookedBy, annotation } = info.event.extendedProps;
                if (isOwn) {
                    info.el.title = annotation
                        ? `Meine Buchung\nAnmerkung: ${annotation}`
                        : 'Meine Buchung';
                } else {
                    info.el.title = 'Belegt';
                }
            },

            // Klick auf belegtes Event
            eventClick: function(info) {
                const { isOwn, bookedBy, annotation } = info.event.extendedProps;
                if (isOwn) {
                    const msg = annotation
                        ? `Ihre Buchung\nAnmerkung: ${annotation}`
                        : 'Ihre Buchung';
                    alert(msg);
                } else {
                    alert('Dieser Zeitraum ist bereits gebucht.');
                }
            },
        });
        calendar.render();

        function roundToSlot(time) {
            const [h, m] = time.split(':').map(Number);
            const rounded = Math.round((h * 60 + m) / 60) * 60;
            const rh = Math.floor(rounded / 60);
            const rm = rounded % 60;
            const clamped = Math.min(Math.max(rh * 60 + rm, 7 * 60), 21 * 60);
            return sprintf('%02d:%02d', Math.floor(clamped / 60), clamped % 60);
        }

        // Buchen
        document.getElementById('btn-submit').addEventListener('click', function () {
            const name       = document.getElementById('input-name').value.trim();
            const annotation = document.getElementById('input-annotation').value.trim();
            const date       = document.getElementById('input-date').value;
            const start      = startSelect.value;
            const end        = endSelect.value;

            errBox.classList.add('d-none');

            // Datum darf max. 7 Tage in der Zukunft liegen
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

            // Dauer prüfen (min 1h, max 6h)
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

            const startDateTime = date + ' ' + start + ':00';
            const endDateTime   = date + ' ' + end   + ':00';

            fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name:       name,
                    annotation: annotation,
                    start_time: startDateTime,
                    end_time:   endDateTime,
                }),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.errors) {
                        const msgs = Object.values(data.errors).flat();
                        showError(msgs.join(' '));
                    } else {
                        window.location.href = '/site/confirm'
                            + '?name='       + encodeURIComponent(data.name)
                            + '&start='      + encodeURIComponent(data.start)
                            + '&end='        + encodeURIComponent(data.end)
                            + '&token='      + encodeURIComponent(data.cancel_token)
                            + '&annotation=' + encodeURIComponent(data.annotation || '');
                    }
                })
                .catch(() => {
                    showError('Verbindungsfehler zum Server.');
                });
        });

    });
</script>