<?php
// Staff Vehicle Non-Assignment Cron Job
// Runs hourly to check for trips in the next 4 hours without vehicle assignments
// Sends structured email notifications to Front Office

include "../../includes/common_api.php";
include "../../includes/_send_mail.php";

// Set timezone and current time
date_default_timezone_set('Asia/Kolkata');
$NOW = date('Y-m-d H:i:s');


 $checkFromTime = date('Y-m-d H:i:s');
 $checkToTime = date('Y-m-d H:i:s', strtotime('+4 hours'));

// Query to find trips without vehicle assignments in the next 4 hours
$sql = "SELECT 
            t.iTripID,
            t.dtTrip,
            r.vName as routeName,
            r.vDestination as destination,
            t.iCapacity,
            t.iRequested as requestedPax,
            COUNT(DISTINCT tva.iVehicleID) as assignedVehicles
        FROM st_trips t
        LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
        LEFT JOIN st_trip_vehicle_assoc tva ON t.iTripID = tva.iTripID AND tva.cStatus = 'A'
        WHERE t.cStatus = 'A'
        AND t.dtTrip BETWEEN '$checkFromTime' AND '$checkToTime'
        GROUP BY t.iTripID, t.dtTrip, r.vName, r.vDestination, t.iCapacity
        HAVING assignedVehicles = 0 AND requestedPax > 0
        ORDER BY t.dtTrip ASC";
        // echo $sql;
        // exit;

$result = sql_query($sql);

if (sql_num_rows($result) > 0) {
    $unassignedTrips = [];
    
    while ($row = sql_fetch_assoc($result)) {
        $unassignedTrips[] = [
            'tripID' => $row['iTripID'],
            'date' => date('d/m/Y', strtotime($row['dtTrip'])),
            'time' => date('g:i A', strtotime($row['dtTrip'])),
            'routeName' => $row['routeName'],
            'destination' => $row['destination'],
            'requestedPax' => $row['requestedPax'],
            'capacity' => $row['iCapacity']
        ];
    }
    
    if (!empty($unassignedTrips)) {
        // Prepare email content
        $email_subject = " Vehicle Assignment Required - " . count($unassignedTrips) . " Trip(s) Without Vehicles";
        
        // Build structured email content
        $email_content = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { background-color: #f44336; color: white; padding: 15px; text-align: center; }
                .content { padding: 20px; }
                .trip-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .trip-table th, .trip-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                .trip-table th { background-color: #f2f2f2; font-weight: bold; }
                .trip-table tr:nth-child(even) { background-color: #f9f9f9; }
                .urgent { color: #f44336; font-weight: bold; }
                .footer { margin-top: 30px; padding: 15px; background-color: #f5f5f5; border-left: 4px solid #f44336; }
            </style>
        </head>
        <body>
          
            
            <div class='content'>
                <p>Dear Front Office Team,</p>
                
                <p>The following " . count($unassignedTrips) . " trip(s) scheduled for the next 4 hours do not have vehicles assigned:</p>
                
                <table class='trip-table'>
                    <thead>
                        <tr>
                         
                            <th>Date</th>
                            <th>Time</th>
                            <th>Route</th>
                            <th>Destination</th>
                            <th>Passengers</th>
                 
                        </tr>
                    </thead>
                    <tbody>";
        
        foreach ($unassignedTrips as $trip) {
            $email_content .= "
                        <tr>
                          
                            <td>{$trip['date']}</td>
                            <td><strong>{$trip['time']}</strong></td>
                            <td><strong>{$trip['routeName']}</strong></td>
                            <td>{$trip['destination']}</td>
                            <td>{$trip['requestedPax']}</td>
    
                        </tr>";
        }
        
        // $email_content .= "
        //             </tbody>
        //         </table>
                
        //         <div class='footer'>
        //             <h3>⚠️ Action Required:</h3>
        //             <ul>
        //                 <li>Please assign vehicles to these trips immediately</li>
        //                 <li>Ensure driver assignments are also completed</li>
        //             </ul>
                    
        //             <p><strong>Time Generated:</strong> " . date('d/m/Y g:i A') . "</p>
        //             <p><strong>System:</strong> Staff Transport Management - Automated Alert</p>
        //         </div>
        //     </div>
        // </body>
        // </html>";
        
        // Email recipients
        $toEmails = "shivani@teaminertia.com"; 
        $ccEmails = ""; // CC recipients
        $bccEmails = ""; // BCC recipients if needed
        
        // Send email using the existing Send_Zohomail function
        $response = Send_Zohomail(
            "Deltin Front Office",           // from name
            "Deltin Front Office",           // from display name
            $toEmails,                       // to emails
            "tktno_reply@deltin.com",       // reply to
            $ccEmails,                       // cc emails
            $bccEmails,                      // bcc emails
            $email_subject,                  // subject
            $email_content,                  // email content
            "",                              // subject_user (for auto-reply)
            "",                              // replystr (auto-reply content)
            "",                              // page
            ""                               // files/attachments
        );
        
        // Log the alert
        $logMessage = "Vehicle assignment alert sent for " . count($unassignedTrips) . " trips. Response: " . $response;
        error_log("[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", 3, "../../logs/vehicle_assignment_alerts.log");
        
        echo "Alert sent successfully for " . count($unassignedTrips) . " unassigned trips.\n";
    } else {
        echo "No unassigned trips found in the next 4 hours.\n";
    }
} else {
    echo "No trips requiring vehicle assignment found.\n";
}

// Optional: Clean up old log files (keep last 30 days)
$logFile = "../../logs/vehicle_assignment_alerts.log";
if (file_exists($logFile) && filesize($logFile) > 5 * 1024 * 1024) { // 5MB limit
    $lines = file($logFile);
    $recentLines = array_slice($lines, -1000); // Keep last 1000 lines
    file_put_contents($logFile, implode('', $recentLines));
}
?>