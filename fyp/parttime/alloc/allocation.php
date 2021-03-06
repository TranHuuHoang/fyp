<?php require_once('../../../Connections/db_ntu.php');
	  require_once('./entity.php');  
      require_once('../../../CSRFProtection.php');
	  require_once('../../../Utility.php');?>

<?php
    $csrf = new CSRFProtection();
	
	
	$query_rsSettings 	= "SELECT * FROM ".$TABLES['allocation_settings_general_part_time']." as g";
	$query_rsTimeslot 	= "SELECT * FROM ".$TABLES['allocation_result_timeslot_part_time']." ORDER BY `id` ASC";
	
	$query_rsDay 		= "SELECT count(*) as number_of_days FROM ".$TABLES['allocation_settings_general_part_time'] . " WHERE opt_out = 0";
	//$query_rsRoom 		= "SELECT * FROM ".$TABLES['allocation_result_room_part_time']." ORDER BY `id` ASC";
	$query_rsStaff	 	= "SELECT s.id as staffid, s.name as staffname, s.position as salutation FROM ".$TABLES['staff']." as s";
	
	
	
	$query_rsAllocation = "SELECT t1.project_id, t2.staff_id, t1.examiner_id, t1.day, t1.slot, t1.room, t1.clash FROM ".$TABLES['allocation_result_part_time']." as t1 JOIN ".$TABLES['fyp_assign_part_time']." as t2 ON t1.project_id = t2.project_id ";
	
	
	
	try
	{
		$settings 		= $conn_db_ntu->query($query_rsSettings)->fetch();
		$rsTimeslot		= $conn_db_ntu->query($query_rsTimeslot)->fetchAll();
		$rsDay			= $conn_db_ntu->query($query_rsDay)->fetch();
		//$rsRoom			= $conn_db_ntu->query($query_rsRoom);
		$staffs			= $conn_db_ntu->query($query_rsStaff);
		$rsAllocation	= $conn_db_ntu->query($query_rsAllocation);
	}
	catch (PDOException $e)
	{
		die($e->getMessage());
	}
	
	//Parse Alloc Date
	try
	{
		$startDate 		 	= DateTime::createFromFormat('Y-m-d', $settings['alloc_date']);
	}
	catch(Exception $e)
	{
		//Default Values
		$startDate 			= new DateTime();
	}
	
	//Timeslots
	$NO_OF_DAYS = $rsDay['number_of_days'];
	for($day=1; $day<=$NO_OF_DAYS; $day++)
		$timeslots_table[$day] = array();

	$day = 0;
	/*foreach ($rsTimeslot as $timeslot)
	{
		if ($day != $timeslot['day'])
		{
			$day++;
			$count=0;
		}
		
		$timeslots_table[$timeslot['day']][ ++$count ] = new Timeslot( 	$timeslot['id'],
																		$timeslot['day'],
																		$timeslot['slot'],
																		DateTime::createFromFormat('H:i:s', $timeslot['time_start']), 
																		DateTime::createFromFormat('H:i:s', $timeslot['time_end']));													  
	}*/
	foreach ($rsTimeslot as $timeslot)
	{
		$timeslots_table[ $timeslot['day'] ][ $timeslot['slot'] ] 	= new Timeslot( $timeslot['id'],
																					$timeslot['day'],
																					$timeslot['slot'],
																					DateTime::createFromFormat('H:i:s', $timeslot['time_start']), 
																					DateTime::createFromFormat('H:i:s', $timeslot['time_end']));
	}
	
	//Rooms
	/*$rooms_table = array();
	foreach ($rsRoom as $room)
	{
		$rooms_table[ $room['id'] ] = new Room(	$room['id'], 
												$room['roomName']);
	}*/
	
	//Staff
	$staffList = array();
	foreach($staffs as $staff) { //Index Staff by staffid
		$staffList[ $staff['staffid'] ] = new Staff($staff['staffid'],
													$staff['salutation'],
													$staff['staffname']);
	}
	
	function getDay($day)
	{
		global $startDate;
		
		if ($day === null || $day == -1) return "-";
		
		$calculatedDate = clone $startDate;
		$day_interval	= new DateInterval('P'.($day-1).'D');	//Offset -1 because day 1 falls on startDate
		$calculatedDate->add($day_interval);
		
		return $calculatedDate->format('d/m/Y');
	}

	//function stringify($s)
	//{
	//	if ($s === null || $s == -1) return "-";
	//	return $s;
	//}
	
	function getStaff($s)
	{
		global $staffList;
		
		if ($s === null || $s == -1) return "-";
		if (!array_key_exists($s, $staffList)) return "?";
		return $staffList[$s]->toString();
	}
	
	function getRoom($s, $day)
	{
		$index = $s-1;
		$rooms_table = retrieveRooms ($day, "allocation_result_room_part_time");
		if ($s === null || $s == -1 || !array_key_exists($index, $rooms_table)|| !(isset($rooms_table))){
			return "-";
		} 
		return $rooms_table[$index]->toString();
	}
	
	function getTimeSlot($d, $s)
	{
		global $timeslots_table;
		if ($d == null || $d == -1 || $s === null || $s == -1 || !array_key_exists($d, $timeslots_table) || !array_key_exists($s, $timeslots_table[$d])) {
			return "-";
		}
		return $timeslots_table[$d][$s]->toString();
	}
	
	
	
?>
<!DOCTYPE html >
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
<title>Allocation System</title>
	<?php require_once('../../../head.php'); ?>
	
	<style>
	.clash_tr {
		background: #FFFF00;
		font-weight: bold;
	}
	
	.clash_td {
		color: #FF0000;
	}
	</style>
	
</head>

<body>
<div id="loadingdiv" class="loadingdiv">
		<img id="loadinggif" src="../../../images/loading.gif"/>
		<p>Allocating timeslots...</p>
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
			<h1>Examiner Allocation System for Part Time Projects</h1>
			<br/>
			<?php 
				if(isset($_REQUEST['error_timeslot']))
				{
					$error_code = $_REQUEST['error_timeslot'];
					switch($error_code)
					{
						case 0: break;
						case 1: 
							echo "<p class='error'>[Timeslot Allocation] Failed: Please allocate examiner first before proceeding!</p>";
							break;
						case 2: 
							echo "<p class='error'>[Timeslot Allocation] Failed: Problem loading timetable settings.</p>";
							break;
						default: 
							echo "<p class='error'>[Timeslot Allocation] Failed: Unknown Error has occurred. </p>";
							break;
					}
				}
				else if(isset($_REQUEST['error_examiner']))
				{
					$error_code = $_REQUEST['error_examiner'];
					switch($error_code)
					{
						case 0:
						break;
						case 1: echo "<p class='error'>[Examiner Allocation] Failed: Please upload the examiner and examinable project list before proceeding!</p>";
						break;
						default: echo "<p class='error'>[Examiner Allocation] Unknown Error has occurred.</p>";
						break;
					}
				}
				else
				{
					if(isset($_REQUEST['allocate_examiner']))
					echo "<p class='success'>[Examiner Allocation] Complete.</p>";
				
					if(isset($_REQUEST['allocate_timeslot']))
					{
						$allocate_code = $_REQUEST['allocate_timeslot'];
						if ($allocate_code == 1)
							echo "<p class='success'>[Timeslot Allocation] Complete.</p>";
						else
							echo "<p class='warn'>[Timeslot Allocation] Allocation may be incomplete.</p>";
					}
						
					
					if(isset($_REQUEST['call']))
						echo "<p class='warn'>[System] All Allocations cleared.</p>";
					
				}?>
		<div id="topcon">
				<div style="float:right; padding-bottom:15px;">
					Number of Project Buffer:
					<input type="text" id="Total_BufferProjects" name="Total_BufferProjects" value="0" placeholder="0"></input>
					<a href="allocation_timetable.php" class="bt" style="width:105px;" title="View Timetable">View Timetable</a>
					<a href="submit_download_timetable.php" class="bt" style="width:125px;" title="Download Timetable">Download Timetable</a>
				</div>
				<div style="float:right; padding-bottom:15px;">

					<button  id="BTN_AllocationExaminer" class="bt" style="width:105px;" title="Allocate Examiner">Allocate Examiner</button>
					
					<button id="allocateTimeSlotBtn"  class="bt" style="width:105px;" title="Allocate Timeslot">Allocate Timeslot</button>

					<button id="BTN_AllocationClear"  class="bt" style="width:105px;" title="Clear Allocation">Clear Allocation</button>
					<!-- For testing purposes -->
					<!-- <button id="BTN_AddStaffPref"  class="bt" style="width:105px;" title="Clear and Add Staff Pref">Add Staff Pref</button> -->
				</div>
				<div style="float:right; padding-bottom:15px;">
					<?php
					echo isset($_SESSION["total_projects"]) ? "Total Projects: " . $_SESSION["total_projects"] : "" ;
					?>
				</div>
				<script type="text/javascript">
			
					$("#BTN_AllocationExaminer").on("click",function(){
						$.ajax({
							url: 'submit_allocate_examiner.php',
							data: {"AlgorithmType" : $( "#AlgorithmType" ).val(),"Total_BufferProjects":$( "#Total_BufferProjects" ).val()},
							type: 'POST',
							success: function (data) {
								console.log(data);
								console.log("Ajax post success! ");
								window.location.href = ("allocation.php?" + data);
							},
							error: function(data){
								console.log("Ajax post failed!");
							}
						});
					});
					$( "#allocateTimeSlotBtn" ).click (function(e) {

						$("#loadingdiv").show();
						$.ajax({
							url: 'submit_allocate_timeslot.php',
							type: 'GET',

							success: function (data) {

							
								console.log(data);
								
								window.location.href = ("allocation.php?" + data);
								$("#loadingdiv").hide();

							},
							error:function (data) {

								console.log("Server error");
								$("#loadingdiv").hide();
							}

						});
					});
					
					$("#BTN_AllocationClear").on("click",function(){
						$.ajax({
							url: 'submit_allocate_clear.php',
							processData: false,
							contentType: false,
							type: 'POST',
							success: function (data) {
								console.log(data);
								console.log("Ajax post success!");
								window.location.href = ("allocation.php?" + data);
							},
							error: function(data){
								console.log("Ajax post failed!");
							}
						});
					});
				</script>

				<table border="1" cellpadding="0" cellspacing="0" width="100%">
					<col width="12%" />
					<col width="22%" />
					<col width="22%" />
					<col width="4%"/>
					<col width="15%"/>
					<col width="15%" />
					<col width="10%" />

					<tr class="heading">
						<td>Project ID</td>
						<td>Supervisor</td>
						<td>Examiner</td>
						<td>Day</td>
						<td>Date</td>
						<td>Timeslot</td>
						<td>Room</td>
					</tr>
					
					<?php foreach ($rsAllocation as $row_rsAllocation) { ?>
					<tr <?php if ($row_rsAllocation['clash']=="1") echo 'class="clash_tr"';?> >
						<td><a href="allocation_edit.php?project=<?php echo $row_rsAllocation['project_id']; ?>" <?php if ($row_rsAllocation['clash']=="1") echo 'class="clash_td"';?> ><?php echo $row_rsAllocation['project_id']; ?></td>
							<td <?php if ($row_rsAllocation['clash']=="1") echo 'class="clash_td"';?> ><?php echo getStaff($row_rsAllocation['staff_id']); ?></td>
							<td <?php if ($row_rsAllocation['clash']=="1") echo 'class="clash_td"';?> ><?php echo getStaff($row_rsAllocation['examiner_id']); ?></td>
							<td <?php if ($row_rsAllocation['clash']=="1") echo 'class="clash_td"';?> ><?php echo ($row_rsAllocation['day'] == "" || $row_rsAllocation['day'] == -1) ? '-' : $row_rsAllocation['day']; ?></td>

							<td <?php if ($row_rsAllocation['clash']=="1") echo 'class="clash_td"';?> ><?php echo getActualDate($row_rsAllocation['day']);  ?></td>
							<td <?php if ($row_rsAllocation['clash']=="1") echo 'class="clash_td"';?> ><?php echo getTimeSlot($row_rsAllocation['day'], $row_rsAllocation['slot']); ?></td>
							<td <?php if ($row_rsAllocation['clash']=="1") echo 'class="clash_td"';?> ><?php echo getRoom($row_rsAllocation['room'], $row_rsAllocation['day']); ?></td>			
							<?php } ?>
						</table>
					</div>
			
		</div>
		<!-- InstanceEndEditable --> 
		
		<?php require_once('../../../footer.php'); ?>
	</div>
	
</body>
<!-- InstanceEnd -->
</html>

<?php
	$conn_db_ntu = null;
	unset($rsTimeslot);
	unset($rsRoom);
	unset($staffList);
	unset($rsAllocation);
?>