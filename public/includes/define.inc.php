<?php
##	DEFINES 		###################################################################################
define('TODAY', date("Y-m-d"));
define('NOW', date("Y-m-d H:i:s"));
define('TOMMORROW', date("Y-m-d", strtotime("+1 day")));
define('YESTERDAY', date("Y-m-d", strtotime("-1 day")));
define('CURRENTTIME', date("H:i:s"));

define('TODAY2', date("d-m-Y"));
define('NOW2', date("d-m-Y H:i:00"));
define('TOMMORROW2', date("d-m-Y", strtotime("+1 day")));
define('YESTERDAY2', date("d-m-Y", strtotime("-1 day")));
define('LAST7DAYS', date("Y-m-d", strtotime("-7 day")));

define('NOW3', date("Ymd.Hi"));
// define('PAYROLL_PROCESS_START', '2014-03');
define('THIS_WEEK', date("W"));
define('THIS_MONTH', date("m"));
define('THIS_YEAR', date("Y"));

define('LAST_WEEK', date("W", strtotime("-1 week")));
define('LAST_MONTH', date("m", strtotime("-1 month")));
define('LAST_YEAR', date("Y", strtotime("-1 year")));

define('CURRENT_MONTH', date("m"));
define('CURRENT_YEAR', date("Y"));

define('FINANCIAL_YEAR_STARTDAY', '04-01');
define('FINANCIAL_YEAR_ENDDAY', '03-31');

define('MONTH_START', date("Y-m-01"));
define('MONTH_START2', date("01-m-Y"));

define('MONTH_YEAR', date("mY"));
define('TODAY1', date("Ymd"));

define('URL_REWRITTING', 'OFF');
define('PROJ_DELIMITER', '[DCC_BREAK]');
$STARTOFMONTH = "01-" . THIS_MONTH . "-" . THIS_YEAR;

// define('SEND_MAILER', 0);
define('OFFICIAL_EMAILID', 'support@deltin.com');
define('PROJ_SESSION_ID', 'DELT_APP');
define('PROJ_FRONT_SESSION_ID', 'DELT_APP_FRONT');
define('PROJ_RM_SESSION_ID', 'DELT_APP_RM');
define('PROJ_AUTHORISE_SESSION_ID', 'DELT_APP_AUTH');
define('PROJ_ALERT_SESSION_ID', 'DELT_APP_ALERT');
define('THUMBNAIL_ALLOWED', 1);	// 1 - Yes, 0 - No.
define('RANDOMIZE_FILENAME', 1); // 0 - Randomize Uploaded Image Name, 1 - Customize Uploaded Image Name
define('SQL_ERROR', 1);
define('NEWLINE', "\r\n");
define('TAB_SPACE', "\t");
define('FORCE_PRINT_DOWNLOAD', 1); // default is 0
define('IS_WAMP_SETUP', 1);
define('WEEK_START_DAY', 1); // 0: Sunday, 1: Monday...
define('QTR_START_MONTH', 1); // Jan
define('QTR_MONTH_OFFSET', 0); // Jan
define('ADD_SLASHES', 0);
define('NA', '- n/a -');
define('IS_INTERNET', false);
define('CONCIERGE_URL', 'https://concierge.deltin.com/');
define('CONCIERGE_STAGE_URL', 'https://concierge.stage.deltin.com/');

##	PATH DEFINES	###################################################################################
define('AJAX_INC_URL', SITE_ADDRESS . 'includes/ajax.inc.php');

define('IMAGE_PATH', SITE_ADDRESS . 'images/');
define('IMAGE_UPLOAD', DOCROOT . 'images/');

define("EVENT_SITE_ADDRESS", "https://www.deltin.com/");
define("EVENT_SITE_IMG_PATH", EVENT_SITE_ADDRESS . "assets/events/");

/**
 * Uploads at "{DOCROOT|SITE_ADDRESS}/uploads/..." or Object Storage
 * 
 * Use functions:
 * - StoreFileUpload(...)
 * - StoreFileExist(...)
 * - StoreFilePath(...)
 * - StoreFileDelete(...)
 * - StoreFileCopy(...)
 **/
define('UPLOAD_TYPE', array(
	'USER' => 'users/',
	'PROPERTY_PIC' => 'property/',
	'SLIDER' => 'slider/',
	'INVOICE' => 'invoice/',
	'RM' => 'rm/',
	'RM_SLIDER' => 'rm_slider/',
	'MEMBER_LEVEL' => 'memberLevel/',
	'OFFERS' => 'offers/',
	'CUSTOMER_PIC' => 'customer_pic/',
	'EVENT_LIST_PIC' => 'event_list_pic/',
	'EVENT_BANNER_PIC' => 'event_banner_pic/',
	'PROPERTY_IMAGES' => 'property_images/',
	'SPLASH_SLIDER' => 'splash_slider/',
	'FLIGHT_REQUEST' => 'flight_request/',
	'HOTEL_REQUEST' => 'hotel_request/',
	'CAB_REQUEST' => 'cab_request/',
	'NOTIFICATION_SMALL' => 'notification_small',
	'NOTIFICATION_BIG' => 'notification_big',
	'CHAT_ATTACHMENTS' => 'chat_attachments/',
	'ADDRESS_PROOF_FRONT' => 'address_proof_front/',
	'ADDRESS_PROOF_BACK' => 'address_proof_back/',
	'PROPERTY_LOGO' => 'property_logo/',
	'VENDOR_CAT' => 'vendor_cat/',
	'VENDOR' => 'vendor/',
	'DL_OFFERS' => 'dl_offers/',
	'DL_CAMPAIGN_FORGROUND' => 'dl_campaign_forground/',
	'DL_CAMPAIGN_IMAGE' => 'dl_campaign_image/',
	'DL_CAMPAIGN_INTROMEDIA' => 'dl_campaign_intromedia/',
));


##	IMAGE DEFINES	###################################################################################
define("FEATURED_IMG", "<img src='" . IMAGE_PATH . "featured.gif' border='0' alt='featured' align='absmiddle'>");
define("UNFEATURED_IMG", "<img src='" . IMAGE_PATH . "unfeatured.gif' border='0' align='absmiddle'>");

define("NOIMAGE", IMAGE_PATH . "/no-image.png");

define("ACTIVE_IMG", "<img src='" . IMAGE_PATH . "active.png'  alt='Active' border='0' align='absmiddle'>");
define("INACTIVE_IMG", "<img src='" . IMAGE_PATH . "inactive.png' alt='Blocked' border='0' align='absmiddle'>");
define("STARRED_IMG", "<img src='" . IMAGE_PATH . "star.png'  alt='Starred' border='0' align='absmiddle'>");
define("UNSTARRED_IMG", "<img src='" . IMAGE_PATH . "not-star.png' alt='UnStarred' border='0' align='absmiddle'>");
define("YES_IMG", "<img src='" . IMAGE_PATH . "yes-ico.gif'  alt='Yes' border='0' align='absmiddle'>");
define("NO_IMG", "<img src='" . IMAGE_PATH . "no-ico.gif' alt='No' border='0' align='absmiddle'>");
// Construct OBJECT_STORAGE_PATH based on SET_STORAGE configuration
if (SET_STORAGE == "1") { //Amazon
	define("OBJECT_STORAGE_PATH", AWS_S3_CDN_ENDPOINT . '/' . AWS_S3_BUCKET . '/' . AWS_S3_DIR);
} else if (SET_STORAGE == "2") { //digital ocean
	define("OBJECT_STORAGE_PATH", AWS_S3_CDN_ENDPOINT . '/' . AWS_S3_DIR);
} else {
	define("OBJECT_STORAGE_PATH", AWS_S3_CDN_ENDPOINT . '/' . AWS_S3_BUCKET . '/' . AWS_S3_DIR);
}

##	NO IMAGE DEFINES	###################################################################################
define('NO_PHOTO_SML', '<img src="images/avatar.png" alt="" class="radius2" />');
##	DEFINED ARRAYs	###################################################################################
$IMG_TYPE = array('gif', 'png', 'pjpeg', 'jpeg', 'jpg', 'JPG');

$DOC_TYPE = array('txt', 'doc', 'docx', 'pdf', 'xls', 'xlsx');

$IMG_FILE_TYPE = array('image/gif', 'image/png', 'image/pjpeg', 'image/jpeg', 'image/jpg');

$DOC_FILE_TYPE = array('text/plain', 'application/msword', 'application/vnd.ms-word', 'application/pdf', 'application/vnd.ms-excel');

$DISPLAY_ARR = array("Y" => "Yes", "N" => "No");

$MODE_ARR = array('A' => 'Add', 'E' => 'Edit');

$WEEKDAY_ARR = array('0' => 'Sunday', '1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday');

$WEEKDAY_ARR2 = array('SUN' => 'Sunday', 'MON' => 'Monday', 'TUE' => 'Tuesday', 'WED' => 'Wednesday', 'THU' => 'Thursday', 'FRI' => 'Friday', 'SAT' => 'Saturday');

$WEEKDAY_ORDER_ARR = array("'SUN'", "'MON'", "'TUE'", "'WED'", "'THU'", "'FRI'", "'SAT'");

$WEEKDAY_ARR3 = array('0' => 'SUN', '1' => 'MON', '2' => 'TUE', '3' => 'WED', '4' => 'THU', '5' => 'FRI', '6' => 'SAT');

$MONTH_ARR = array("1" => "January", "2" => "February", "3" => "March", "4" => "April", "5" => "May", "6" => "June", "7" => "July", "8" => "August", "9" => "September", "10" => "October", "11" => "November", "12" => "December");

$SHORT_MONTH_ARR = array("1" => "Jan", "2" => "Feb", "3" => "Mar", "4" => "Apr", "5" => "May", "6" => "Jun", "7" => "Jul", "8" => "Aug", "9" => "Sep", "10" => "Oct", "11" => "Nov", "12" => "Dec");

$PAYMENT_STATUS_ARR = array('N' => 'Not Paid', 'P' => 'Paid');

$USER_LEVEL_ARR = array('0' => 'Super Admin', '1' => 'Admin', '2' => 'HOD', '3' => 'National Head', '4' => 'Loyalty Coordinator', '5' => 'VIP Services', '6' => 'Jr. RM', '7' => 'Members', '8' => 'Sr. RM', '9' => 'Caller', '10' => 'Campaign Manager', '11' => 'Supervisor', '12' => 'Staff', '13' => 'Delights');

$YES_ARR = array('Y' => 'Yes', 'N' => 'No');

$YES_ARR2 = array('Y' => YES_IMG, 'N' => NO_IMG);

$STATUS_ARR = array("A" => "Active", "I" => "Inactive"); //, "P"=>"Pending", "X"=>"Ended/Cancelled"

$STATUS_RM_ARR = array("A" => "Active", "I" => "Inactive", "R" => "Resign");

$STATUS_CLASS_ARR = array("A" => "success", "I" => "danger", "P" => "warning", "X" => "secondary");

$PERIOD_ARR = array("M" => "Month", "Y" => "Year");

$GENDER_ARR = array("M" => "Male", "F" => "Female", "O" => "Others");

$GENDER_ARR2 = array("M" => "pe-7s-male icon-gradient bg-malibu-beach", "F" => "pe-7s-female icon-gradient bg-warm-flame", "O" => "pe-7s-female icon-gradient bg-warm-flame");

$MARITAL_STATUS_ARR = array("1" => "Single", "2" => "Married", "3" => "Widowed", "4" => "Divorced");

$FOOD_PREF_ARR = array("1" => "Non-veg", "2" => "Veg", "3" => "Jain", "4" => "Vegan");

$DISTRICT_ARR = array('1' => 'North Goa', '2' => 'South Goa');

$TALUKA_ARR = array('1' => 'North Goa', '2' => 'South Goa');

##	DEFINED ERROR MSGS	###################################################################################

define('NO_RECORDS_IN_TABLE', 'No Data Records Found In Table');
define('READONLY_ACCESS', '<div class="err_lbl1" align="center">You Can No Longer Add/ Modify Records For This Module Locally. Inorder To Do So, You Need To Login To The Online Module.</div>');
define('INVALID_ACCESS', 'Invalid Access Detected. Script Terminated.');
define('MODULE_ACCESS_DENIED', 'Invalid Access: You Do Not Have The Necessary Permissions To View This Module');
define('MODULE_EDIT_DENIED', 'Invalid Access: You Do Not Have The Necessary Permissions To Edit This Process');
define('INVALID_PARAMETER', 'Invalid Parameter Detected. Script Terminated.');

#######################################################################################################
$FULL_MONTH_ARR = array("01" => "January", "02" => "February", "03" => "March", "04" => "April", "05" => "May", "06" => "June", "07" => "July", "08" => "August", "09" => "September", "10" => "October", "11" => "November", "12" => "December");

$DURATIONTYPE_ARR = array('W' => 'Weeks', 'M' => 'Months');

$TIME_DURATION_ARR = array('S' => 'Seconds', 'M' => 'Minutes');

$GRAPH_COLOR_ARR = array('1' => 'red', '2' => 'orange', '3' => 'yellow', '4' => 'green', '5' => 'blue', '6' => 'purple', '7' => 'grey', '8' => 'black');

$ENC_CHARARR = array('1' => 'r', '2' => 'j', '3' => 'e', '4' => 'a', '5' => 'c', '6' => 'y', '7' => 'p', '8' => 'o', '9' => 'z', '0' => 'x');

$USER_REF_TYPE = array('A' => 'Admin', 'R' => 'Relationship Manager');

$HEADER_CSS = array('A' => '  header-text-light');
$SIDEBAR_CSS = array('A2' => ' bg-danger sidebar-text-light');
$HEADER_HEADING_ARR = array('A' => 'Administrator');
$HEADER_ICON_ARR = array('A' => 'fa-gavel');

$RATING_ARR = array('5' => '5 Star', '4' => '4 Star', '3' => '3 Star', '2' => '2 Star', '1' => '1 Star');

$REQUEST_STATUS_ARR = array('D' => 'New', 'H' => 'Hold', 'B' => 'Booked', 'C' => 'Cancelled', 'A' => 'Approved', 'R' => 'Rescheduled', 'Z' => 'Archived', 'S' => 'Confirmed', 'E' => 'Expired', 'V' => 'Availed');

$REQUESTSTATUS_COLOR_ARR = array('D' => 'primary', 'I' => 'alternate', 'A' => 'warning', 'B' => 'success', 'C' => 'danger', 'X' => 'light', 'Z' => 'dark');

$STATUS_COLOR_ARR = array('primary', 'secondary', 'success', 'info', 'warning', 'danger', 'focus', 'alternate', 'light', 'dark');

$RM_STATUS_ARR = array("PEND" => "PENDING", "CONF" => "CONFIRM", "NOSH" => "NO SHOW", "INVO" => "INVOICED", "CHAN" => "CHANGE", "INPR" => "IN PROCESS");

$BACKGROUND_CSS_ARR = array('bg-warm-flame', 'bg-night-fade', 'bg-sunny-morning', 'bg-tempting-azure', 'bg-amy-crisp', 'bg-heavy-rain', 'bg-mean-fruit', 'bg-malibu-beach', 'bg-deep-blue', 'bg-ripe-malin', 'bg-arielle-smile', 'bg-plum-plate', 'bg-happy-fisher', 'bg-happy-itmeo', 'bg-mixed-hopes', 'bg-strong-bliss', 'bg-grow-early', 'bg-love-kiss', 'bg-premium-dark', 'bg-happy-green', 'bg-vicious-stance', 'bg-midnight-bloom', 'bg-night-sky', 'bg-slick-carbon', 'bg-royal', 'bg-asteroid', 'bg-transparent');

$MIS_APPROVED_DASHBOARD_ARR = array('D' => 'New', 'I' => 'In-Process', 'A' => 'Confirmed', 'B' => 'Booked');

$MIS_DASHBOARD_CSS_ARR = array('D' => 'bg-warm-flame', 'I' => 'bg-night-fade', 'A' => 'bg-vicious-stance', 'B' => 'bg-tempting-azure');

$MIS_DASHBOARD_LINK_ARR = array('D' => 'requests_disp.php?srch_mode=QUERY&status=D', 'I' => 'requests_disp.php?srch_mode=QUERY&status=I', 'A' => 'requests_disp.php?srch_mode=QUERY&status=A', 'B' => 'bookings_disp.php');

$OCCUPANCY_ARR = array('S' => 'Single', 'D' => 'Double', 'T' => 'Triple');
$OCCUPANCY_ARR2 = array('S' => '1', 'D' => '2', 'T' => '3');
$OCCUPANCY_ARR3 = array('1' => 'S', '2' => 'D', '3' => 'T');

$REQ_TYPE_ARR = array('CS', 'RQ', 'RD', 'RG', 'RO', 'RR');
$BOOK_TYPE_ARR = array('BO', 'BD', 'BG', 'BI', 'BR');

$BOOKING_STATUS_ARR = array('A' => 'Confirmed', 'C' => 'Cancelled');
$BOOKING_STATUS_ARR2 = array('A' => 'Confirmed', 'C' => 'Cancelled', 'I' => 'Invoiced', 'X' => 'Closed');
$BOOKINGSTATUS_COLOR_ARR = array('A' => 'warning', 'C' => 'danger', 'I' => 'success', 'X' => 'secondary');

$HOTELENQUIRY_STATUS_ARR = array('NA' => 'No Actions Taken', 'RS' => 'Request Sent', 'AV' => 'Available', 'NAV' => 'Not Available', 'RMC' => 'RM Confirmed', 'HC' => 'Hotel Confirmed', 'BK' => 'Booked');
$HOTELENQUIRY_COLOR_ARR = array('NA' => 'alternate', 'RS' => 'secondary', 'AV' => 'dark', 'NAV' => 'danger', 'RMC' => 'warning', 'HC' => 'focus', 'BK' => 'success');

$CASINO_PLAY_STATUS_ARR = array("N" => "No Show", "P" => "Played", "I" => "Not Played");
$CASINO_PLAY_COLOR_ARR = array("N" => "#f52e2e", "P" => "#1c94f3", "I" => "#ffb300");
$CASINO_PLAY_COLOR_ARR2 = array("N" => "danger", "P" => "success", "I" => "warning");

$CM_STATUS_ARR = array('R' => 'Sent Back', 'A' => 'Approved', 'P' => 'Pending', 'C' => 'Closed');

$INVOICE_STATUS_ARR = array('D' => 'New', 'I' => 'Rejected by Concierge', 'R' => 'Rejected By Casino Manager', 'P' => 'In Process', 'A' => 'Approved by Casino Manager', 'C' => 'Closed');
$INVOICE_STATUS_ARR2 = array('D' => 'New', 'I' => 'Rejected', 'R' => 'Rejected', 'P' => 'In Process', 'A' => 'Approved', 'C' => 'Closed');
$INVOICE_STATUS_COLOR_ARR = array('D' => 'alternate', 'I' => 'warning', 'R' => 'danger', 'P' => 'dark', 'A' => 'success', 'C' => 'focus');
$HOTEL_INVOICE_STATUS_ARR = array('D' => 'New', 'I' => 'Rejected by Concierge', 'R' => 'In Process', 'P' => 'In Process', 'A' => 'In Process', 'C' => 'Closed');
$HOTEL_INVOICE_STATUS_COLOR_ARR = array('D' => 'secondary', 'I' => 'danger', 'P' => 'warning', 'R' => 'warning', 'C' => 'success');

$ACCOUNT_STATUS_ARR = array('A' => 'Approved', 'C' => 'Closed');

$AGGREGATOR_ARR = array('1' => 'MakeMyTrip', '2' => 'Booking.com');

$PAYMENT_MODE_ARR = array('CP' => 'Cash Payment', 'NB' => 'Net Banking');

$REQ_TYPE_ARR2 = array("'CS'", "'RQ'", "'RD'", "'RG'", "'RO'", "'RR'");
$BOOK_TYPE_ARR2 = array("'BO'", "'BD'", "'BG'", "'BI'", "'BR'");

$MEMBERSHIP_LEVEL = array('1' => 'BLUE', '2' => 'BLACK', '3' => 'GOLD');
$MEMBERSHIP_TYPE = array('1' => 'Deltin Select', '2' => 'Club Deltin');

$FLIGHT_TYPE = array(array('id' => '1', 'name' => 'One way'), array('id' => '2', 'name' => 'Round trip'));
$FLIGHT_TYPE_ARR = array('1' => 'One way', '2' => 'Round trip');

$REQ_TYPE_DESC_ARR = array('CS', 'RQ' => 'Request', 'RD' => 'Room Type', 'RG' => 'Guest Details', 'RO' => 'Room Type', 'RR' => 'Room Details');
$BOOK_TYPE_DESC_ARR = array('BO' => 'Booking', 'BD' => 'Room Type', 'BG' => 'Guest Details', 'BI', 'BR' => 'Room Details');

$BENEFITS_STATUS_ARR = array('I' => 'Not Availed', 'A' => 'Availed');
$BENEFITS_STATUS_COLOR_ARR = array('I' => '#B3B3B3', 'A' => '#3FDE6A');

$CANCEL_REASONS = array('1' => 'I am not travelling', '2' => 'My trip was cancelled');
$DEFAULT_RM_STATUS_TXT_ARR = array('Available', 'Off Duty', 'In a meeting', 'Out sick', 'On vacation', 'Commuting');
$RM_STATUS_TXT_COLOR_ARR = array('available' => '#3BCB08', 'off duty' => '#F54343');
$OCCUPANCY_ARR = array('S' => 'Single', 'D' => 'Double', 'T' => 'Triple');

$MESSAGE_TYPE_ARR = array('T' => 'Text', 'I' => 'Image', 'D' => 'Document');
$RM_LEVEL_ARR = array('1' => 'Junior', '2' => 'Senior');
$SLIDER_TYPE_ARR = array('I' => 'Internal', 'E' => 'External');

$PACKAGE_TYPE_ARR = array("A" => "Adults", "K" => "Kids", "C" => "Couples");
$PACKAGE_TYPE_ARR2 = array("GEN" => "General", "COD" => "COD", "PTN" => "Partner", "CTM" => "Custom");
$EVENT_ARR = array('E' => 'Event', "F" => 'Flight Request', "H" => "Hotel Request", "C" => "Cab Request", "P" => "Profile Screen", "N" => "Notifications", "M" => "Message", "R" => "My Request", "L" => "Chip Locker", "V" => "Voucher");
$EVENT_ARR_VAL = array("F" => "FlightScreen", "H" => "HotelScreen", "C" => "CabScreen", "P" => "ProfileScreen", "N" => "NotificationScreen", "M" => "MessageScreen", "R" => "My Requests", "L" => "Chip Locker");
$MEMBERSHIP_STATUS_ARR = array('A' => 'Active', 'I' => 'Inactive', 'W' => 'Withdrawn', 'S' => 'Stand-by', 'P' => 'Alert', 'B' => 'Barred', 'E' => 'Expired', 'R' => 'Redeemed', 'V' => 'Valid', 'N' => 'Not Valid');
$MEMBERSHIP_STATUS_TEXT_ARR = array('Active' => 'A', 'Inactive' => 'I', 'Withdrawn' => 'W', 'Stand By' => 'S', 'Alert' => 'P', 'Barred' => 'B', 'Expired' => 'E', 'Redeemed' => 'R', 'Valid' => 'V', 'Not Valid' => 'N');
const STATUS_COLORS = [
	'D' => 'Orange',
	'H' => 'blue',
	'A' => 'lightgreen',
	'B' => 'darkgreen',
	'C' => 'red',
	'Z' => 'purple',
	'E' => 'grey',
	'V' => 'green',

];
$BOOKING_TYPE_ARR = array('WALK' => 'Walk IN', 'COD' => 'COD', 'PRE' => 'Pre Purchase', 'GRP' => 'Group');
$EMAIL_TYPE_ARR = array('To' => 'To', 'CC' => 'CC', 'BCC' => 'BCC');
$PHONE_TYPE_OPTIONS = ['SMS' => 'SMS', 'WA' => 'WhatsApp'];
$DOW_ARR = array("0" => "Sunday", "1" => "Monday", "2" => "Tuesday", "3" => "Wednesday", "4" => "Thursday", "5" => "Friday", "6" => "Saturday");
$PACKAGE_TYPE_ARR = array("A" => "Adults", "K" => "Kids", "C" => "Couples");
//$PACKAGE_TYPE_ARR2 = array("GEN" => "General", "COD" => "COD", "PTN" => "Partner", "CTM" => "Custom");
$PACKAGE_TYPE_ARR2 = array("MEM" => "Member", "GST" => "Guest");
$KYC_VERIFY_TYPE_ARR = array('1' => 'pan', '2' => 'adh', '3' => 'dl', '4' => 'pp', '5' => 'vtr', '6' => ''/* 'fpp' */, '7' => ''/* 'vsa' */, '8' => ''/* 'ctz' */);
$KYC_VERIFY_TYPE_FLIP_ARR = array_flip(array_filter($KYC_VERIFY_TYPE_ARR));
$DL_INTROMEDIA_TYPE = array("I" => "Image", "V" => "Video", "H" => "HTML Page");
$DL_CAMPAIGN_FLOWTYPE = array("2" => "2 Step", "3" => "3 Step");
$BENEFICIARY_STAGE_ARR = array("P" => "Pending", "N" => "Notified", "V" => "Viewed", "A" => "Availed");

$SERVICE_OFFERED= array("F" => "Fleet", "B" => "Bus", "T" => "Both");

$VEHICLE_DRIVER_TYPE= array("1" => "Hired", "2" => "Contract" , "3"=>"Owned");
$FLEET_BOOKING_FOR= array("S" => "Staff", "G" => "Guest" );
$FLEET_TRAVEL_TYPE= array("1" => "One Way Trip", "2" => "Return Trip",  "3" => "Vehicle Disposal" );