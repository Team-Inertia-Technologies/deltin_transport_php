<?php
/**
 * Fleet Log Booking Functions
 * Similar to common.master.php but specifically for fleet_log_booking table
 */

function LogFleetBooking($iRefID, $cRefType, $vRefName = '', $vDesc = '', $cMode = 'I', $iUserID = 0, $vUserName = '', $iLocID = 0)
{
    global $_SERVER, $sess_user_id, $sess_user_name, $sess_user_locid;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $NOW = NOW;
    
    // Auto-detect user info if not provided
    if (empty($iUserID) && isset($sess_user_id) && is_numeric($sess_user_id)) {
        $iUserID = $sess_user_id;
        $vUserName = !empty($sess_user_name) ? $sess_user_name : GetXFromYID("SELECT vName from users where iUserID = $iUserID");
        $iLocID = 0;
    }
    
    // Default values if still empty
    if (empty($iUserID)) $iUserID = 0;
    if (empty($vUserName)) $vUserName = 'API User';
    if (empty($iLocID)) $iLocID = 0;
    
    if (empty($vRefName)) {
        if ($cRefType == 'B') { // Booking
            $vRefName = GetXFromYID("SELECT vName FROM fleet_booking WHERE iFleet_BookingID = $iRefID");
        } else if ($cRefType == 'V') { // Vehicle
            $vRefName = GetXFromYID("SELECT vRnum FROM vehicle WHERE iVehicleID = $iRefID");
        } else if ($cRefType == 'D') { // Driver
            $vRefName = GetXFromYID("SELECT vName FROM driver WHERE iDriverID = $iRefID");
        }
    }

    if (empty($vDesc)) {
        switch ($cMode) {
            case 'I':
                $vDesc = 'Newly Created';
                break;
            case 'U':
                $vDesc = 'Updated';
                break;
            case 'A':
                $vDesc = 'Vehicle Allocated';
                break;
            case 'D':
                $vDesc = 'Deleted';
                break;
            default:
                $vDesc = 'Action Performed';
                break;
        }
    }
    
    // Get next ID for fleet_log_booking
    $iLMID = NextID('iLMID', 'fleet_log_booking');
    
    // Insert log entry
    $logSql = "INSERT INTO fleet_log_booking 
               (iLMID, iLocID, iUserID, vUserName, dtDate, iRefID, cRefType, vRefName, vDesc, cMode, vIP, cStatus) 
               VALUES 
               ($iLMID, $iLocID, $iUserID, '" . db_input($vUserName) . "', '$NOW', $iRefID, '$cRefType', '" . db_input($vRefName) . "', '" . db_input($vDesc) . "', '$cMode', '$ip', 'A')";
    
    $result = sql_query($logSql, 'FLEET_LOG.001');
    
    return $result ? $iLMID : false;
}

/**
 * Log booking creation
 */
function LogBookingCreated($iBookingID, $vPassengerName = '', $iUserID = 0, $vUserName = '', $iLocID = 0)
{
    return LogFleetBooking($iBookingID, 'B', $vPassengerName, 'Booking created', 'I', $iUserID, $vUserName, $iLocID);
}

/**
 * Log booking update
 */
function LogBookingUpdated($iBookingID, $vPassengerName = '', $vDesc = '', $iUserID = 0, $vUserName = '', $iLocID = 0)
{
    if (empty($vDesc)) {
        $vDesc = 'Booking updated';
    }
    return LogFleetBooking($iBookingID, 'B', $vPassengerName, $vDesc, 'U', $iUserID, $vUserName, $iLocID);
}

/**
 * Log vehicle allocation to booking
 */
function LogVehicleAllocated($iBookingID, $iVehicleID = 0, $iDriverID = 0, $vPassengerName = '', $iUserID = 0, $vUserName = '', $iLocID = 0)
{
    // Build description with vehicle and driver details
    $vDesc = 'Vehicle allocated';
    
    if ($iVehicleID > 0) {
        $vehicleReg = GetXFromYID("SELECT vRnum FROM vehicle WHERE iVehicleID = $iVehicleID");
        if (!empty($vehicleReg)) {
            $vDesc .= ' - Vehicle: ' . $vehicleReg;
        }
    }
    
    if ($iDriverID > 0) {
        $driverName = GetXFromYID("SELECT vName FROM driver WHERE iDriverID = $iDriverID");
        if (!empty($driverName)) {
            $vDesc .= ' - Driver: ' . $driverName;
        }
    }
    
    return LogFleetBooking($iBookingID, 'B', $vPassengerName, $vDesc, 'A', $iUserID, $vUserName, $iLocID);
}

/**
 * Log booking deletion
 */
function LogBookingDeleted($iBookingID, $vPassengerName = '', $iUserID = 0, $vUserName = '', $iLocID = 0)
{
    return LogFleetBooking($iBookingID, 'B', $vPassengerName, 'Booking deleted', 'D', $iUserID, $vUserName, $iLocID);
}
?>