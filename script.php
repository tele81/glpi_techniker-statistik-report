<?php
// GLPI Konfiguration und Includes laden
define('GLPI_ROOT', __DIR__);
include(GLPI_ROOT . "/inc/includes.php");

// PHPMailer laden
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


// Funktion zur Berechnung der Bearbeitungszeit
function berechneBearbeitungszeit($start, $end) {
    if (!$end) {
        return "Noch nicht abgeschlossen";
    }

    $startDateTime = new DateTime($start);
    $endDateTime = new DateTime($end);
    $diff = $startDateTime->diff($endDateTime);

    if ($diff->days > 0) {
        return $diff->days . " Tage, " . $diff->h . " Stunden";
    } elseif ($diff->h > 0) {
        return $diff->h . " Stunden, " . $diff->i . " Minuten";
    } else {
        return $diff->i . " Minuten";
    }
}

// Funktion zur Übersetzung des Ticketstatus
function ticketStatus($status) {
    $statuses = [
        1 => "Neu",
        2 => "In Bearbeitung",
        4 => "Wartend",
        5 => "Gelöst",
        6 => "Geschlossen"
    ];
    return $statuses[$status] ?? "Unbekannt";
}

// Überprüfen, ob die DB-Klasse existiert
if (!class_exists('DB')) {
    die('Datenbank-Klasse nicht gefunden. Überprüfe den Include-Pfad.');
}

// Erstelle eine Instanz von DB
$db = new DB();

// Bestimmen der Periode (woche, monat, jahr)
$period = isset($argv[1]) ? $argv[1] : 'week';  // Argument von der Kommandozeile (z.B. "month", "year")
$startOfPeriod = $endOfPeriod = '';
$periodLabel = '';
$calendarPeriod = '';

// Für Woche
if ($period === 'week') {
    $startOfPeriod = date('Y-m-d 00:00:00', strtotime('monday this week'));
    $endOfPeriod = date('Y-m-d 23:59:59', strtotime('friday this week'));
    $periodLabel = 'Woche';
    $calendarPeriod = date('d.m.Y', strtotime('monday this week')) . ' bis ' . date('d.m.Y', strtotime('friday this week'));
}
// Für Monat
elseif ($period === 'month') {
    $startOfPeriod = date('Y-m-01 00:00:00');
    $endOfPeriod = date('Y-m-t 23:59:59');
    $periodLabel = 'Monat';
    $calendarPeriod = date('m.Y');
}
// Für Jahr
elseif ($period === 'year') {
    $startOfPeriod = date('Y-01-01 00:00:00');
    $endOfPeriod = date('Y-12-31 23:59:59');
    $periodLabel = 'Jahr';
    $calendarPeriod = date('Y');
}


// Manuelle Liste der Techniker (E-Mail => Name)
$technikernamen = [
    'email@domain' => 'Name',
    'email2@domain' => 'Name2',

// Weitere Einträge hier hinzufügen
];

// Array mit den E-Mails der Techniker
$technikerEmails = array_keys($technikernamen);

// SQL-Abfrage für Ticket- und Benutzerdaten, nur für Techniker aus der Liste
$sql = "SELECT 
    u.firstname AS Techniker_Vorname,
    u.name AS Techniker_Nachname,
    ue.email AS Techniker_Email,
    t.id AS Ticket_ID,
    t.name AS Ticket_Titel,
    t.date AS Erstellung,
    t.date_mod AS Letzte_Aenderung,
    t.solvedate AS Abschlussdatum,
    t.status AS Ticket_Status,
    u_creator.firstname AS Ersteller_Vorname,
    u_creator.name AS Ersteller_Nachname,
    u_lastupdater.firstname AS Bearbeiter_Vorname,
    u_lastupdater.name AS Bearbeiter_Nachname,
    COUNT(CASE WHEN t.date >= '$startOfPeriod' AND t.users_id_recipient = u.id THEN 1 END) OVER (PARTITION BY u.id) AS Neu_Erstellte_Tickets,
    COUNT(CASE WHEN t.date_mod BETWEEN '$startOfPeriod' AND '$endOfPeriod' THEN 1 END) OVER (PARTITION BY u.id) AS Bearbeitete_Tickets,
    COUNT(CASE WHEN t.status = 4 AND t.date_mod BETWEEN '$startOfPeriod' AND '$endOfPeriod' THEN 1 END) OVER (PARTITION BY u.id) AS Auf_Wartend_Tickets,
    COUNT(CASE WHEN t.status = 5 AND t.date_mod BETWEEN '$startOfPeriod' AND '$endOfPeriod' THEN 1 END) OVER (PARTITION BY u.id) AS Geloeste_Tickets,
    COUNT(CASE WHEN t.status = 6 AND t.solvedate BETWEEN '$startOfPeriod' AND '$endOfPeriod' THEN 1 END) OVER (PARTITION BY u.id) AS Geschlossene_Tickets
FROM 
    glpi_tickets t
LEFT JOIN 
    glpi_users u ON t.users_id_lastupdater = u.id
LEFT JOIN 
    glpi_useremails ue ON u.id = ue.users_id
LEFT JOIN 
    glpi_users u_creator ON t.users_id_recipient = u_creator.id
LEFT JOIN 
    glpi_users u_lastupdater ON t.users_id_lastupdater = u_lastupdater.id
WHERE 
    (t.date_mod BETWEEN '$startOfPeriod' AND '$endOfPeriod')
    AND ue.email IS NOT NULL
    AND ue.email IN ('" . implode("','", $technikerEmails) . "')
ORDER BY 
    Geschlossene_Tickets DESC, t.date ASC;";

// Führe die SQL-Abfrage aus
$result = $db->query($sql);
if (!$result) {
    die('Datenbankfehler: ' . $db->error());
}

function berechneScore($techniker) {
    return ($techniker['neu_erstellte'] * 1.5) + 
           ($techniker['bearbeitete'] * 2.5) + 
           ($techniker['auf_wartend'] * 1) + 
           ($techniker['geloeste'] * 3) + 
           ($techniker['geschlossene'] * 1.5);
}

// Techniker-Daten sammeln
$technikerSummary = [];
$totalNewTickets = 0; // Gesamtzahl der neuen Tickets
$totalClosedTickets = 0; // Gesamtzahl der geschlossenen Tickets im Zeitraum

while ($row = $db->fetchArray($result)) {
    $email = $row['Techniker_Email'];
    $technikerSummary[$email]['name'] = "{$row['Techniker_Vorname']} {$row['Techniker_Nachname']}";
    $technikerSummary[$email]['vorname'] = $row['Techniker_Vorname']; // Vorname speichern
    $technikerSummary[$email]['neu_erstellte'] = $row['Neu_Erstellte_Tickets'];
    $technikerSummary[$email]['bearbeitete'] = $row['Bearbeitete_Tickets'];
    $technikerSummary[$email]['auf_wartend'] = $row['Auf_Wartend_Tickets'];
    $technikerSummary[$email]['geloeste'] = $row['Geloeste_Tickets'];
    $technikerSummary[$email]['geschlossene'] = $row['Geschlossene_Tickets'];

    // Gesamtzahl der neuen und geschlossenen Tickets im Zeitraum
    $totalNewTickets += $row['Neu_Erstellte_Tickets'];
    $totalClosedTickets += $row['Geschlossene_Tickets'];
}

// Berechnung der Gesamtzahl der geschlossenen Tickets über alle Techniker hinweg
$totalClosedTicketsAllTechnicians = array_sum(array_column($technikerSummary, 'geschlossene'));

// Techniker nach geschlossenen Tickets sortieren (Ranking)
foreach ($technikerSummary as $email => &$techniker) {
    $techniker['score'] = berechneScore($techniker);
}
unset($techniker); // Verhindert Referenzprobleme bei foreach

uasort($technikerSummary, function($a, $b) {
    return $b['score'] <=> $a['score']; // Sortiert nach berechnetem Score (höchster zuerst)
});

// Disclaimer ganz oben hinzufügen
$disclaimer = "
    <p style='font-size: 16px; color: #666;'> 
        Diese E-Mail dient ausschließlich zur Information und soll keinesfalls als Bewertung oder Kritik verstanden werden.</br>
        Vielmehr soll sie uns alle motivieren, weiterhin unser Bestes zu geben und uns gegenseitig durch konstruktive Rückmeldungen zu unterstützen.</br>
        Jeder von uns leistet einen wertvollen Beitrag, und der Austausch hilft uns, noch besser zu werden.</br>
        Wir danken euch allen für eure großartige Arbeit!
    </p>";

// HTML-Nachricht vorbereiten
$message = "
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h2 {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        .rank {
            font-weight: bold;
        }
        .green {
            background-color: #dff0d8;
        }
        .blue {
            background-color: #d9edf7;
        }
        .gray {
            background-color: #f9f9f9;
        }
        .gold {
            background-color: #ffd700;  /* Gold */
            font-weight: bold;
        }
        .silver {
            background-color: #c0c0c0;  /* Silber */
            font-weight: bold;
        }
        .bronze {
            background-color: #cd7f32;  /* Bronze */
            font-weight: bold;
        }
        .none {
            background-color: #f9f9f9;  /* Grau für alle anderen */
        }
    </style>
</head>
<body>

    $disclaimer  <!-- Disclaimer hier einfügen -->

    <h2>Zusammenfassung für $periodLabel Zeitraum: $calendarPeriod</h2>

    <h3>Mitarbeiter-Ranking (nach Score):</h3>
    <table>
        <tr>
            <th>Position</th>
            <th>Mitarbeiter</th>
            <th>Erstellte Tickets via Telefon</th>
            <th>Bearbeitete Tickets</th>
            <th>Wartend</th>
            <th>Gelöste Tickets</th>
            <th>Geschlossene Tickets</th>
            <th>Score</th>

        </tr>";

$rank = 1;
foreach ($technikerSummary as $technikerEmail => $techniker) {
    $badgeClass = '';
    $rankBadge = '';
    if ($rank == 1) {
        $badgeClass = 'gold';
        $rankBadge = '🥇';
    } elseif ($rank == 2) {
        $badgeClass = 'silver';
        $rankBadge = '🥈';
    } elseif ($rank == 3) {
        $badgeClass = 'bronze';
        $rankBadge = '🥉';
    } else {
        $badgeClass = 'none';
    }

    $message .= "
    <tr class='$badgeClass'>
        <td class='rank'>$rank. Position</td>
        <td>{$techniker['vorname']} $rankBadge</td>
        <td>{$techniker['neu_erstellte']}</td>
        <td>{$techniker['bearbeitete']}</td>
        <td>{$techniker['auf_wartend']}</td>
        <td>{$techniker['geloeste']}</td>
        <td>{$techniker['geschlossene']}</td>
        <td>{$techniker['score']}</td>

    </tr>";
    $rank++;
}

// Abschluss und Signatur

$message .= "</table><p><br></p>
  <div style='font-family: Arial, sans-serif; margin: 20px; color: #333;'>
    <h3>📢 Wichtiger Hinweis zur Score-Berechnung</h3>
    <p>Der Score wird anhand einer festgelegten Formel berechnet, die verschiedene Faktoren wie erstellte, bearbeitete, gelöste und geschlossene Tickets berücksichtigt.<br/>
       Die Gewichtung soll den tatsächlichen Arbeitsaufwand möglichst fair widerspiegeln.</p>
       <p>🔹 Score-Berechnung: Score = (Erstellte Tickets × 1.5) + (Bearbeitete Tickets × 2.5) + (Wartende Tickets × 1) + (Gelöste Tickets × 3) + (Geschlossene Tickets × 1.5)</p>
    <h3>💡 Wichtige Punkte zur Fairness:</h3>
    <ul style='list-style-type: none; padding: 0;'>
        <li>✔️ Der Score dient ausschließlich zur Orientierung und nicht als Bewertung einzelner Personen.</li>
        <li>✔️ Unterschiedliche Ticketarten können variierenden Aufwand erfordern – diese Berechnung kann nicht jeden Einzelfall abbilden.</li>
        <li>✔️ Die Erhebung erfolgt transparent, um einen objektiven Überblick zu ermöglichen.</li>
    </ul>
  </div>";

    $message .= "<p class='signature'></br></p>";
    $message .= "";
    $message .= "<p>--</p>";
    $message .= "<p>------------------------------------------------------------------------------------------------</p>";
    $message .= "<p>Text</p>";
    $message .= "<p>Text</p>";
    $message .= "<p>Text</p>";
    $message .= "<p>Text</p>";
    $message .= "<p>Text</p>";
    $message .= "<p>------------------------------------------------------------------------------------------------</p>";
    $message .= "<p>Automatisch generiert von GLPI</p>";
    $message .= "</body></html>";


// E-Mail-Versand vorbereiten
$year = date('Y');
$subject = "$periodLabel Ticket-Zusammenfassung für $calendarPeriod - Mitarbeiter Überblick";
$from = 'Absender@email.de';
$replyTo = 'replyTo@email.de';

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.office365.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'Absender@email.de';
    $mail->Password = 'passwort';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom($from, 'GLPI System');
    $mail->addAddress('Empfänger@email.de'); 
    $mail->addReplyTo($replyTo, 'GLPI System');

    $mail->Subject = $subject;
    $mail->Body    = $message;
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    if ($mail->send()) {
        echo "E-Mail wurde erfolgreich gesendet\n";
    } else {
        echo "Fehler beim Senden der E-Mail\n";
    }
} catch (Exception $e) {
    echo "Fehler beim Senden der E-Mail: {$mail->ErrorInfo}\n";
}

?>
