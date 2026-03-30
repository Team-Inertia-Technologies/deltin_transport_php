<?php
// Test email functionality without database
include "../../includes/_send_mail.php";

// Test data
$unassignedTrips = [
    [
        'tripID' => 123,
        'date' => '30/03/2026',
        'time' => '2:30 PM',
        'routeName' => 'Panaji to Deltin Royale',
        'destination' => 'Deltin Royale Casino',
        'requestedPax' => 8,
        'capacity' => 12
    ],
    [
        'tripID' => 124,
        'date' => '30/03/2026',
        'time' => '3:15 PM',
        'routeName' => 'Margao to Deltin Jaqk',
        'destination' => 'Deltin Jaqk Casino',
        'requestedPax' => 15,
        'capacity' => 20
    ]
];

// Prepare email content
$email_subject = "Vehicle Assignment Required - " . count($unassignedTrips) . " Trip(s) Without Vehicles";

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
    <div class='header'>
        <h2>Vehicle Assignment Required</h2>
    </div>
    
    <div class='content'>
        <p>Dear Front Office Team,</p>
        
        <p class='urgent'>The following " . count($unassignedTrips) . " trip(s) scheduled for the next 4 hours do not have vehicles assigned:</p>
        
        <table class='trip-table'>
            <thead>
                <tr>
                    <th>Trip ID</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Route</th>
                    <th>Destination</th>
                    <th>Passengers</th>
                    <th>Required Capacity</th>
                </tr>
            </thead>
            <tbody>";

foreach ($unassignedTrips as $trip) {
    $email_content .= "
                <tr>
                    <td>{$trip['tripID']}</td>
                    <td>{$trip['date']}</td>
                    <td><strong>{$trip['time']}</strong></td>
                    <td><strong>{$trip['routeName']}</strong></td>
                    <td>{$trip['destination']}</td>
                    <td>{$trip['requestedPax']}</td>
                    <td>{$trip['capacity']}</td>
                </tr>";
}

$email_content .= "
            </tbody>
        </table>
        
        <div class='footer'>
            <h3>⚠️ Action Required:</h3>
            <ul>
                <li>Please assign vehicles to these trips immediately</li>
                <li>Ensure driver assignments are also completed</li>
            </ul>
            
            <p><strong>Time Generated:</strong> " . date('d/m/Y g:i A') . "</p>
            <p><strong>System:</strong> Staff Transport Management - Automated Alert</p>
        </div>
    </div>
</body>
</html>";

// Email recipients
$toEmails = "shivani@teaminertia.com"; 
$ccEmails = ""; 
$bccEmails = ""; 

echo "Attempting to send test email...\n";

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

echo "Email send response: " . $response . "\n";
echo "Test completed.\n";
?>