# Raumbuchungssytem mit FullCalendar Integration

## Anwendung & Technologien
Das Raumbuchungssystem ermöglicht es eingeloggten Nutzerinnen und Nutzern, Räume über einen interaktiven Wochenkalender zu buchen, einzusehen und per individuellem Stornierungslink wieder freizugeben.

Die Anwendung wurde mit dem **Yii2 Advanced Template** (PHP) umgesetzt, das Frontend und Backend sauber voneinander trennt: Das Backend stellt eine REST-API bereit, die das Frontend per Fetch API asynchron konsumiert.

Als Kalenderkomponente kommt **FullCalendar** zum Einsatz, das Klick- und Drag-Interaktionen direkt im Kalender ermöglicht.

Die Oberfläche basiert auf **Bootstrap 5** mit den Primärfarben Pantone 281 (`#00205B`) und Pantone 299 (`#00A3E0`).

Barrierefreiheit nach **WCAG 2.1 Stufe AA** wurde konsequent berücksichtigt, unter anderem durch ein vollständig tastatur- und screenreadertaugliches Buchungspopup (nicht getestet).

- PHP 8.4
- Yii2-Advanced (V2.0.54)
- Composer
- SQLite (Version 3.53.0)
- FullCalendar (v6)
- Git (Latest: 2.53.0)
- JetBrains - PHPStorm (2026.1)
- GitHub
- getestet unter Firefox

## Installation

### Voraussetzungen
- PHP ≥ 8.4 mit den Extensions: `pdo_sqlite`, `sqlite3`, `mbstring`, `curl`, `zip`, `xml` ``
	- für Windows: in der php.ini die o.g. extensions aktivieren
	- unter Linux:
	``` 
	  sudo apt update
	  sudo apt install -y software-properties-common
	  sudo LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php -y
	  sudo apt update
	  sudo apt install -y php8.4 php8.4-cli php8.4-sqlite3 php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip unzip git
 	```
- Composer
	- für Windows Composer downloaden
	- unter Linux:
```
curl -sS https://getcomposer.org/installer | php sudo mv composer.phar /usr/local/bin/composer
```

- Git
	- für Windows Git downloaden
	- unter Linux:
```
sudo apt install git -y
```

### Schritte

**1. Repository klonen**

```
git clone https://github.com/Danni735/room-booking-system.git
cd room-booking-system 
```

**2. Abhängigkeiten installieren**

```
composer install
```

**3. Das Projekt initialisieren**
```
php init
```
→ `0` für **Development** wählen, mit `yes` bestätigen

**4. sqlite for dsn: main-local**
common/config/main-local.php
	-> replace

" components' => [...
'db' => [...
		'dsn' => 'sqlite:' . dirname(__DIR__) . '/runtime/database.db',
		...] ] "

**Server starten**

Terminal 1 – Frontend
```
php yii serve --docroot=frontend/web --port=8080 
```

Terminal 2 – Backend
```
php yii serve --docroot=backend/web --port=8081
```

**Frontend:** http://localhost:8080  
**Backend:** http://localhost:8081

## Hinweis zur Nutzung

**Login-Daten für das Frontend**
Benutzer: admin
Passwort: admin123
*//*
Benutzer: Daniela
Passwort: dap12345

Der Nutzer kann per Drag&Drop mit der Maus, seinen Zeitslot markieren und auswählen.

## Erläuterung wesentlicher Architektur- und Designentscheidungen

Yii2-Advanced ist perfekt für diese Aufgabe geeignet.

**MVC - Design Pattern**
Die Grundlage des Frameworks bildet das MVC Pattern, eine saubere Trennung von Geschäftslogik, Benutzeroberfläche und Datenfluss.
Ein "leichtgewichtiges" Framework mit hoher Kontrolle bis in die untersten Ebenen.
Der Code ist automatisch gut strukturiert. Es gibt eine einfache (alternative) Bedienbarkeit über den integrierten web basierten Code-Generator "Gii".
Gii´s Hauptfunktionen sind die Generatoren für Models, CRUD, Controller, Views, Module und Extensions.

**Layerd Architecture**
Die Advanced Version von Yii2 unterscheidet sich von der Basic Version dahingehend, dass sie eine klare Unterteilung von Front und Backend vorgibt.

**RBAC**
Role-Based Access Control ist in dem Fall wichtig, für die klare Zugriffskontrolle.
Das Login System ist bereits vollständig integriert.
Alternativ kann man für spätere Implementierungen, Usern bestimmte Rollen zuteilen sowie eine Standard Rolle beim Anmelden, sowohl im Frontend als auch im Backend.

```
┌─────────────────────────────────────────────┐
│           Browser – Frontend                │
│           Port 8080 · Yii2 Frontend         │
│                                             │
│  ┌─────────────────────────────────────┐   │
│  │  FullCalendar v6                    │   │
│  │  Klick · Drag · Wochenanzeige       │   │
│  └─────────────────────────────────────┘   │
│  ┌─────────────────────────────────────┐   │
│  │  Buchungspopup                      │   │
│  │  WCAG 2.1 AA · Focus Trap · ARIA    │   │
│  └─────────────────────────────────────┘   │
│  ┌──────────────┐  ┌──────────────────┐   │
│  │ AssetBundle  │  │  Bootstrap 5     │   │
│  │ JS/CSS lokal │  │  UPB Corp.Design │   │
│  └──────────────┘  └──────────────────┘   │
└──────────────┬──────────────────────────────┘
               │  Fetch API · JSON
               ▼
┌─────────────────────────────────────────────┐
│           REST-API – Backend                │
│           Port 8081 · Yii2 Backend          │
│                                             │
│  GET  /bookings        → Buchungen als JSON │
│  POST /bookings        → Anlegen +          │
│                          Doppelbuchungsprüf.│
│  POST /bookings/cancel → Token-Stornierung  │
│                                             │
│  BookingController · MVC · RBAC · REST      │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────┐
│           SQLite 3.53                       │
│                                             │
│  Tabellen: booking · room · user            │
│  Foreign Key: booking → room                │
│  Cancel-Token · User-ID                     │
└─────────────────────────────────────────────┘
```

1. **FullCalendar** als lokale JS-Dateien `/frontend/web/js/`
2. **AssetBundle** in Yii2 registrieren (JS/CSS registrieren)
3. **REST-Controller** in Yii2 (`BookingController`)
4. **Booking-Modell** + Migration (Tabelle mit Start/End-Zeit) -> booking Tabelle
5. **Vue-App** im View einbinden (kein `.vue`-Build, reines JS) FullCalendar + Buchungsformular
6. **Doppelbuchungs-Prüfung** serverseitig in PHP

Vorteile dieser Lösung
- **Reaktivität**: Der Kalender reagiert sofort, ohne dass die ganze Seite neu geladen werden muss.
- **Modularität**: Man kannst den Kalender als eigenständige Komponente überall im System wiederverwenden.
- **State Management**: Mit Vue kann man Buchungszustände (z. B. "Wird gerade geladen...") viel eleganter anzeigen. 

## eingesetzte KI-Tools

**Claude Code**
Die grundlegenden Design und Technologie Entscheidungen habe ich getroffen.
Yii2-Advanced war mir vorher bekannt, jedoch habe ich mich über SQLite vorher über die KI informiert, sowie Vor- und Nachteile von Yii2 in Verbindung mit der Aufgabenstellung abgewogen.

FullCalendar war mir noch nicht bekannt, hier hab ich die KI genutzt um einen passenden Kalender zu finden.
Herausforderung: z.B. hat Claude mir ein sehr altes Plugin für Yii2 herausgesucht, ich habe mich auf der Seite von FullCalendar in die Dokumentation eingelesen und den für mich passenden Weg herausgesucht. In weiteren Schritten hat mich die KI bei der Integration von FullCalender unterstützt.

Das Grundgerüst (MVC) habe ich erstellt, die KI habe ich viel Programmcode schreiben lassen, ihn analysiert, getestet und angepasst.

Bei Fehlern im Code oder auch zur Ausbesserung der Dokumentation, hat mich Claude unterstützt.

"Die Installationsanleitung in dieser README basiert auf einer Zusammenfassung meiner Konversation mit Claude. Nachdem ich verschiedene Ansätze für meinen spezifischen Workaround getestet habe, wurde die hier beschriebene, saubere Vorgehensweise final von der KI generiert ". [dieser Textabschnitt ist von Gemini generiert worden].

**Gemini**
Als Unterstützung außerhalb der Programmierung, habe ich auf Google Gemini zugegriffen.

## Bekannte Einschränkungen oder offene Punkte
Das System ist **erweiterbar** für mehrere Räume.
Eine "room" Tabelle gibt die "id" für eine beliebige Anzahl an Räumen vor.
Diese ist mit der "booking" Tabelle verknüpft. Zum Zeitpunkt der Veröffentlichung ist es noch nicht möglich mehrere Räume zu buchen.

Die Spalte "user_id" in der "booking" wird noch nicht mit der Immatrikulationsnummer oder Personalnummer gefüllt.

Hinweise für den Nutzer bezüglich der Bedeutung der Farben im Kalender, das man nur 7 Tage im Voraus buchen kann, sowie ein Hinweis zu den Öffnungszeiten, ist noch denkbar.

## Dokumentation der REST Endpoint

Die REST-API des Raumbuchungssystems umfasst drei Endpunkte unter der Basis-URL 
http://localhost:8081. Über "GET /bookings" werden alle vorhandenen Buchungen als JSON-Array abgerufen, das direkt von FullCalendar als Kalender-Events verarbeitet wird.

Neue Buchungen werden per POST /bookings mit Name, optionaler Anmerkung sowie Start- und Endzeit als JSON-Body angelegt.
Bei Erfolg antwortet der Server mit den gespeicherten Daten inklusive eines einmaligen Stornierungstokens.

Ist ein Zeitraum bereits belegt, gibt die API den Statuscode 422 mit einer Fehlermeldung zurück, die im Frontend direkt im Buchungspopup angezeigt wird. Die Stornierung erfolgt über POST /bookings/cancel mit dem Token als einzigem Parameter – ist der Token gültig, wird die Buchung gelöscht und eine Bestätigung zurückgegeben, andernfalls antwortet die API mit 404.


## Deployment

1. Der fertiggestellte Code wird via Git auf GitHub z.B. in eine neu erstellte Feature Branch hochgeladen.
2. Wenn alles funktioniert, wird der Code auf die DEV Branch gebracht (Pull/ Merge Request).
   2.1 Building, Testing und Deploy der Application (z.B. mit GitHub Actions)
3. Von der Dev Branch geht es zur Testing Branch.
4. Von der Testing Branch geht es zur Master/Main Branch.
5. Checking und Monitoring nach dem Deployment kontinuierlich.

Bedingungen:
- alle geheimen Werte in die eine .env Datei auslagern (DB-Daten, App_Key (CookieValidationKey))
- .env Datei in die .gitignore schreiben (niemals committen), per SSH einloggen und hochladen oder anlegen
  - fällt hier weg, da die generierten Files wie z.B. /frontend/web/index.php und /backend/web/index.php für die Production ausgeschlossen werden.
- Yii_DEBUG = false und Yii_ENV = production
- $ composer require vlucas/phpdotenv um die Variablen `$_ENV` and `$_SERVER` zu nutzen
	- Lädt die .env-Datei und macht sie als $_ENV verfügbar (common/config/bootstrap.php)
	```
 	$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
	$dotenv->load();
	```
- composer install --no-dev -> Vendor wird auf dem Server neu gebaut

## Buchungen

- Hauptrprüfung im Booking-Model
- Unique Constraint - Datenbank
 z.B. nur eine Anfrage gleichzeitig durchlassen
im Booking-Controller -> actionCreate(){} -> Tabelle wird für die Dauer der Prüfung gesperrt

## Daten werden vom Backend ans Frontend übergeben im Booking Controller
``` 
	if ($booking->save()) {
	// hier werden die Daten ans Frontend übergeben
		Yii::$app->response->statusCode = 201;
		return [
		'id'           => $booking->id,
		'name'        => $booking->name,
		...
		];
	}
```

**Hier wird abgebrochen:**
	```
	Yii::$app->response->statusCode = 422;
	return ['errors' => $booking->errors];
	```
**Fehlermeldung muss in der Frontend index.php noch abgefangen werden.**
if (response.status === 201) {

} else if (response.status === 422) {
// data = { errors: { start_time: ["Zeitraum bereits gebucht"] } }

} else {
// DB-Exception oder sonstiger Server-Fehler
document.getElementById('fehler-meldung').textContent = 'Server-Fehler, bitte erneut versuchen.';
}

**mögliche CORS Fehler**

- $behaviors muss in BookingController.php als erstes definiert werden
- weil ich im header diesen Header schicke -> headers: { 'Content-Type': 'application/json' } 
  - muss ich 'Access-Control-Allow-Headers'   => ['Content-Type', 'X-Requested-With'], in die behaviors setzen
	denn Browser blockt die Anfrage beim Preflight

## Lizenzen
- [FullCalendar](https://fullcalendar.io/license) – Non-Commercial License