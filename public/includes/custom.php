<?php
function GetSingleDataFromID($table, $pk_field, $pk_id, $cond = "")
{
    // Construct the query
    $q = "SELECT * FROM " . $table . " WHERE " . $pk_field . " = '" . $pk_id . "' " . $cond . " LIMIT 1";

    // Execute the query
    $r = sql_query($q, "CUSTOM.05");

    // Check if at least one row is returned
    if (sql_num_rows($r)) {
        // Fetch and return the first row as an object
        return sql_fetch_object($r);
    }

    // Return null if no row is found
    return null;
}
function FillComboGroup($selected, $ctrl, $comp, $values, $class = "form-control", $groupclass = "bg-gray", $optionclass = "bg-white", $fn = "")
{
?>
    <select name="<?php echo $ctrl; ?>" id="<?php echo $ctrl; ?>" class="<?php echo $class; ?>" <?php echo $fn; ?>>
        <?php
        if (!empty($comp)) {
            $selected_str = ('' === $selected) ? 'selected' : '';
        ?>
            <option class="<?php echo $optionclass; ?>" value="" <?php echo $selected_str; ?>> - <?php echo $comp; ?> - </option>
        <?php
        }
        foreach ($values as $key1 => $val1) {
        ?>
            <optgroup label="<?php echo $val1['name']; ?>" class="<?php echo $groupclass; ?>">
                <?php
                foreach ($val1['opt'] as $key2 => $val2) {
                    $key = "{$key1}~{$key2}";
                    $selected_str = ($key === $selected) ? 'selected' : '';
                ?>
                    <option class="<?php echo $optionclass; ?>" value="<?php echo $key; ?>" <?php echo $selected_str; ?>><?php echo $val2; ?></option>
                <?php
                }
                ?>
            </optgroup>
        <?php
        }
        ?>
    </select>
<?php
}

function GetDataFromID($table, $pk_field, $pk_id, $cond = "")
{
    $q = "select * from " . $table . " where " . $pk_field . " = '" . $pk_id . "' " . $cond;
    $r = sql_query($q, "CUSTOM.05");

    $arr = array();
    if (sql_num_rows($r)) {
        while ($row = sql_fetch_object($r)) {
            $arr[] = $row;
        }

        return $arr;
    }
}

function GetDataFromQuery($q)
{
    $arr = array();
    $r = sql_query($q, "CUSTOM.06");
    if (sql_num_rows($r)) $arr = sql_get_data($r);
    return $arr;
}

function GetDataFromCOND($table, $cond = "")
{
    $q = "select * from " . $table . " where 1 " . $cond;
    $r = sql_query($q, "CUSTOM.21");

    $arr = array();
    if (sql_num_rows($r)) {
        while ($row = sql_fetch_object($r)) {
            $arr[] = $row;
        }

        return $arr;
    }
}

function InsertData($table, $values)
{
    $str = '';

    if (!empty($values)) {
        $q = "insert into $table values(" . $values . ")";
        $r = sql_query($q, "CUSTOM.37");
    }

    //$str = $q.'<br />'; 
    $str = $r;

    return $str;
}

function UpdataData($table, $values, $cond)
{
    $str = '';

    if (!empty($values)) {
        $q = "update $table set $values where $cond";
        $r = sql_query($q, "CUSTOM.56");
    }

    $str = $q;

    return $str;
}

function UpdateData($table, $values, $cond)
{
    $str = '';

    if (!empty($values)) {
        $q = "update $table set $values where $cond";
        $r = sql_query($q, "CUSTOM.56");
    }

    $str = $q;

    return $str;
}

function DeleteData($table, $field, $pk, $cond = "")
{
    $str = '';

    $q = "delete from $table where $field=$pk and 1 $cond";
    $r = sql_query($q, "CUSTOM.56");

    $str = $q;

    return $str;
}

function UpdateField($table, $field, $field_val, $cond = "")
{
    $q = "update $table set $field='" . $field_val . "' where 1 and $cond";
    $r = sql_query($q, 'CUSTOM.351');
    $count = sql_affected_rows($r);

    return $count;
}

function HelpIcon($mesg)
{
    $str = '<i class="fa fa-question-circle" data-toggle="tooltip" data-placement="right" title="' . $mesg . '"></i>';

    return $str;
}

function GetCountFromTable($table, $cond = "")
{
    $count = GetXFromYID("select count(*) from " . $table . " where 1 " . $cond);

    return $count;
}

function GetXArrFromYID2($table, $values, $cond = '', $mode = "1")
{
    $q  = "select $values from $table where 1 $cond";
    $arr = array();
    $r = sql_query($q, 'COM39');

    if (sql_num_rows($r)) {
        if ($mode == "2")
            for ($i = 0; list($x) = sql_fetch_row($r); $i++)
                $arr[$i] = $x;
        else if ($mode == "3")
            for ($i = 0; list($x, $y) = sql_fetch_row($r); $i++)
                $arr[$x] = $y;
        else if ($mode == "4")
            while ($a = sql_fetch_assoc($r))
                $arr[$a['I']] = $a;
        else
            while (list($x) = sql_fetch_row($r))
                $arr[$x] = $x;
    }

    return $arr;
}

function GetCounts($table, $cond)
{
    $q = GetXFromYID("select count(*) from $table where 1 $cond");

    return $q;
}

function GetStatusPills($status = "", $status_arr = "")
{
    $pill_str = $bd_col = "";
    $text = isset($status_arr[$status]) ? $status_arr[$status] : '';

    if (!empty($text)) {
        if ($status == 'U') $bd_col = 'badge-primary';
        else if ($status == 'D') $bd_col = 'badge-danger';
        else if ($status == 'A') $bd_col =  'badge-success';

        $pill_str = '<div class="mb-2 mr-2 badge ' . $bd_col . '">' . $text . '</div>';
    }

    return $pill_str;
}

######################## IMA related functions ##########################

function GetPatientCode($pat_id)
{
    $pat_code = 'invalid';

    if (!empty($pat_id) && is_numeric($pat_id)) {
        $c_str = '#PAT';
        $c_str .= str_pad($pat_id, 5, '0', STR_PAD_LEFT);

        $pat_code = $c_str;
    }

    return $pat_code;
}

function getPatientCurrentStatus($pat_id)
{
    // awaiting result , positive, home quarantine, hospitalized and Recovered
    global $PATIENT_STAGE_ARR, $PATIENT_STAGE_CSS_ARR; // = array('I'=>'Isolated/Qurantined', 'H'=>'Hospitalised', 'D'=>'Deceased', 'C'=>'Cured');
    global $TEST_STATUS_ARR, $TEST_STATUS_CSS_ARR;
    /*$TEST_STATUS_ARR = array('A'=>'Awaiting', 'Y'=>'Positive', 'N'=>'Negative');
    $TEST_STATUS_CSS_ARR = array('A'=>'warning', 'Y'=>'danger', 'N'=>'success');

    $PATIENT_STAGE_ARR = array('I'=>'Isolated/Qurantined', 'H'=>'Hospitalised', 'D'=>'Deceased', 'C'=>'Cured');
    $PATIENT_STAGE_CSS_ARR = array('I'=>'alternate', 'H'=>'warning', 'D'=>'danger', 'C'=>'success');*/

    $status_arr = array();
    $text = "Avaiting Result";
    $color = "bg-warning";
    if (is_numeric($pat_id)) {
        $q = "select cPositive, cStage from patient where iPatID=$pat_id";
        $r = sql_query($q, "");

        if (sql_num_rows($r)) {
            list($_positive, $_stage) = sql_fetch_row($r, "");

            if (!empty($_positive)) {
                $text = $TEST_STATUS_ARR[$_positive];
                $color = $TEST_STATUS_CSS_ARR[$_positive];
            }

            if (!empty($_stage)) {
                $text .= '/' . $PATIENT_STAGE_ARR[$_stage];
            }

            /*if($_positive=='N') 
            {
                $text 
                $text = "Negative";
                $color = "bg-default";
            }
            else if($_positive=='Y')
            {
                $text = "Positive";
                $color = "bg-danger";

                if(isset($PATIENT_STAGE_ARR[$_stage]))
                {
                    $text = $PATIENT_STAGE_ARR[$_stage];
                    $color = 'bg-'.$PATIENT_STAGE_CSS_ARR[$_stage];                    
                }
            }*/
        }
    }

    $status_arr['TEXT'] = $text;
    $status_arr['COLOR'] = "progress-bar " . $color;
    $status_arr['ELEM'] = '<div className="progress-bar ' . $color . '" role="progressbar" style="width:100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">' . $text . '</div>';

    return $status_arr;
}

function updateWebPusherId($pat_id, $pusher_id = "")
{
    $notif_id = "";
    if (is_numeric($pat_id)) {
        $notif_id = GetXFromYID("SELECT vWebPushrID FROM patient WHERE iPatID=$pat_id");

        if ($notif_id != $pusher_id)
            UpdateField('patient', 'vWebPushrID', $pusher_id, "iPatID=$pat_id");
    }
}

function getVolunteerDetails($vid)
{
    $v_details = array();
    if (is_numeric($vid)) {
        $q = "select vName, vMobile from volunteer where iVolunteerID=$vid";
        $r = sql_query($q, "");

        $_name = $_phone = "";
        if (sql_num_rows($r)) {
            list($_name, $_phone) = sql_fetch_row($r);
        }

        $v_details['NAME'] = $_name;
        $v_details['PHONE'] = $_phone;
    }

    return $v_details;
}

function getPatientOTP($patid)
{
    $otp = "";
    if (is_numeric($patid)) {
        $otp = GetXFromYID("select cOTP from patinvite where iPatInviteID=$patid");
    }

    return $otp;
}

function PostRequest($url, $referer, $_data)
{
    // convert variables array to string:
    $data = array();
    while (list($n, $v) =
        each($_data)
    ) {
        $data[] = "$n=$v";
    }

    $data = implode('&', $data);

    $url = parse_url($url);
    if ($url['scheme'] != 'http') {
        die('Only HTTP request are supported !');
    }
    // extract host and path:
    $host = $url['host'];
    $path = $url['path'];

    // open a socket connection on port 80
    $fp = fsockopen($host, 80);

    // send the request headers:
    fputs($fp, "POST $path HTTP/1.1\r\n");
    fputs($fp, "Host: $host\r\n");
    fputs($fp, "Referer: $referer\r\n");
    fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
    fputs($fp, "Content-length: " . strlen($data) . "\r\n");
    fputs($fp, "Connection: close\r\n\r\n");
    fputs($fp, $data);

    $result = '';

    while (!feof($fp)) {
        // receive the results of the request
        $result .= fgets($fp, 128);
    }

    // close the socket connection:
    fclose($fp);

    // split the result header from the content
    $result = explode("\r\n\r\n", $result, 2);
    $header = isset($result[0]) ? $result[0] : '';
    $content = isset($result[1]) ? $result[1] : '';

    // return as array:
    return array($header, $content);
}

function updateSymptoms($POST = array(), $patid = "", $date = "")
{
    $txtchkcough = isset($_POST['chkCough']) ? $_POST['chkCough'] : 'N';
    $txtchktiredness = isset($_POST['chkTiredness']) ? $_POST['chkTiredness'] : 'N';
    $txtchkshortb = isset($_POST['chkShortB']) ? $_POST['chkShortB'] : 'N';
    $txtchkheadache = isset($_POST['chkHeadache']) ? $_POST['chkHeadache'] : 'N';
    $txtchkdrosiness = isset($_POST['chkDrosiness']) ? $_POST['chkDrosiness'] : 'N';
    $txtchkchestpain = isset($_POST['chkChestPain']) ? $_POST['chkChestPain'] : 'N';
    $txtnotes = isset($_POST['txtnotes']) ? db_input2($_POST['txtnotes']) : '';

    if (is_numeric($patid)) {
        $med_Id = GetXFromYID("select iMedLogID from pat_medlog where dDate='$date' and iPatID=$patid order by dtEntry desc, FIELD(cType,'N','A','M') limit 1");

        if (!empty($med_Id) && is_numeric($med_Id)) {
            $q = "UPDATE pat_medlog SET cCough='$txtchkcough', cHeadAche='$txtchkheadache', cShortnessBreath='$txtchkshortb', cTiredness='$txtchktiredness', cChestPain='$txtchkchestpain', cDrowsiness='$txtchkdrosiness', vNotes='$txtnotes' WHERE iMedLogid=$med_Id";
            $r = sql_query($q, "");
        }
    }
}
?>