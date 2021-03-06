<?php require_once('../../../Connections/db_ntu.php');
require_once('../../../CSRFProtection.php');
require_once('../../../Utility.php'); ?>

<?php
$localHostDomain = 'http://localhost';
$ServerDomainHTTP = 'http://155.69.100.32';
$ServerDomainHTTPS = 'https://155.69.100.32';
$ServerDomain = 'https://fypexam.scse.ntu.edu.sg';
if(isset($_SERVER['HTTP_REFERER'])) {
	try {
			// If referer is correct
			if ((strpos($_SERVER['HTTP_REFERER'], $localHostDomain) !== false) || (strpos($_SERVER['HTTP_REFERER'], $ServerDomainHTTP) !== false) || (strpos($_SERVER['HTTP_REFERER'], $ServerDomainHTTPS) !== false) || (strpos($_SERVER['HTTP_REFERER'], $ServerDomain) !== false)) {
					//echo "<script>console.log( 'Debug: " . "Correct Referer" . "' );</script>";
			}
			else {
					throw new Exception($_SERVER['Invalid Referer']);
					//echo "<script>console.log( 'Debug: " . "Incorrect Referer" . "' );</script>";
			}
	}
	catch (Exception $e) {
			header("HTTP/1.1 400 Bad Request");
			die ("Invalid Referer.");
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SERVER['QUERY_STRING'])) {
		header("HTTP/1.1 400 Bad Request");
		exit("Bad Request");
}
else {
    $csrf = new CSRFProtection();

    $_REQUEST['validate'] = $csrf->cfmRequest();

    $rsStaff = $conn_db_ntu->query("SELECT * FROM " . $TABLES['staff'] . " ORDER BY id ASC")->fetchAll();

    //matches the table with the database and update database if there are changes.
    $delete = array();
    foreach ($rsStaff as $curStaff) {
        $staffid = str_replace('.','',$curStaff['id']);
        //$staffid = $curStaff['id'];
        if (isset($_POST['index_'.$staffid]) && !empty($_POST['index_'.$staffid])) {

            $name = GetSQLValueString(trim($_POST['name_' . $staffid]), "text");
            $name2 = GetSQLValueString(trim($_POST['name2_' . $staffid]), "text");
            $email = strtolower(trim($_POST['email_' . $staffid]));
            $canExamine = isset($_POST['examine_' . $staffid]);
            $id = explode("@", $email)[0];

            // update staff id when email address is edited.
            if ($email != strtolower(trim($curStaff['email']))) {

                $query_Update = sprintf("UPDATE %s SET id = '%s', email='%s' where id ='%s'", $TABLES["staff"], $id, $email, $curStaff['id']);
                $DBOBJ_Result = $conn_db_ntu->prepare($query_Update);
                $DBOBJ_Result->execute();
            }

            $email = GetSQLValueString($email, "text");
            $id = GetSQLValueString($id, "text");

            /* $values[] = sprintf("(%s, %s, %s, %s, %d, %d)",
                 $id, $email, $name, $name2, $exemption, $canExamine);
            */

            if (isset($_POST["sem"]) && $_POST["sem"] == 1) {

                if (isset($_POST['exemption_' . $staffid])) {
                    $exemption = GetSQLValueString(trim($_POST['exemption_' . $staffid]), "int");
                    if ($exemption === "NULL") $exemption = 0;

                    if (trim($_POST['name_' . $staffid]) != $curStaff['name'] || trim($_POST['name2_' . $staffid]) != $curStaff['name2'] || $canExamine != $curStaff['examine'] || $exemption != $curStaff['exemption']) {
                        $query_Update = sprintf("UPDATE %s SET name=%s, name2=%s, examine=%d, exemption=%d where id =%s", $TABLES["staff"], $name, $name2, $canExamine, $exemption, $id);
                        $DBOBJ_Result = $conn_db_ntu->prepare($query_Update);
                        $DBOBJ_Result->execute();
                    }
                }
            } else if (isset($_POST["sem"]) && $_POST["sem"] == 2) {

                if (isset($_POST['exemptionS2_' . $staffid])) {
                    $exemptionS2 = GetSQLValueString(trim($_POST['exemptionS2_' . $staffid]), "int");
                    if ($exemptionS2 === "NULL") $exemptionS2 = 0;

                    if (trim($_POST['name_' . $staffid]) != $curStaff['name'] || trim($_POST['name2_' . $staffid]) != $curStaff['name2'] || $canExamine != $curStaff['examine'] || $exemptionS2 != $curStaff['exemptionS2']) {
                        $query_Update = sprintf("UPDATE %s SET name=%s, name2=%s, examine=%d, exemptionS2=%d where id =%s", $TABLES["staff"], $name, $name2, $canExamine, $exemptionS2, $id);
                        $DBOBJ_Result = $conn_db_ntu->prepare($query_Update);
                        $DBOBJ_Result->execute();

                    }
                }
            }
        }
        // find any deleted staff entries from examiner setting table and delete the staffs from the database.
        else {
            $id = GetSQLValueString($curStaff['id'], "text");
            $query_Delete = sprintf("DELETE FROM %s WHERE id=%s", $TABLES["staff"],$id);
            $DBOBJ_Result = $conn_db_ntu->prepare($query_Delete);
            $DBOBJ_Result->execute();
            //$delete[] = sprintf("id=%s", $staffid);
        }
    }

    // add new staff into database
    $c = 0;
    if (isset($_POST['newEmail'])) {
        foreach ($_POST['newEmail'] as $newRecord) {
            $newID = GetSQLValueString(explode("@", strtolower(trim($newRecord)))[0], "text");
            $newEmail = GetSQLValueString(strtolower($_POST['newEmail'][$c]), "text");
            $newName = GetSQLValueString(trim($_POST['newName'][$c]), "text");
            $newName2 = GetSQLValueString(trim($_POST['newName2'][$c]), "text");
            $newExemption = GetSQLValueString(trim($_POST['newExemption'][$c]), "int");
            $newExamine = isset($_POST['newExamine'][$c]);


            $query_Insert = sprintf("INSERT INTO %s (id, email, name, name2, exemption, examine) VALUES (%s, %s, %s, %s, %d, %d)
    		    ON DUPLICATE KEY UPDATE name=%s, name2=%s, exemption=%d, examine=%d ", $TABLES["staff"], $newID, $newEmail, $newName, $newName2, $newExemption, $newExamine, $newName, $newName2, $newExemption, $newExamine);
            $DBOBJ_Result = $conn_db_ntu->prepare($query_Insert);
            $DBOBJ_Result->execute();

            $c++;
        }
    }

    $conn_db_ntu = null;
    unset($rsStaff);
    unset($delete);
    unset($c);

    if (session_status() !==PHP_SESSION_ACTIVE) { session_start();}
    $_SESSION["semester"] = $_POST["sem"];
    $_SESSION["year"] = $_POST["year"];

    if (isset ($_REQUEST['validate'])) {
        header("location:examiner_setting.php?validate=1");
        exit;
    } else {
        $_SESSION['examiner_setting_msg'] = "save";
        header("location:examiner_setting.php");
        exit;
    }
}
?>
