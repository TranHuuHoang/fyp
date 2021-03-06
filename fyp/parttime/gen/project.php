<?php 
require_once('../../../Connections/db_ntu.php');
require_once('../../../CSRFProtection.php');
require_once('../../../Utility.php');

$csrf = new CSRFProtection();

$_REQUEST['csrf'] 	= $csrf->cfmRequest();

$filter_Search 			= "%". (isset($_REQUEST['search']) && !empty($_REQUEST['search']) ? $_REQUEST['search'] : '') ."%";
$filter_ProjectYear 	= "%". (isset($_REQUEST['filter_ProjectYear']) && !empty($_REQUEST['filter_ProjectYear']) ? $_REQUEST['filter_ProjectYear'] : '') ."%";
$filter_ProjectSem 		= "%". (isset($_REQUEST['filter_ProjectSem']) && !empty($_REQUEST['filter_ProjectSem']) ? $_REQUEST['filter_ProjectSem'] : '') ."%";
$filter_Supervisor  	= "%". (isset($_REQUEST['filter_Supervisor']) && !empty($_REQUEST['filter_Supervisor']) ? $_REQUEST['filter_Supervisor'] : '') ."%";

$query_rsStaff				= "SELECT * FROM " . $TABLES["staff"];
$query_rsProject 			= "SELECT * FROM " .
$TABLES['fea_projects_part_time'] . " as p1 LEFT JOIN " . 
$TABLES['fyp_assign_part_time'] 	. " as p2 ON p1.project_id 	= p2.project_id LEFT JOIN "	.
$TABLES['fyp']			. " as p3 ON p2.project_id 	= p3.project_id LEFT JOIN "	.
$TABLES['staff']		. " as p4 ON p2.staff_id 	= p4.id "					.
"WHERE p2.complete = 0 AND (p2.project_id LIKE ? OR p3.title LIKE ?) AND (p2.year LIKE ? AND p2.sem LIKE ? AND p2.staff_id LIKE ?) ORDER BY p2.project_id ASC";

try
{
	// GET ALL STAFF FOR FILTER DROP DOWN CONTROL
	$stmt_0 			= $conn_db_ntu->prepare($query_rsStaff);
	$stmt_0->execute();
	$DBData_rsStaff 	= $stmt_0->fetchAll(PDO::FETCH_ASSOC);
	$AL_Staff			= array();
	foreach ($DBData_rsStaff as $key => $value) {
		$AL_Staff[$value["id"]] = $value["name"];
	}
	asort($AL_Staff);

	// GET Project data
	$stmt 				= $conn_db_ntu->prepare($query_rsProject);
	$stmt->bindParam(1, $filter_Search);				// Search project id 
	$stmt->bindParam(2, $filter_Search);				// Search project title
	$stmt->bindParam(3, $filter_ProjectYear);			// Search project year
	$stmt->bindParam(4, $filter_ProjectSem);			// Search project sem
	$stmt->bindParam(5, $filter_Supervisor);			// Search supervisor
	$stmt->execute();
	$DBData_rsProject   = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$Total_RowCount 	= count($DBData_rsProject);


}
catch (PDOException $e)
{
	die($e->getMessage());
}
$conn_db_ntu = null;
?>

<!DOCTYPE html >
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
		<title>Part time Project List</title>
	<?php require_once('../../../head.php'); ?>
</head>

<body>
	<div id="loadingdiv" class="loadingdiv">
		<img id="loadinggif" src="../../../images/loading.gif"/>
		<p>Uploading projects....</p>
	</div>
	<div id="bar"></div>
	<div id="wrapper">
		<div id="header"></div>
		
		<div id="left">
			<div id="nav">
				<?php require_once('../../nav.php'); ?>
			</div>
		</div>
		
		<div id="logout">
				<a href="../../logout.php"><img src="../../../images/logout.jpg" /></a>
		</div>
		
		<!-- InstanceBeginEditable name="Content" -->
		<div id="content">
			<h1>Part Time Project List</h1>
            <?php  
			if (isset ($_REQUEST['csrf']) ||isset ($_REQUEST['validate'])) {
				echo "<p class='warn'> CSRF validation failed.</p>";	
			}
			
            else {
				
				if (isset ($_REQUEST['error_code'])) {
					$error_code = $_REQUEST['error_code'];
					switch ($error_code) {
						case 1:
						echo "<p class='warn'> Uploaded file has no file name!</p>";
						break;
						case 2:
						echo "<p class='warn'> Uploaded file has an invalid format type. Only excel files (.xlsx .xls .csv) are allowed!</p>";
						break;
						case 3:
						echo "<p class='warn'> Uploaded file is open. Close it and upload again!</p>";
						break;
						case 4:
						echo "<p class='error'> Cannot load excel file. Please contact system admin!</p>";
						break;
					}
			   }
				if (isset ($_REQUEST['import_project'])){
					echo "<p class='success'> Project List uploaded successfully.</p>";	
				}?>
			<div id="topcon">
				
		 <?php require_once('../../../upload_head.php'); ?>
				<form id="FORM_FileToUpload_ProjectList" enctype="multipart/form-data">
					<table style="text-align: left; width: 100%;">
						<col width="20%">
						<col width="20%">
						<col width="20%">
						<col width="20%">
						<col width="20%">
						<tr>
							<td colspan="4">
								Please select the <b><u>Project List</u></b>:
							</td>
							<td style="text-align: right;">
								<input type="submit" value="Import" name="submit" class="btn btn-xs btn-success" >
							</td>
						</tr>
						
						<tr>
							<td colspan="5">
								<input type="file" id="FileToUpload_ProjectList" name="file" >
							</td>
						</tr>
						<tr>
							<td colspan="5">
								<div id="progressbardiv" class="progress" style="display: none;">
									<div id="progressbar" class="progress-bar progress-bar-success" role="progressbar" style="width:0%; color:black; ">
										<span>0%</span>
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<td colspan="5"><div id="status"></div></td>
						</tr>
					</table>
					<?php $csrf->echoInputField();?>
				</form>
				<script type="text/javascript">
					$("#FORM_FileToUpload_ProjectList").submit(function( event ) {
						uploadFile();
						event.preventDefault();
					});
					function _(el){
						return document.getElementById(el);
					}
					function uploadFile(){
						if(_("FileToUpload_ProjectList").files.length == 0) {
							alert("Please select a file to upload!");
						}
						else {
							var file_data = _("FileToUpload_ProjectList").files[0];
							var csrfToken = _("CSRF_token").value;
							console.log(file_data.name + ", "+ file_data.size +", "+ file_data.type);
							var formData = new FormData();
							
							formData.append("file", file_data);
							formData.append("csrf__",csrfToken );
							_("loadingdiv").style.display  = "block";
							$.ajax({
								url: 'submit_import_projectlist.php',
								data: formData,
								processData: false,
								contentType: false,
								type: 'POST',
								xhr: function () {
			                    	// this part is progress bar
			                    	var xhr = new window.XMLHttpRequest();
			                    	xhr.upload.addEventListener("progress", function (evt) {
			                    		_("progressbardiv").style.display  = "block";
			                    		if (evt.lengthComputable) {
			                    			var percentComplete = evt.loaded / evt.total;
			                    			percentComplete = parseInt(percentComplete * 100);
			                    			$("#progressbar").text(percentComplete + "%");
			                    			$("#progressbar").css('width', percentComplete + "%");

			                    			if(percentComplete == 100){
			                    				_('status').innerHTML = "File uploaded. Waiting for server to respond!";
			                    			}
			                    		}
			                    	}, false );
			                    	return xhr;
			                    },
			                    success: function (data) {
			                    	console.log(data);
			                    	console.log("File uploaded. Server Responded!");
			                    	_('status').innerHTML = "File uploaded. Server Responded!";
			                    	_("progressbardiv").style.display  = "none";
			                    	_("loadingdiv").style.display  = "none";
			                    	$("#progressbar").text(0 + "%");
			                    	$("#progressbar").css('width', 0 + "%");
			                    	window.location.href = ("project.php?" + data);
			                    },
			                    error: function(data){
			                    	console.log("File upload failed!");
			                    	_('status').innerHTML = "File upload failed!";
			                    }
			                });

						}
					}
				</script>
				<br/>
				<form name="searchbox" action="project.php" method="post" >
					<table id="Table_Filter_ProjectList" width="100%" >
						<colgroup>
							<col width="20%" >
							<col width="20%" >
							<col width="20%" >
							<col width="20%" >
							<col width="20%" >
						</colgroup>
						<tr>
							<td >
								<b> Year</b>
							</td>
							<td>
								<select id="filter_ProjectYear" name="filter_ProjectYear" onchange="this.form.submit()">
									<option value="">SELECT</option>
									<?php
									$CurrentYear = sprintf("%02d", substr(date("Y"), -2));
									$LastestYear = sprintf("%02d", substr(date("Y"), -2));
									$EarlistYear = $CurrentYear - 10;

										 // Loops over each int[year] from current year, back to the $earliest_year [1950]
									foreach ( range( $LastestYear, $EarlistYear ) as $i ) {
										$i = sprintf("%02d", substr($i, -2)) . (sprintf("%02d", (substr($i, -2)+1)));

										if(isset($_REQUEST["filter_ProjectYear"]) && $_REQUEST["filter_ProjectYear"] == $i){
											echo "<option selected value='".$i."'>".$i."</option>";
										}else{
											echo "<option value='".$i."'>".$i."</option>";
										}
									}
									?>
								</select>
							</td>
							<td colspan="3" >
								<?php 
								if( $Total_RowCount > 1){
									echo $Total_RowCount . " records";
								}else{
									echo $Total_RowCount . " record";
								}
								?>
							</td>
						</tr>
						<tr>
							<td >
								<b> Sem</b>
							</td>
							<td>
								<select id="filter_ProjectSem" name="filter_ProjectSem" onchange="this.form.submit()">
									<option value="">SELECT</option>
									<?php
									for($index = 1; $index<3; $index++){
										if(isset($_REQUEST["filter_ProjectSem"]) && $_REQUEST["filter_ProjectSem"] == $index){
											echo "<option selected value='".$index."'>".$index."</option>";
										}else{
											echo "<option value='".$index."'>".$index."</option>";
										}
									}
									?>
								</select>
							</td>
							<td colspan="3"></td>
						</tr>
						<tr>
							<td >
								<b>Supervisor</b>
							</td>
							<td colspan="2">
								<select id="filter_Supervisor" name="filter_Supervisor" onchange="this.form.submit()">
									<option value="" selected>SELECT</option>
									<?php
									foreach ($AL_Staff as $key => $value) {
										$StaffID_Filter = $_REQUEST["filter_Supervisor"];
										$StaffID = $key;
										$StaffName = $value;
										if($StaffID_Filter == $StaffID){
											echo "<option value=" . $StaffID . " selected>";
											echo $StaffName;
											echo "</option>";	
										}else{
											echo "<option value=" . $StaffID . ">";
											echo $StaffName;
											echo "</option>";
										}
									}
									?>
								</select>
							</td>
							<td colspan="2" style="text-align:right;">
								<input type="search" name="search" value="<?php echo isset($_REQUEST['search']) ?  $_REQUEST['search'] : '' ?>" />
								<input type="submit" value="Search" title="Search for a project" class="bt"/>
							</td>
						</tr>
					</table>
					<?php $csrf->echoInputField();?>
				</form>
				<br/>
				<table width="100%" border="1">
					<col width="13%" />
					<col width="6%" />
					<col width="5%" />
					<col width="32%" />
					<col width="30%" />
					<col width="6%" />
					<col width="6%" />

					<tr class="heading">
						<td>Project ID</td>
						<td>Year</td>
						<td>Sem</td>
						<td>Project Title</td>
						<td>Supervisor</td>
						<td>Exam Year</td>
						<td>Exam Sem</td>
					</tr>
					<?php
					foreach ($DBData_rsProject as $key => $value) {
						echo "<tr>";
						echo "<td>" . $value['project_id'] . "</td>";
						echo "<td>" . $value['year'] . "</td>";
						echo "<td>" . $value['sem'] . "</td>";
						echo "<td>" . $value['title'] . "</td>";
						echo "<td>" . $value['Supervisor'] . "</td>";
						echo "<td>" . $value['examine_year'] . "</td>";
						echo "<td>" . $value['examine_sem'] . "</td>";
						echo "</tr>";
					}
					?>
				</table>
				
				</div>	
				
				
				
               <?php }?>
		</div>
		<!-- InstanceEndEditable --> 
		
		<?php require_once('../../../footer.php'); ?>
	</div>
</body>
<!-- InstanceEnd -->
</html>

<?php
	unset($rsProject);
?>