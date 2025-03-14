# GLPI Techniker-Statistik-Report

Dieses PHP-Skript generiert einen Bericht über die Performance von Technikern in einem GLPI (Gestionnaire libre de parc informatique)-System, basierend auf den von ihnen bearbeiteten Tickets in einem bestimmten Zeitraum (Woche, Monat oder Jahr). 
Der Bericht wird als HTML-E-Mail formatiert und kann an eine vordefinierte Liste von Technikern gesendet werden.

## Voraussetzungen

- **GLPI**: Das Skript erfordert eine laufende GLPI-Instanz mit Zugang zur Datenbank und den entsprechenden Tabellen.
- **PHP**: Mindestens PHP 7.4 wird benötigt.
- **PHPMailer**: Wird für den Versand der E-Mail benötigt. Stellen Sie sicher, dass PHPMailer über `composer` installiert wurde.

  ```bash
  composer require phpmailer/phpmailer


Datenbankzugang: Die Datenbankverbindung wird durch die DB-Klasse von GLPI hergestellt.

### Funktionen
### 1. berechneBearbeitungszeit($start, $end)

Berechnet die Bearbeitungszeit eines Tickets zwischen einem Start- und Enddatum.

Eingaben: Zwei Datumswerte (Start und Ende).
Ausgabe: Ein String, der die Dauer in Tagen, Stunden und Minuten anzeigt, oder "Noch nicht abgeschlossen", wenn kein Enddatum vorhanden ist.

### 2. ticketStatus($status)

Übersetzt den Status eines Tickets in eine lesbare Form (z.B. "Neu", "In Bearbeitung", "Gelöst").

Eingaben: Der Statuscode eines Tickets.
Ausgabe: Der Status des Tickets in lesbarer Form.

### 3. berechneScore($techniker)

Berechnet den Score für jeden Techniker, basierend auf der Anzahl der bearbeiteten, gelösten und geschlossenen Tickets.

Eingaben: Ein Array mit Ticketdaten eines Technikers.
Ausgabe: Ein numerischer Score, der zur Rangfolge verwendet wird.

### 4. Periodenbestimmung

Das Skript kann für verschiedene Zeiträume (Woche, Monat, Jahr) ausgeführt werden. Der Zeitraum wird entweder durch das Kommandozeilenargument oder standardmäßig auf "Woche" gesetzt.

    Beispiel: $ php script.php month für den monatlichen Bericht.

### Funktionsweise
Datenbankabfrage: Das Skript führt eine SQL-Abfrage auf die GLPI-Datenbank aus, um Ticket- und Benutzerdaten der Techniker in einem bestimmten Zeitraum abzurufen.
Techniker-Statistiken: Es werden Statistiken wie die Anzahl der neuen, bearbeiteten, gelösten und geschlossenen Tickets für jeden Techniker berechnet.
Berichtserstellung: Basierend auf diesen Daten wird ein HTML-Bericht generiert, der die Techniker in einer Rangliste anzeigt.
E-Mail-Versand: Das Skript sendet den Bericht per E-Mail an die Techniker.

### Manuelle Liste der Techniker

### Die E-Mail-Adressen der Techniker sind im Skript hinterlegt:

    $technikernamen = [
    'm.max@doamin.de' => 'Max',
    'a.suess@web.de' => 'Arnd',
    // Weitere Einträge hier hinzufügen
    ];


### Berichtserstellung

### Der Bericht enthält:

Mitarbeiter-Ranking: Eine Rangliste der Techniker basierend auf ihrem Score.
Ticketstatistiken: Details zu den erstellten, bearbeiteten, gelösten und geschlossenen Tickets.
Score-Berechnung: Eine detaillierte Erklärung zur Berechnung des Scores, basierend auf der Anzahl von Tickets in verschiedenen Status.

### E-Mail-Inhalt

### Das Skript sendet eine HTML-E-Mail mit folgenden Abschnitten:

Disclaimer (Hinweis zur Fairness der Berechnung)
Zusammenfassung des Zeitraums (Woche, Monat oder Jahr)
Ranking der Techniker nach Score
Eine Erklärung zur Score-Berechnung

### Verwendung

Führen Sie das Skript über die Kommandozeile aus:

    php script.php <periode>

Ersetzen Sie <periode> mit einem der folgenden Werte:

    week (Woche)
    month (Monat)
    year (Jahr)

### Beispiel:

    php script.php week

### Erklärung:

- **Installation von PHPMailer**: Für den Versand der E-Mails benötigt das Skript PHPMailer, daher sollte dieser über Composer installiert werden.
- **Manuelle Liste der Techniker**: Techniker müssen manuell im Skript hinzugefügt werden, basierend auf ihren E-Mail-Adressen.
- **Perioden**: Das Skript unterstützt die Perioden "Woche", "Monat" und "Jahr", die per Argument beim Ausführen angegeben werden können.


