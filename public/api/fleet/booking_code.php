<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../../includes/common_api.php";
date_default_timezone_set('Asia/Calcutta');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Expires: " . gmdate("D, d M Y H:i:s", 1) . " GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$postdata = file_get_contents("php://input");
$request  = json_decode($postdata, true);
$_REQUEST = array_merge($_REQUEST, $request ?? []);

$mode       = $_REQUEST['mode'] ?? '';
$token      = trim($_REQUEST['token'] ?? '');
$booking_id = intval($_REQUEST['id'] ?? 0);
$code       = db_input(trim($_REQUEST['code'] ?? ''));

switch ($mode) {

    // ===================== CASE: VERIFY_BOOKING_CODE =====================
    case 'VERIFY_BOOKING_CODE':

        if (!$token || !$booking_id) {
            http_response_code(400);
            echo json_encode([
                "statusCode" => 400,
                "error" => [
                    "message" => "Missing token or booking id."
                ]
            ]);
            exit;
        }

        $user_id = intval(DecodeParam($token));

        // -------------------- VERIFY VENDOR USER --------------------
        $vendorRes = sql_query(
            "SELECT iRefID FROM users WHERE iUserID = $user_id AND cRefSrcType = 'V' AND cStatus = 'A' LIMIT 1",
            'AUTH.VENDOR'
        );

        if (!sql_num_rows($vendorRes)) {
            http_response_code(401);
            echo json_encode([
                "statusCode" => 401,
                "error" => [
                    "message" => "Invalid token or not a vendor user."
                ]
            ]);
            exit;
        }

        $vendorRow = sql_fetch_assoc($vendorRes);
        $vendorID  = intval($vendorRow['iRefID']);

        // -------------------- VERIFY BOOKING BELONGS TO VENDOR --------------------
        $bookingRes = sql_query(
            "SELECT iDriverID, vBookingCode, iVendorID FROM fleet_booking
             WHERE iFleet_BookingID = $booking_id AND cStatus = 'A' LIMIT 1",
            'BOOKING.DETAILS'
        );

        if (!sql_num_rows($bookingRes)) {
            http_response_code(404);
            echo json_encode([
                "statusCode" => 404,
                "error" => [
                    "message" => "Booking not found."
                ]
            ]);
            exit;
        }

        $booking  = sql_fetch_assoc($bookingRes);
        $driverID = intval($booking['iDriverID']);

        if ($vendorID > 0 && intval($booking['iVendorID']) !== $vendorID) {
            http_response_code(403);
            echo json_encode([
                "statusCode" => 403,
                "error" => [
                    "message" => "This booking does not belong to your fleet."
                ]
            ]);
            exit;
        }

        // -------------------- VERIFY BOOKING CODE FOR THIS TRIP --------------------
        if ($code === '') {
            http_response_code(400);
            echo json_encode([
                "statusCode" => 400,
                "error" => [
                    "message" => "Booking code is required."
                ]
            ]);
            exit;
        }

        $codeRes = sql_query(
            "SELECT iFleet_BookingID FROM fleet_booking
             WHERE iFleet_BookingID = $booking_id AND iDriverID = $driverID
               AND vBookingCode = '$code' AND cStatus = 'A' LIMIT 1",
            'CHECK.CODE'
        );

        if (!sql_num_rows($codeRes)) {
            http_response_code(400);
            echo json_encode([
                "statusCode" => 400,
                "error" => [
                    "message" => "Invalid booking code for this trip."
                ]
            ]);
            exit;
        }

        $NOW = NOW;

        // -------------------- MARK BOOKING CODE AS ENTERED BY VENDOR --------------------
        sql_query(
            "UPDATE fleet_booking SET cBookingCodeEnteredBy = 'V' WHERE iFleet_BookingID = '$booking_id'",
            'BOOKING.CODE.BY'
        );

        // -------------------- COMPLETE THE TRIP --------------------
        $log_id = NextID('iLogID', 'fleet_booking_log');
        $query  = "UPDATE fleet_booking SET cType='C', vDropTime = '$NOW' WHERE iFleet_BookingID='$booking_id' AND iDriverID='$driverID'";
        sql_query("INSERT INTO fleet_booking_log (iLogID, iFleet_BookingID, cRefType, vRefName, dtAdded, iUserID, cStatus) VALUES ($log_id, '$booking_id', 'C', 'Trip Compeleted', '$NOW', '$driverID', 'A')", 'TRIP.LOG');
        $result = sql_query($query, 'TRIP.COMPLETE');

        if (sql_affected_rows() > 0) {
            http_response_code(200);
            echo json_encode([
                "statusCode" => 200,
                "data" => [
                     "message" => "Trip completed successfully."
                ],
               
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                "statusCode" => 400,
                "error" => [
                    "message" => "Failed to complete trip. Please check booking ID and driver ID."
                ]
            ]);
        }
        break;

    // ===================== CASE: MARK_TRIP_COMPLETE =====================
    case 'MARK_TRIP_COMPLETE':

        $booking_ids = $_REQUEST['id'] ?? [];
        if (!is_array($booking_ids)) {
            $booking_ids = [$booking_ids];
        }
        $booking_ids = array_values(array_filter(array_map('intval', $booking_ids)));

        if (!$token || empty($booking_ids)) {
            http_response_code(400);
            echo json_encode([
                "statusCode" => 400,
                "error" => [
                    "message" => "Missing token or booking id."
                ]
            ]);
            exit;
        }

        $user_id = intval(DecodeParam($token));

        // -------------------- VERIFY VENDOR USER --------------------
        $vendorRes = sql_query(
            "SELECT iRefID FROM users WHERE iUserID = $user_id AND cRefSrcType = 'V' AND cStatus = 'A' LIMIT 1",
            'AUTH.VENDOR'
        );

        if (!sql_num_rows($vendorRes)) {
            http_response_code(401);
            echo json_encode([
                "statusCode" => 401,
                "error" => [
                    "message" => "Invalid token or not a vendor user."
                ]
            ]);
            exit;
        }

        $vendorRow = sql_fetch_assoc($vendorRes);
        $vendorID  = intval($vendorRow['iRefID']);

        $NOW      = NOW;
        $success  = [];
        $failed   = [];

        foreach ($booking_ids as $booking_id) {

            // -------------------- VERIFY BOOKING BELONGS TO VENDOR --------------------
            $bookingRes = sql_query(
                "SELECT iDriverID, iVendorID FROM fleet_booking
                 WHERE iFleet_BookingID = $booking_id AND cStatus = 'A' LIMIT 1",
                'BOOKING.DETAILS'
            );

            if (!sql_num_rows($bookingRes)) {
                $failed[] = [
                    "id" => $booking_id,
                    "message" => "Booking not found."
                ];
                continue;
            }

            $booking  = sql_fetch_assoc($bookingRes);
            $driverID = intval($booking['iDriverID']);

            if ($vendorID > 0 && intval($booking['iVendorID']) !== $vendorID) {
                $failed[] = [
                    "id" => $booking_id,
                    "message" => "This booking does not belong to your fleet."
                ];
                continue;
            }

            // -------------------- COMPLETE THE TRIP --------------------
            $log_id = NextID('iLogID', 'fleet_booking_log');
            $query  = "UPDATE fleet_booking SET cType='C', vDropTime = '$NOW' WHERE iFleet_BookingID='$booking_id' AND iDriverID='$driverID'";
            sql_query("INSERT INTO fleet_booking_log (iLogID, iFleet_BookingID, cRefType, vRefName, dtAdded, iUserID, cStatus) VALUES ($log_id, '$booking_id', 'C', 'Trip Compeleted', '$NOW', '$driverID', 'A')", 'TRIP.LOG');
            sql_query($query, 'TRIP.COMPLETE');

            if (sql_affected_rows() > 0) {
                $success[] = $booking_id;
            } else {
                $failed[] = [
                    "id" => $booking_id,
                    "message" => "Failed to complete trip."
                ];
            }
        }

        if (count($success) > 0) {
            http_response_code(200);
            echo json_encode([
                "statusCode" => 200,
                "data" => [
                    "message" => "Trip(s) completed successfully.",
                    "completed" => $success,
                    "failed" => $failed
                ]
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                "statusCode" => 400,
                "error" => [
                    "message" => "Failed to complete trip(s).",
                    "failed" => $failed
                ]
            ]);
        }
        break;

    // ===================== DEFAULT =====================
    default:
        http_response_code(400);
        echo json_encode([
            "statusCode" => 400,
            "error" => [
                "message" => "Invalid or missing mode."
            ]
        ]);
        exit;
}
