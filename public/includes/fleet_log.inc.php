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

function &fleetBookingDatState()
{
    static $state = [
        'rows' => [],
        'armed' => false,
    ];
    return $state;
}

function fleetBookingDatTrackedFields()
{
    return [
        ['col' => 'vBookedBy', 'label' => 'booked by'],
        ['col' => 'cBookingFor', 'label' => 'booked for', 'map' => 'booking_for'],
        ['col' => 'iFleet_TrvPurID', 'label' => 'travel purpose', 'lookup' => 'purpose'],
        ['col' => 'iFleet_TrvTypeID', 'label' => 'travel type', 'lookup' => 'travel_type'],
        ['col' => 'iPropertyID', 'label' => 'property', 'lookup' => 'property'],
        ['col' => 'iFleet_BKCatID', 'label' => 'booking category', 'lookup' => 'booking_cat'],
        ['col' => 'vInstructions', 'label' => 'instructions'],
        ['col' => 'vRemarks', 'label' => 'remarks'],
        ['col' => 'vTravelNotes', 'label' => 'travel notes'],
        ['col' => 'vName', 'label' => 'passenger name'],
        ['col' => 'vMobileNo', 'label' => 'mobile number'],
        ['col' => 'iFStaffID', 'label' => 'staff', 'lookup' => 'staff'],
        ['col' => 'iPax', 'label' => 'pax'],
        ['col' => 'iBaggage', 'label' => 'baggage'],
        ['col' => 'vPickUpLocation', 'label' => 'pickup location'],
        ['col' => 'vPickUpTime', 'label' => 'pickup date time', 'kind' => 'datetime'],
        ['col' => 'vDropLocation', 'label' => 'drop location'],
        ['col' => 'vLandmark', 'label' => 'landmark'],
        ['col' => 'iVehicleCatID', 'label' => 'vehicle category', 'lookup' => 'vehicle_cat'],
        ['col' => 'cDisposal', 'label' => 'trip type', 'map' => 'trip_type'],
        ['col' => 'tReturnTime', 'label' => 'return time', 'kind' => 'datetime'],
        ['col' => 'iVendorID', 'label' => 'vendor', 'lookup' => 'vendor'],
        ['col' => 'iFleet_StationID', 'label' => 'station', 'lookup' => 'station'],
    ];
}

function fleetBookingDatScalar($value)
{
    if (is_array($value) || is_object($value)) {
        return '';
    }
    return (string)($value ?? '');
}

function fleetBookingDatStamp($value)
{
    $value = trim(stripslashes(fleetBookingDatScalar($value)));
    if ($value === '' || strpos($value, '0000-00-00') === 0) {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i', $ts) : $value;
}

function fleetBookingDatNormalize($value, $field)
{
    if (($field['kind'] ?? '') === 'datetime') {
        return fleetBookingDatStamp($value);
    }
    if (!empty($field['lookup'])) {
        $id = intval($value);
        return $id > 0 ? (string)$id : '';
    }
    return trim(stripslashes(fleetBookingDatScalar($value)));
}

function queueFleetBookingDatText($bookingId, $userId, $text)
{
    $bookingId = intval($bookingId);
    $text = trim((string)$text);
    if ($bookingId <= 0 || $text === '') {
        return;
    }
    $state = &fleetBookingDatState();
    $state['rows'][] = [
        'bookingId' => $bookingId,
        'userId' => intval($userId),
        'type' => 'text',
        'text' => $text,
    ];
    fleetBookingDatArm();
}

function queueFleetBookingDatChanges($bookingId, $userId, $oldRow, $newRow, $fields)
{
    $bookingId = intval($bookingId);
    if ($bookingId <= 0 || !is_array($fields)) {
        return;
    }
    $state = &fleetBookingDatState();
    foreach ($fields as $field) {
        $col = $field['col'] ?? '';
        if ($col === '') {
            continue;
        }
        $oldVal = is_array($oldRow) ? ($oldRow[$col] ?? '') : '';
        $newVal = is_array($newRow) && array_key_exists($col, $newRow) ? $newRow[$col] : $oldVal;
        if (fleetBookingDatNormalize($oldVal, $field) === fleetBookingDatNormalize($newVal, $field)) {
            continue;
        }
        $state['rows'][] = [
            'bookingId' => $bookingId,
            'userId' => intval($userId),
            'type' => 'change',
            'field' => $field,
            'old' => $oldVal,
            'new' => $newVal,
        ];
    }
    if (!empty($state['rows'])) {
        fleetBookingDatArm();
    }
}

function fleetBookingDatArm()
{
    $state = &fleetBookingDatState();
    if ($state['armed']) {
        return;
    }
    $state['armed'] = true;
    register_shutdown_function('flushFleetBookingDatQueue');
}

function fleetBookingDatReleaseResponse()
{
    static $released = false;
    if ($released) {
        return;
    }
    $released = true;
    ignore_user_abort(true);
    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }

    $chunks = [];
    while (ob_get_level() > 0) {
        $chunks[] = ob_get_clean();
    }
    $body = implode('', array_reverse($chunks));
    if (!headers_sent()) {
        header('Connection: close');
        header('Content-Length: ' . strlen($body));
    }
    echo $body;
    flush();
}

function fleetBookingDatLookupSpec()
{
    return [
        'purpose' => ['fleet_travelpurpose', 'iFleet_TrvPurID', 'vName'],
        'travel_type' => ['fleet_traveltype', 'iFleet_TrvTypeID', 'vName'],
        'property' => ['property', 'iPropertyID', 'vName'],
        'booking_cat' => ['fleet_bookingcategory', 'iFleet_BkCatID', 'vName'],
        'vehicle_cat' => ['vehicle_category', 'iVCatID', 'vName'],
        'vendor' => ['vendor', 'iVendorID', 'vName'],
        'station' => ['fleet_station', 'iFlt_StationID', 'vName'],
        'vehicle' => ['vehicle', 'iVehicleID', 'vRnum'],
        'driver' => ['driver', 'iDriverID', 'vName'],
        'staff' => ['fleet_staff', 'iFStaffID', 'vName'],
        'user' => ['users', 'iUserID', 'vName'],
    ];
}

function fleetBookingDatMaps()
{
    global $FLEET_BOOKING_FOR, $FLEET_TRAVEL_TYPE;
    return [
        'booking_for' => is_array($FLEET_BOOKING_FOR) ? $FLEET_BOOKING_FOR : [],
        'trip_type' => is_array($FLEET_TRAVEL_TYPE) ? $FLEET_TRAVEL_TYPE : [],
    ];
}

function fleetBookingDatNames($rows)
{
    $specs = fleetBookingDatLookupSpec();
    $ids = [];
    foreach ($rows as $row) {
        $userId = intval($row['userId'] ?? 0);
        if ($userId > 0) {
            $ids['user'][$userId] = $userId;
        }
        if (($row['type'] ?? '') !== 'change') {
            continue;
        }
        $lookup = $row['field']['lookup'] ?? '';
        if ($lookup === '' || !isset($specs[$lookup])) {
            continue;
        }
        foreach (['old', 'new'] as $side) {
            $id = intval($row[$side] ?? 0);
            if ($id > 0) {
                $ids[$lookup][$id] = $id;
            }
        }
    }

    $names = [];
    foreach ($ids as $lookup => $idList) {
        if (!isset($specs[$lookup]) || empty($idList)) {
            continue;
        }
        [$table, $idCol, $nameCol] = $specs[$lookup];
        $sql = "SELECT $idCol AS id, $nameCol AS name FROM $table WHERE $idCol IN (" . implode(',', $idList) . ")";
        $res = sql_query($sql, 'FLEET_DAT.NAME');
        while ($found = sql_fetch_assoc($res)) {
            $names[$lookup . ':' . intval($found['id'])] = trim(stripslashes((string)($found['name'] ?? '')));
        }
    }
    return $names;
}

function fleetBookingDatDisplay($value, $field, $names)
{
    if (($field['kind'] ?? '') === 'datetime') {
        $stamp = fleetBookingDatStamp($value);
        return $stamp === '' ? '' : date('d-m-Y H:i', strtotime($stamp));
    }
    if (!empty($field['lookup'])) {
        $id = intval($value);
        if ($id <= 0) {
            return '';
        }
        $found = $names[$field['lookup'] . ':' . $id] ?? '';
        return $found !== '' ? $found : (string)$id;
    }
    $raw = trim(stripslashes(fleetBookingDatScalar($value)));
    if ($raw === '') {
        return '';
    }
    if (!empty($field['map'])) {
        $map = fleetBookingDatMaps()[$field['map']] ?? [];
        if (isset($map[$raw])) {
            return $map[$raw];
        }
        $asInt = (string)intval($raw);
        if (isset($map[$asInt])) {
            return $map[$asInt];
        }
    }
    return $raw;
}

function fleetBookingDatSentence($who, $label, $oldDisplay, $newDisplay)
{
    if ($oldDisplay === '' && $newDisplay !== '') {
        return $who . ' assigned the ' . $label . ' ' . $newDisplay;
    }
    if ($oldDisplay !== '' && $newDisplay === '') {
        return $who . ' removed the ' . $label . ' ' . $oldDisplay;
    }
    return $who . ' changed the ' . $label . ' from ' . $oldDisplay . ' to ' . $newDisplay;
}

function flushFleetBookingDatQueue()
{
    $state = &fleetBookingDatState();
    $rows = $state['rows'];
    $state['rows'] = [];
    if (empty($rows)) {
        return;
    }

    fleetBookingDatReleaseResponse();

    $names = fleetBookingDatNames($rows);
    $dtTime = defined('NOW') ? NOW : date('Y-m-d H:i:s');
    $values = [];
    foreach ($rows as $row) {
        $who = $names['user:' . intval($row['userId'])] ?? '';
        if ($who === '') {
            $who = 'User';
        }
        if (($row['type'] ?? '') === 'text') {
            $desc = $who . ' ' . $row['text'];
        } else {
            $field = $row['field'];
            $oldDisplay = fleetBookingDatDisplay($row['old'], $field, $names);
            $newDisplay = fleetBookingDatDisplay($row['new'], $field, $names);
            if ($oldDisplay === $newDisplay) {
                continue;
            }
            $desc = fleetBookingDatSentence($who, $field['label'], $oldDisplay, $newDisplay);
        }
        $desc = function_exists('mb_substr') ? mb_substr($desc, 0, 1000) : substr($desc, 0, 1000);
        $whoStored = function_exists('mb_substr') ? mb_substr($who, 0, 150) : substr($who, 0, 150);
        $values[] = "(" . intval($row['bookingId']) . ", '" . db_input($dtTime) . "', '" . db_input($whoStored) . "', '" . db_input($desc) . "', 'A')";
    }
    if (empty($values)) {
        return;
    }
    sql_query(
        "INSERT INTO fleet_booking_dat (iFleet_BookingID, dtTime, vByWhom, vDesc, cStatus) VALUES " . implode(',', $values),
        'FLEET_DAT.INS'
    );
}
?>