<?php
/**
 * KORTZEN - Google Calendar API Helper
 * Sincroniza automáticamente las reservas en el Google Calendar del cliente
 */

function agendarEnGoogleCalendar($accessToken, $citaData) {
    if (empty($accessToken)) return false;

    $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    $servicio = $citaData['servicio'] ?? 'Corte de Autor';
    $barbero = $citaData['barbero'] ?? 'Master Barber';
    $fechaHora = $citaData['fecha_hora'] ?? date('Y-m-d H:i:s');
    $duracionMinutos = intval($citaData['duracion_minutos'] ?? 35);

    $startTs = strtotime($fechaHora);
    $endTs = $startTs + ($duracionMinutos * 60);

    // Formato RFC3339 para Ecuador (UTC-5)
    $startIso = date('Y-m-d\TH:i:s-05:00', $startTs);
    $endIso = date('Y-m-d\TH:i:s-05:00', $endTs);

    $event = [
        'summary' => "Cita KORTZEN: {$servicio}",
        'description' => "Tu reserva en KORTZEN Barbería con el profesional {$barbero}.\n¡Te esperamos para brindarte la mejor experiencia!",
        'location' => 'KORTZEN Llano Chico, Calle 17 de septiembre, Quito, Ecuador',
        'start' => [
            'dateTime' => $startIso,
            'timeZone' => 'America/Guayaquil'
        ],
        'end' => [
            'dateTime' => $endIso,
            'timeZone' => 'America/Guayaquil'
        ],
        'reminders' => [
            'useDefault' => false,
            'overrides' => [
                ['method' => 'popup', 'minutes' => 120],  // 2 horas antes
                ['method' => 'popup', 'minutes' => 1440]  // 1 día antes
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode >= 200 && $httpCode < 300);
}
