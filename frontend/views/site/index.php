<?php

/** @var yii\web\View $this */

$this->title = 'Raumbuchung – Universität Paderborn';

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

<!--
    Skip-Link (WCAG 2.4.1): Ermöglicht Tastaturnutzern,
    direkt zum Kalender zu springen ohne durch die Navigation zu tabben.
-->
<a href="#calendar" class="visually-hidden-focusable">Direkt zum Kalender springen</a>

<!-- Overlay: visuell + semantisch vom Rest getrennt -->
<div id="popup-overlay" class="d-none" aria-hidden="true"></div>

<!-- Hauptinhalt – wird beim Öffnen des Modals per inert gesperrt -->
<main id="main-content">

    <!--
        Lebendiger Bereich für Statusmeldungen (WCAG 4.1.3):
        Screenreader lesen Meldungen hier automatisch vor,
        ohne dass der Fokus wechseln muss.
    -->
    <div id="status-announcer"
         role="status"
         aria-live="polite"
         aria-atomic="true"
         class="visually-hidden">
    </div>

    <!-- Kalender -->
    <div id="calendar"
         class="mb-4"
         aria-label="Buchungskalender. Klicken oder ziehen Sie einen Zeitraum, um eine Buchung zu erstellen."
         tabindex="0">
    </div>

</main>

<!--
    BARRIEREFREIES MODAL (WAI-ARIA Dialog Pattern, W3C):

    role="dialog"        – identifiziert den Dialog für Assistenztechnologien
    aria-modal="true"    – signalisiert, dass der Hintergrund nicht bedienbar ist
    aria-labelledby      – verknüpft sichtbaren Titel mit dem Dialog (wird beim Öffnen vorgelesen)
    aria-describedby     – kurze Beschreibung des Dialogzwecks für Screenreader
    tabindex="-1"        – erlaubt programmatischen Fokus auf dem Container
-->
<div id="booking-popup"
     role="dialog"
     aria-modal="true"
     aria-labelledby="popup-title"
     aria-describedby="popup-description"
     class="d-none position-fixed top-50 start-50 translate-middle bg-white p-4"
     tabindex="-1">

    <h5 id="popup-title" class="mb-1">Raum buchen</h5>

    <!-- Versteckte Beschreibung für Screenreader -->
    <p id="popup-description" class="visually-hidden">
        Füllen Sie das Formular aus, um einen Raum zu buchen.
        Pflichtfelder sind mit einem Sternchen markiert.
        Mit der Escape-Taste können Sie den Dialog schließen.
    </p>

    <!--
        Fehlermeldung mit role="alert" (WCAG 3.3.1, 4.1.3):
        Wird von Screenreadern sofort vorgelesen, sobald sie eingeblendet wird,
        ohne dass der Nutzer den Fokus dorthin bewegen muss.
    -->
    <div id="popup-error"
         role="alert"
         aria-live="assertive"
         aria-atomic="true"
         class="alert alert-danger d-none py-2">
    </div>

    <!-- Name (readonly, da automatisch aus Login befüllt) -->
    <div class="mb-3">
        <label for="input-name" class="form-label">
            Name <span class="text-danger" aria-label="Pflichtfeld">*</span>
        </label>
        <input id="input-name"
               type="text"
               class="form-control"
               placeholder="Ihr Name"
               readonly
               aria-required="true"
               aria-readonly="true"
               autocomplete="name" />
    </div>

    <!-- Anmerkung -->
    <div class="mb-3">
        <label for="input-annotation" class="form-label">
            Anmerkung
            <small class="text-muted fw-normal">(optional)</small>
        </label>
        <input id="input-annotation"
               type="text"
               class="form-control"
               placeholder="z.B. Teammeeting, Schulung, Präsentation …"
               aria-required="false" />
    </div>

    <!-- Datum (readonly, wird durch Kalenderklick gesetzt) -->
    <div class="mb-3">
        <label for="input-date" class="form-label">Datum</label>
        <input id="input-date"
               type="text"
               class="form-control"
               readonly
               aria-readonly="true"
               aria-required="true" />
    </div>

    <!-- Start- und Endzeit -->
    <div class="row g-3 mb-3">
        <div class="col">
            <label for="input-start" class="form-label">Startzeit</label>
            <select id="input-start"
                    class="form-select"
                    aria-required="true">
            </select>
        </div>
        <div class="col">
            <label for="input-end" class="form-label">
                Endzeit
                <small class="text-muted fw-normal">(1–6 Std.)</small>
            </label>
            <select id="input-end"
                    class="form-select"
                    aria-required="true">
            </select>
        </div>
    </div>

    <!-- Buttons -->
    <div class="d-flex gap-2 mt-3">
        <button id="btn-submit"
                type="button"
                class="btn btn-primary flex-fill">
            Buchen
        </button>
        <!--
            Schließen-Button mit explizitem aria-label (WCAG 2.4.6):
            Macht den Zweck für Screenreader eindeutig.
            Ist immer der letzte Tab-Stopp – danach springt Fokus
            per Focus-Trap zurück zum ersten Element.
        -->
        <button id="btn-cancel"
                type="button"
                class="btn btn-secondary flex-fill"
                aria-label="Buchung abbrechen und Dialog schließen">
            Abbrechen
        </button>
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

        // ── DOM-Referenzen ──────────────────────────────────────────
        const popup       = document.getElementById('booking-popup');
        const overlay     = document.getElementById('popup-overlay');
        const errBox      = document.getElementById('popup-error');
        const announcer   = document.getElementById('status-announcer');
        const mainContent = document.getElementById('main-content');
        const startSelect = document.getElementById('input-start');
        const endSelect   = document.getElementById('input-end');

        // Merkt sich das Element, das das Modal geöffnet hat (für Fokus-Rückkehr)
        let triggerElement = null;

        // ── Hilfsfunktionen ─────────────────────────────────────────

        function toTimeStr(minutes) {
            const h = Math.floor(minutes / 60);
            const m = minutes % 60;
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }

        function sprintf(fmt, ...args) {
            return fmt.replace(/%02d/g, () => String(args.shift()).padStart(2, '0'));
        }

        function roundToSlot(time) {
            const [h, m] = time.split(':').map(Number);
            const rounded = Math.round((h * 60 + m) / 60) * 60;
            const clamped = Math.min(Math.max(rounded, 7 * 60), 21 * 60);
            return toTimeStr(clamped);
        }

        // Screenreader-Ansage ohne Fokus-Wechsel (WCAG 4.1.3)
        function announce(message) {
            announcer.textContent = '';
            setTimeout(() => { announcer.textContent = message; }, 100);
        }

        function showError(msg) {
            errBox.textContent = msg;
            errBox.classList.remove('d-none');
        }

        function clearError() {
            errBox.textContent = '';
            errBox.classList.add('d-none');
            document.getElementById('input-name').removeAttribute('aria-invalid');
        }

        // ── Focus Trap (WAI-ARIA Dialog Pattern, W3C) ───────────────
        //
        // Alle fokussierbaren Elemente im Modal ermitteln und
        // Tab/Shift+Tab zyklisch durch sie führen.
        // Escape schließt den Dialog.

        function getFocusableElements() {
            return Array.from(popup.querySelectorAll(
                'button:not([disabled]), input:not([disabled]), ' +
                'select:not([disabled]), textarea:not([disabled]), ' +
                '[tabindex]:not([tabindex="-1"])'
            ));
        }

        function handleFocusTrap(e) {
            if (e.key !== 'Tab') return;

            const focusable = getFocusableElements();
            if (focusable.length === 0) return;

            const first = focusable[0];
            const last  = focusable[focusable.length - 1];

            if (e.shiftKey) {
                // Shift+Tab auf erstem Element → zum letzten springen
                if (document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                // Tab auf letztem Element → zum ersten springen
                if (document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        }

        function handleEscape(e) {
            if (e.key === 'Escape') hidePopup();
        }

        // ── Popup öffnen / schließen ─────────────────────────────────

        function showPopup(dateStr, startTime, endTime, trigger) {
            // Auslösendes Element merken für Fokus-Rückkehr (WCAG 2.4.3)
            triggerElement = trigger || document.activeElement;

            document.getElementById('input-date').value       = dateStr;
            document.getElementById('input-name').value       = currentUser;
            document.getElementById('input-annotation').value = '';
            clearError();

            updateStartOptions(dateStr, startTime);
            updateEndOptions(startSelect.value, endTime);

            // Hintergrund für Screenreader und Tastatur sperren (MDN: aria-modal, inert)
            mainContent.inert = true;
            mainContent.setAttribute('aria-hidden', 'true');

            overlay.classList.remove('d-none');
            popup.classList.remove('d-none');

            // Fokus in Modal setzen – kurze Verzögerung wegen CSS-Transition
            setTimeout(() => {
                const firstFocusable = getFocusableElements()[0];
                if (firstFocusable) firstFocusable.focus();
                else popup.focus();
            }, 50);

            // Tastatur-Events registrieren
            document.addEventListener('keydown', handleFocusTrap);
            document.addEventListener('keydown', handleEscape);

            announce('Buchungsdialog geöffnet. Datum: ' + dateStr);
        }

        function hidePopup() {
            popup.classList.add('d-none');
            overlay.classList.add('d-none');

            // Hintergrund wieder zugänglich machen
            mainContent.inert = false;
            mainContent.removeAttribute('aria-hidden');

            // Tastatur-Events entfernen
            document.removeEventListener('keydown', handleFocusTrap);
            document.removeEventListener('keydown', handleEscape);

            // Fokus zurück zum auslösenden Element (WCAG 2.4.3)
            if (triggerElement && typeof triggerElement.focus === 'function') {
                triggerElement.focus();
            }
            triggerElement = null;
            calendar.unselect();
        }

        overlay.addEventListener('click', hidePopup);
        document.getElementById('btn-cancel').addEventListener('click', hidePopup);

        // ── Selects befüllen ─────────────────────────────────────────

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
                startSelect.innerHTML += `<option value="${t}">${t} Uhr</option>`;
            });

            if (preferredStart && [...startSelect.options].some(o => o.value === preferredStart)) {
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
                    endSelect.innerHTML += `<option value="${t}">${t} Uhr</option>`;
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

        // ── FullCalendar ─────────────────────────────────────────────

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
                const trigger  = document.activeElement;
                showPopup(dateStr, roundToSlot(startStr), roundToSlot(endStr), trigger);
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
                                title: isOwn ? ev.title : 'Belegt',
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

            // Barrierefreie Labels für Event-Blöcke (WCAG 1.1.1)
            eventDidMount: function(info) {
                const { isOwn, annotation } = info.event.extendedProps;
                const start = info.event.startStr ? info.event.startStr.substring(11, 16) : '';
                const end   = info.event.endStr   ? info.event.endStr.substring(11, 16)   : '';

                if (isOwn) {
                    const label = annotation
                        ? `Eigene Buchung ${start}–${end} Uhr, Anmerkung: ${annotation}`
                        : `Eigene Buchung ${start}–${end} Uhr`;
                    info.el.setAttribute('aria-label', label);
                    info.el.setAttribute('title', label);
                } else {
                    info.el.setAttribute('aria-label', `Belegter Zeitraum ${start}–${end} Uhr`);
                    info.el.setAttribute('title', 'Dieser Zeitraum ist belegt');
                }
            },

            eventClick: function(info) {
                const { isOwn, annotation } = info.event.extendedProps;
                if (isOwn) {
                    announce(annotation
                        ? `Ihre Buchung. Anmerkung: ${annotation}`
                        : 'Ihre Buchung.');
                } else {
                    announce('Dieser Zeitraum ist bereits gebucht.');
                }
            },
        });

        calendar.render();

        // ── Buchen ───────────────────────────────────────────────────

        document.getElementById('btn-submit').addEventListener('click', function () {
            const name       = document.getElementById('input-name').value.trim();
            const annotation = document.getElementById('input-annotation').value.trim();
            const date       = document.getElementById('input-date').value;
            const start      = startSelect.value;
            const end        = endSelect.value;

            clearError();

            const bookingDate = new Date(date);
            bookingDate.setHours(0, 0, 0, 0);
            if (bookingDate >= maxDate) {
                showError('Buchungen sind nur bis maximal 7 Tage im Voraus möglich.');
                return;
            }

            if (!name) {
                showError('Bitte geben Sie Ihren Namen ein.');
                document.getElementById('input-name').setAttribute('aria-invalid', 'true');
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

            // Ladezustand kommunizieren (WCAG 4.1.3)
            const btn = document.getElementById('btn-submit');
            btn.disabled = true;
            btn.textContent = 'Wird gespeichert …';
            announce('Buchung wird gespeichert …');

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
                        btn.disabled = false;
                        btn.textContent = 'Buchen';
                        showError(Object.values(data.errors).flat().join(' '));
                    } else {
                        announce('Buchung erfolgreich. Sie werden weitergeleitet.');
                        window.location.href = '/site/confirm'
                            + '?name='       + encodeURIComponent(data.name)
                            + '&start='      + encodeURIComponent(data.start)
                            + '&end='        + encodeURIComponent(data.end)
                            + '&token='      + encodeURIComponent(data.cancel_token)
                            + '&annotation=' + encodeURIComponent(data.annotation || '');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.textContent = 'Buchen';
                    showError('Verbindungsfehler zum Server. Bitte versuchen Sie es erneut.');
                });
        });

    });
</script>