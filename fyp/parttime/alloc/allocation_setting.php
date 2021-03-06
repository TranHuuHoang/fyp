<?php require_once('../../../Connections/db_ntu.php');
	  require_once('../../../CSRFProtection.php');
	  require_once('../../../Utility.php');?>


<?php
    $csrf = new CSRFProtection(); 
	
	
	
	$MIN_ROOMS		= 10;
	
	$query_rsSettings = "SELECT * FROM ".$TABLES['allocation_settings_general_part_time']." as g";
	
	$query_rsRoom_day1 = "SELECT * FROM ".$TABLES['allocation_settings_room_part_time']." WHERE day =1 ORDER BY `id` ASC";
	$query_rsRoom_day2 = "SELECT * FROM ".$TABLES['allocation_settings_room_part_time']." WHERE day =2 ORDER BY `id` ASC";
	$query_rsRoom_day3 = "SELECT * FROM ".$TABLES['allocation_settings_room_part_time']." WHERE day =3 ORDER BY `id` ASC";
	//$query_numRoom 	  = "SELECT count(*) as count FROM ".$TABLES['allocation_settings_room_part_time']." as r ORDER BY `id` ASC";
	$query_projCount  = "SELECT count(*) as count FROM ".$TABLES['fyp_assign_part_time']." WHERE complete = 0";
	$query_OtherSettings ="SELECT * FROM ".$TABLES['allocation_settings_others']." WHERE type= 'PT'";
	
	try
	{
		$settings 	= $conn_db_ntu->query($query_rsSettings)->fetchall();
		//$numRoom 	= $conn_db_ntu->query($query_numRoom)->fetch();
		//$rooms		= $conn_db_ntu->query($query_rsRoom);
		$rooms_day1		= $conn_db_ntu->query($query_rsRoom_day1)->fetchall();
		$rooms_day2		= $conn_db_ntu->query($query_rsRoom_day2)->fetchall();
		$rooms_day3		= $conn_db_ntu->query($query_rsRoom_day3)->fetchall();
		
		$projCount	= $conn_db_ntu->query($query_projCount)->fetch();
		$otherSettings = $conn_db_ntu->query($query_OtherSettings)->fetch();
	}
	catch (PDOException $e)
	{
		die($e->getMessage());
	}
	if ($rooms_day1 != null || sizeof($rooms_day1) > 0) { 
		$rooms_day1 = (array)json_decode($rooms_day1[0]["roomArray"]);
		$count_day1  = sizeof($rooms_day1);

	}
	else {
		$count_day1 =0;
	}
	if ($rooms_day2!= null || sizeof($rooms_day2) > 0) { 
		$rooms_day2  = (array)json_decode($rooms_day2[0]["roomArray"]);
		$count_day2  =  sizeof($rooms_day2);
	}
	else {
		$count_day2 =0;
	}
	if ($rooms_day3!= null || sizeof($rooms_day3) > 0) { 
		$rooms_day3  = (array)json_decode($rooms_day3[0]["roomArray"]);
		$count_day3  =  sizeof($rooms_day3);
	}
	else {
		
		$count_day3 =0;
	}
	//Exam Year & Sem settings
	$YEAR_RANGE			= 505;
	$today				= new DateTime();
	$curWorkYear		= $today->format('Y') % 100 - 1;
	$examYearValue	 	= $curWorkYear*100 + ($curWorkYear+1);			//Current Year (Default)
	$examYearStart 		= $examYearValue - $YEAR_RANGE;	//Current Year
	$examYearEnd 		= $examYearValue + $YEAR_RANGE;
	
	$examSemStart		= 1;				//Range
	$examSemEnd			= 2;
	$examSemValue		= 1;				//Default Sem
	
	/* Parse Settings */
   

	try
	{	
		if ($otherSettings || sizeof($otherSettings)>0) {
			$NO_OF_DAYS = $otherSettings ['alloc_days'];
		}
		else {
				$NO_OF_DAYS =0;
		}
	
		$settings_examyear= $otherSettings['exam_year'];
		if ($settings_examyear >= $examYearStart && $settings_examyear <= $examYearEnd)	//In Range Year
			$examYearValue = $settings_examyear;
			
		//$settings_examsem	= $settings['exam_sem'];
		$settings_examsem= $otherSettings['exam_sem'];
		
		
		if ($settings_examsem >= $examSemStart && $settings_examsem <= $examSemEnd)		//In Range Sem
		{$examSemValue = $settings_examsem;}
	 
	}
	catch(Exception $e)
	{
		die($e->getMessage());
	}

	
	$proj_to_assign	= $projCount['count'];
	
	$roomCount			= max($count_day1, $MIN_ROOMS);
	$roomCount_day2     = max($count_day2, $MIN_ROOMS);
    $roomCount_day3     = max($count_day3, $MIN_ROOMS);
	
	function yearToStr($yearInput)
	{
		$yr1 = round($yearInput / 100, 0, PHP_ROUND_HALF_DOWN);
		$yr2 = $yearInput % 100;
		
		return $yr1 . "/" . $yr2;
	}
	
	function generateYearSelect($id, $selected)
	{
		global $examYearStart, $examYearEnd;
		
		echo '<select id="'.$id.'" name="'.$id.'">';
		for ($curYear=$examYearStart; $curYear <= $examYearEnd; $curYear+=101)
		{
			$isSelected = ($curYear == $selected) ? "selected" : "";
			echo '<option value="'.$curYear.'"'.$isSelected.'>'.yearToStr($curYear).'</option>';
		}
		echo '</select>';
	}
	
	function generateSemSelect($id, $selected)
	{
		global $examSemStart, $examSemEnd;
		
		echo '<select id="'.$id.'" name="'.$id.'">';
		for ($curSem=$examSemStart; $curSem <= $examSemEnd; $curSem++)
		{
			$isSelected = ($curSem == $selected) ? "selected" : "";
			echo '<option value="'.$curSem.'"'.$isSelected.'>'.$curSem.'</option>';
		}
		echo '</select>';
	}
	
	function generateTimeSelect($id, $start, $end, $interval, $selected)
	{
		$start_time 	= DateTime::createFromFormat('H:i:s', $start);
		$end_time		= DateTime::createFromFormat('H:i:s', $end);
		$time_interval	= new DateInterval('PT'.$interval.'M');
		
		echo '<select id="'.$id.'" name="'.$id.'">';
		for ($curTime=$start_time; $curTime <= $end_time; $curTime->add($time_interval))
		{
			$isSelected = ($curTime == $selected) ? "selected" : "";
			echo '<option value="'.$curTime->format('H:i:s').'"'.$isSelected.'>'.$curTime->format('H:i').'</option>';
		}
		echo '</select>';
	}
	
	function generateDurationSelect($id, $start, $end, $interval, $selected)
	{
		$start_time 	= DateTime::createFromFormat('i', $start);
		$end_time		= DateTime::createFromFormat('i', $end);
		$time_interval	= new DateInterval('PT'.$interval.'M');
		
		echo '<select id="'.$id.'" name="'.$id.'">';
		for ($curTime=$start_time; $curTime <= $end_time; $curTime->add($time_interval))
		{
			$isSelected = ($curTime->format('i') == $selected) ? "selected" : "";
			echo '<option value="'.$curTime->format('i').'"'.$isSelected.'>'.$curTime->format('i').' Minutes</option>';
		}
		echo '</select>';
	}
	
	if (isset ($_REQUEST["no_of_days_room"])) {
			$noOfDays = $_REQUEST["no_of_days_room"];
			initRoomTable($noOfDays);
			return;
		}
		 
		if (isset ($_REQUEST["no_of_days_timeslot"])) {
			$noOfDays = $_REQUEST["no_of_days_timeslot"];
			generateTSTable($noOfDays);
			return;
		}
		function generateAllocDate () {
			global $settings;
			$allocDateInputStr = "";
			$formattedStartDate = "";
			if (sizeof($settings)>0) {
				$startDate = DateTime::createFromFormat('Y-m-d', $settings[0]['alloc_date']);
				$formattedStartDate = $startDate->format('Y-m-d');
				
			}
			$allocDateInputStr = '<input type="text" id="alloc_date" name="alloc_date" value="'. $formattedStartDate.'" required />';
			echo $allocDateInputStr;
		}
		function generateTSTable ($noOfDays) {
			global $settings;
			$timeSlotTableStr ="";
			$chkStr = "";
			
			for ($i=0;$i<$noOfDays;$i++) {
				
				$actualDay = $i+1;
				if ($i< sizeof($settings) ){
					$startDate = DateTime::createFromFormat('Y-m-d', $settings[$i]['alloc_date']);
					$startTime = DateTime::createFromFormat('H:i:s', $settings[$i]['alloc_start']);
					$endTime   = DateTime::createFromFormat('H:i:s', $settings[$i]['alloc_end']);
					$timeslotDuration 	= new DateInterval('PT'.$settings[$i]['alloc_duration'].'M');
					if ($settings[$i]['opt_out'] == 1) {
							$chkStr = "checked";
					 } 
					 else {
						 $chkStr ="";
					 }
					
				}
				else {
					$startDate 			= new DateTime();
					$startTime 			= DateTime::createFromFormat('H:i:s', '08:30:00');
					$endTime			= DateTime::createFromFormat('H:i:s', '17:30:00');
					$timeslotDuration 	= new DateInterval('PT30M');
	
				}
				$duration =  $timeslotDuration->format('%i');
				$id_name="tab-".$actualDay;
				 
				$timeSlotTableStr .= '';
				$class="tab-content"; 
				if($i==0){
					$class .= " current";
				}
				$timeSlotTableStr .= '<tbody id="'. $id_name .'" class="'.$class .'">';
				$timeSlotTableStr .= '<tr><td style="padding:5px;">Opt Out Option:</td>';
				$timeSlotTableStr .= '<td><label><b><input type="checkbox" name="opt_out[]" value="'. $i.'"';
				
				
				$timeSlotTableStr .= $chkStr. '/></b></label></td></tr>';
				$timeSlotTableStr .= '<tr><td style="padding:5px;">Date:</td>';
				$timeSlotTableStr .= '<td><span id="next_date'. $i.'">';
				
				
				
				$timeSlotTableStr .= $startDate->format('d/m/Y'). '</span>';
                $timeSlotTableStr .= '<input type ="hidden" id="day'.$i .'" name="alloc_days[]" value="'.$startDate->format('Y-m-d') .'"/></td></tr>';                                                      
                
				$timeSlotTableStr .= '<tr><td style="padding:5px;">Start Time:</td>';
				$startTimeID = "start_time[]";
                $timeSlotTableStr .= '<td>'. generateTimeSelect($startTimeID, '08:30:00', '17:30:00', '30',$startTime) . '</td></tr>';
				
				
				$timeSlotTableStr .= '<tr><td style="padding:5px;">End Time:</td>';
				$endTimeID = "end_time[]";
                $timeSlotTableStr .= '<td>'. generateTimeSelect($endTimeID, '08:30:00', '17:30:00', '30',$endTime) . '</td></tr>';
				$timeSlotTableStr .= '<tr><td style="padding:5px;">Timeslot Duration:</td>';
				$durationID = "duration[]";
                $timeSlotTableStr .= '<td>'.  generateDurationSelect($durationID, '20', '40', '10',$duration) . '</td></tr></tbody>';	
				
			}
			echo $timeSlotTableStr ;
		}
		
		
		
		function initRoomTable ($noOfDays) {
			global $settings;
			$roomTableHTMLStr ="";
			$disabledStr = "";
			$noOfRmAdded =5;
			for ($dayIndex=0;$dayIndex<$noOfDays;$dayIndex++) {
				 $actualDay = $dayIndex +1;
				 if ($dayIndex ==0 ) {
						   
						$roomTableHTMLStr .= '<div id = "day-' . $actualDay . '" class = "room-content current">';
					}
				else {
						$roomTableHTMLStr .= '<div id ="day-' . $actualDay . '" class = "room-content">';
				 }
					if ($dayIndex< sizeof($settings) ) {
						if ($settings[$dayIndex]['opt_out'] == 1) {
							$disabledStr = "disabled";
						}	
						else {
								$disabledStr = "";
						}
					}
					    $roomTableHTMLStr .= '<table id ="room_table' . $actualDay .
						'" border="0" style= "text-align:left;">';
						$roomTableHTMLStr .= '<col width = "30"/>';
						$roomTableHTMLStr .= '<col width = "380"/>';
						$roomTableHTMLStr .= initRooms($dayIndex, $noOfDays);  
						$roomTableHTMLStr .= '</table><input id ="addRoomBtn' . $actualDay.  '"';   
						$roomTableHTMLStr .= 'type = "button" class="bt" title="Add more rooms" value="Add Rooms"' . $disabledStr;
						
						$roomTableHTMLStr .= '/></div>';  
						  
						
				   }
			echo $roomTableHTMLStr;
			
			
		}
	   function initRooms($dayIndex, $noOfDays){
		
		//global $rooms;
		global $MIN_ROOMS,$settings, $TABLES, $conn_db_ntu;
		$actualDay = $dayIndex +1;
		$query_rsRoom = "SELECT * FROM ".$TABLES['allocation_settings_room_part_time']." WHERE day =? ORDER BY `id` ASC";
		
		$stmt = $conn_db_ntu->prepare($query_rsRoom);
		$stmt->bindParam(1, $actualDay);
		$stmt->execute();
		$rooms_day	= $stmt->fetch();
		
		
		if ($rooms_day && sizeof($rooms_day) > 0) { 
			$rooms_day = (array)json_decode($rooms_day["roomArray"]);
			$rmcount_day  =  sizeof($rooms_day);
			}
		else {
			$rmcount_day  =0;
			}
		
		$roomCount = 1;
		$roomContentStr = "";
		$disabledStr = "";
		for ($i=1;$i<=$rmcount_day;$i++)	
		{
			
			$roomContentStr .= '<tr><td class="room_td">'.$roomCount.'.</td>';
			
			if (sizeof($settings) >= $noOfDays) {
						
                        if ($settings[$dayIndex]['opt_out'] == 1)
                        {
							$disabledStr = "disabled";
							//$roomContentStr.= '<td class="room_td"><input style="width:200px; //background:#ededed;" id="room'.$actualDay. '_'.$roomCount.'" //name="room'.$actualDay. '_'.$roomCount.'" value="'.$rooms_day[$i].'" //readonly="readonly" />';
						}
                        else {
							$disabledStr = "";

						}
            }          
						//else
                       // {
							//$roomContentStr.= '<td class="room_td"><input style="width:200px;" id="room'.$actualDay. '_'.$roomCount.'" name="room'.$actualDay. '_'.$roomCount.'" value="'.$rooms_day[$i].'" />';
						//}
						$roomContentStr .='<td class="room_td"><input type="text" style="width:200px;" id="room'.$actualDay. '_'.$roomCount.'" name="room'.$actualDay. '_'.$roomCount.'" value="'.$rooms_day[$i].'" '.$disabledStr. '/>';
			
			
			$roomContentStr.= '</td></tr>';
			$roomCount++;
		}
		
		//Fill Gaps
		while ($roomCount<=$MIN_ROOMS) // min rooms = 10, if less than 10 then fill in with empty text boxes until 10.
		{
			$roomContentStr.= '<tr><td class=\"room_td\">'.$roomCount.'.</td>';
			if (sizeof($settings) >= $noOfDays) {
                        if ($settings[$dayIndex]['opt_out'] == 1) 
                        {
							$disabledStr = "disabled";
							//$roomContentStr.= '<td class="room_td"><input style="width:200px; //background:#ededed;" id="room'.$actualDay. '_'.$roomCount.'" //name="room'.$actualDay. '_'.$roomCount.'" readonly="readonly" />';
						}
                        else {
							$disabledStr = "";
						}
                        //else
                       //{
						//	$roomContentStr.= '<td class="room_td"><input style="width:200px;" //id="room'.$actualDay. '_'.$roomCount.'" name="room'.$actualDay. '_'.$roomCount.'" />';
						//}
			}			
			
			$roomContentStr.= '<td class="room_td"><input type="text" style="width:200px;" //id="room'.$actualDay. '_'.$roomCount.'" name="room'.$actualDay. '_'.$roomCount.'" ' .$disabledStr. '/></td></tr>';
			
			
			$roomCount++;
		}
		
		return $roomContentStr;
		
	}
	
       
	
	function enoughSlots()
	{
		global $proj_to_assign;
		return true;
		
		//return $proj_to_assign;
	}

	
?>

<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>

	<title>Allocation Settings</title>
	<?php require_once('../../../head.php'); ?>
	<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css">
	<style>
		td .room_td
		{
			padding-bottom:10px;
		}
                
         tbody{
			width: 350px;
            height:150px;
			margin: 0 auto;
		}
		ul.tabs{
			margin: 0px;
			padding: 0px;
			list-style: none;
		}
		ul.tabs li{
			background: none;
			color: #444;
			display: inline-block;
			padding: 10px 15px;
			cursor: pointer;
		}

		ul.tabs li.current{
			background: #ededed;
			color: #444;
		}

		.tab-content{
			display: none;
			background: #ededed;
			padding: 15px;
		}

		.tab-content.current{
			display: inherit;
		}
                
		ul.room_tabs{
			margin: 0px;
			padding: 0px;
			list-style: none;
		}
		ul.room_tabs li{
			background: none;
			color: #222;
			display: inline-block;
			padding: 10px 15px;
			cursor: pointer;
		}

		ul.room_tabs li.current{
			background: #ededed;
			color: #222;
		}

		.room-content{
			display: none;
			background: #ededed;
			padding: 15px;
		}

		.room-content.current{
			display: inherit;
		}
         table tr {
			height:40px;
		}         
            
	</style>
	
	<script type="text/javascript">
	
	    //global variables to keep track of no of rooms(textboxes) for each day
		
		var roomCount_Day1, roomCount_Day2, roomCount_Day3;
	
		$(document).ready(function(){
			roomCount_Day1 = <?php echo $roomCount; ?>;
            roomCount_Day2 = <?php echo $roomCount_day2; ?>;
            roomCount_Day3 = <?php echo $roomCount_day3; ?>;
			
			$( "#alloc_date" ).datepicker({ 
				
				dateFormat: "yy-mm-dd",
				
			});	
			  var no_of_days = <?php echo $NO_OF_DAYS; ?>;
              generateTabs(no_of_days);   
			 
		});
		
      
			function calculateNextDate(){
                   
				   
					var start_date = $("#alloc_date").val();
					
                   var date = new Date(start_date);
                   var day_1 = new Date(date.setDate(date.getDate()));
                   var day_2 = new Date(date.setDate(date.getDate() + 1));
                   var day_3 = new Date(date.setDate(date.getDate() + 1.5));
                   
                   var day1_string = day_1.getDate() + '/' +(day_1.getMonth()+1) + '/' + day_1.getUTCFullYear();
                   var day2_string = day_2.getDate() + '/' +(day_2.getMonth()+1) + '/' + day_2.getUTCFullYear();
                   var day3_string = day_3.getDate() + '/' +(day_3.getMonth()+1) + '/' + day_3.getUTCFullYear();
                    
                   $("#next_date0").html(day1_string);
                   $("#next_date1").html(day2_string);
                   $("#next_date2").html(day3_string);
                   
                   var day1_value = day_1.getUTCFullYear() + '-' +(day_1.getMonth()+1) + '-' + day_1.getDate();
                   var day2_value = day_2.getUTCFullYear() + '-' +(day_2.getMonth()+1) + '-' + day_2.getDate();
                   var day3_value = day_3.getUTCFullYear() + '-' +(day_3.getMonth()+1) + '-' + day_3.getDate();
                   
                   $("#day0").val(day1_value);
                   $("#day1").val(day2_value);
                   $("#day2").val(day3_value);


                }
                
                $(function () {
                
                    $("#alloc_date").on("change", function() {
						calculateNextDate();
					});
					
                })
                
             
						$(function(){ // tabs function for both time alloc and room
	
                        // when you click the ul for timeslot
                        $('ul.tabs').on("click", "li", function(){ 
		
							var tab_id = $(this).attr('data-tab');
				  
                            var room_ul_id, room_div_id;
                            
                            //remove current from ul and div for tabs
                            $('ul.tabs li').removeClass('current');
                            $('.tab-content').removeClass('current');
                            
                            //remove current from ul and div for rooms
                            $('ul.room_tabs li').removeClass('current');
                            $('.room-content').removeClass('current');
                            
                            switch(tab_id)
                            {
                                case 'tab-1': room_ul_id = 'room_day1'; room_div_id ='day-1'; break;
                                case 'tab-2': room_ul_id = 'room_day2'; room_div_id ='day-2'; break;
                                case 'tab-3': room_ul_id = 'room_day3'; room_div_id ='day-3'; break;
                            }
                            
                            //for the room side - assign current
                            $("#"+room_div_id).addClass('current');
                            $("#"+room_ul_id).addClass('current');
                            
                            // for the tabs side - assign current
                            $(this).addClass('current');
                            $("#"+tab_id).addClass('current');   
						});
                    
                    // when you click rooms setting tabs
                      $('ul.room_tabs').on("click", "li", function(){
                            var room_id = $(this).attr('data-tab');
                            var tab_ul_id, tab_div_id;
                            
                            //remove current from ul and div for rooms
                            $('ul.room_tabs li').removeClass('current');
                            $('.room-content').removeClass('current');
                            
                            //remove current from ul and div for tabs
                            $('ul.tabs li').removeClass('current');
                            $('.tab-content').removeClass('current');
                            
                              switch(room_id)
                            {
                                case 'day-1': tab_ul_id = 'tab1'; tab_div_id ='tab-1'; break;
                                case 'day-2': tab_ul_id = 'tab2'; tab_div_id ='tab-2'; break;
                                case 'day-3': tab_ul_id = 'tab3'; tab_div_id ='tab-3'; break;
                            }
                            

                            // for the tabs side - assign current
                            $("#"+tab_ul_id).addClass('current');
                            $("#"+tab_div_id).addClass('current');
                            
        
                            //for the room side - assign current
                            $(this).addClass('current');
                            $("#"+room_id).addClass('current');
                            
                            
                    });
                          
                    
                });
				
				function generateTabs(noOfDays) {
					var roomTableTabHTMLStr ="";
					var timeSlotTabHTMLStr ="";
				   for (var i=1;i<=noOfDays;i++) {
					   if (i==1 ) {
						   
						roomTableTabHTMLStr += "<li data-tab=\"day-"+ i + "\" class=\"room-link current\" id= \"room_day" + i +"\"><b>Day " + i + "</b></li>";
						timeSlotTabHTMLStr += "<li data-tab=\"tab-"+ i + "\" class=\"tab-link current\" id= \"tab" + i +"\"><b>Day " + i + "</b></li>";
					 }
					 else {
						roomTableTabHTMLStr += "<li data-tab=\"day-"+ i + "\" class=\"room-link \" id= \"room_day" + i +"\"><b>Day " + i + "</b></li>";
						timeSlotTabHTMLStr += "<li data-tab=\"tab-"+ i + "\" class=\"tab-link\" id= \"tab" + i +"\"><b>Day " + i + "</b></li>";
					 }
				   }
				  
				    $("#roomTabs").html(roomTableTabHTMLStr);
					 $("#timeSlotTabs").html(timeSlotTabHTMLStr);
				}
				function regenerateRoomTable (no_of_days) {
					
					
					var dataArr =  {"no_of_days_room": no_of_days};
			
					$.ajax({
						type: "POST",
						url: "allocation_setting.php",
						data: dataArr,
						success: function(data){
					 
						$("#roomTableGroup").html("");
						$("#roomTableGroup").html(data);
						
					
					},
					error: function(msg){
						alert("error occurred");
						 		 	
					}	
			
				});
				
				}
              function regenerateTimeSlotTable (no_of_days) {
				    
						var dataArr =  {"no_of_days_timeslot": no_of_days};
			
						$.ajax({
							type: "POST",
							url: "allocation_setting.php",
							data: dataArr,
							success: function(data){
					 
							$("#tsSettingsBody").html("");
							$("#tsSettingsBody").html(data);
						
					
						},
						error: function(data){
							alert("error occurred");
										
						}	
			
						});
				
				}
	</script>
</head>

<body>
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
			<h1>Allocation Settings for Full Time Projects </h1>
			<?php 
			
			if(isset($_REQUEST['save']))
				echo "<p class='success'> Allocation settings saved.</p>";
			if(isset($_REQUEST['clear']))
				echo "<p class='warn'> Allocation settings changes cleared.</p>";
			if(isset($_REQUEST['reset']))
				echo "<p class='warn'> Allocation settings reset to default.</p>";
			
			if(!enoughSlots())
				echo "<p class='warn'> Your current settings do not provide sufficient rooms/slots for timetable allocation!</p>";
			
			if (isset ($_REQUEST['validate'])) {
				echo "<p class='warn'> CSRF validation failed.</p>";	
			}
			else  {?>
			
			<div id="topcon">
				<form action="submit_saveas.php" method="post">
					<?php $csrf->echoInputField();?>
					<table id="settings_table" border="0" style="margin-top:15px;">	
						<tr>
							<td valign="top" style="text-align:left;">
								<div id="exam_settings">
									<h3 style="padding-bottom:10px;">Exam Settings</h3>
									<table id="timeslot_table" border="0" width="406" style="text-align:left;">
										<col width="110" />
										<col width="220" />
										<tr>
											<td style="padding:5px;">Exam Year:</td>
											<td><?php generateYearSelect('exam_year', $examYearValue); ?></td>
										</tr>
										
										<tr>
											<td style="padding:5px;">Exam Sem:</td>
											<td><?php generateSemSelect('exam_sem', $examSemValue); ?>
											</td>
										</tr>
                                                                                <tr>
											<td style="padding:5px;">Number of Days:</td>
											<td><input type="number" id="number_of_days" name="number_of_days" min="1" max="10" value="<?php echo $NO_OF_DAYS; ?>"/></td>
										</tr>
									</table>
								</div>
							</td>
							
							<td valign="top" style="text-align:left; padding-left:70px;" rowspan="2">
                                                                
                                 <h3 style="padding-bottom:10px;">Room Settings</h3>
									<ul id="roomTabs" class="room_tabs">
									</ul>                         
									<div id= "roomTableGroup">     
										<?php initRoomTable($NO_OF_DAYS) ?>                      
									</div>
                          
							</td>
						</tr>
						
						<tr>
							<td valign="top" style="text-align:left;">
									<div id="timeslot_settings">
									<h3 style="padding-bottom:10px;">Timeslot Settings</h3>
									<table id="timeslot_table" border="0" width="406" style="text-align:left;">
										<col width="110" />
										<col width="220" />
                                       <tr>
											<td style="padding:5px;">Allocation Date:</td>
											
											<td><?php generateAllocDate() ?></td>
										</tr>
										<tr>
											<td colspan="2">                                      
                                                <ul id= "timeSlotTabs" class="tabs">
                                          
                                                 </ul>
                                             </td>
										</tr>     
                
									<tr>
										<td>
											<table id = "tsSettingsBody">
												<?php   generateTSTable($NO_OF_DAYS);?>
											</table>
										</td>
									</tr>
									

									</table>
					  <label><input type="checkbox" name="apply_to_all[]" /> Apply to all</label>   
                     </div>  
							</td>
							
							<td></td>
						</tr>
					</table>
                                
					
					<div style="float:right; padding-top:25px;">
                                            
						<a href="submit_resetas.php" class="bt" title="Reset to default" style="width:130px;">Reset to default</a>
						<!-- <a href="allocation_setting.php?clear=1" class="bt" title="Clear all changes">Clear Changes</a> -->
						<input type="submit" title="Save all changes" value="Save Changes" class="bt" style="font-size:12px !important;"/>
					</div>
				</form>
			</div>
			<?php }?>
			<br/>
		</div>
		<script>
		function addRoom_Day1(val, elementId,  day) // add rooms for day 1
		{
               
			var table = document.getElementById(elementId);

			for(var i=0;i<val;i++)
			{
				var row = table.insertRow(table.rows.length);
				var td_index = row.insertCell(0);
				var td_field = row.insertCell(1);
				roomCount_Day1++;
				td_index.innerHTML = roomCount_Day1 + ".";
				td_index.className = 'room_td';
				
				td_field.innerHTML = "<input style=\"width:200px;\" id=\"room" + day + "_"+roomCount_Day1+"\" name=\"room" + day + "_" +roomCount_Day1+"\">";
				td_field.className = 'room_td';
				
			}
		}  
		function addRoom_Day2(val, elementId,  day) // add rooms to day 2
		{
   
			var table = document.getElementById(elementId);

			for(var i=0;i<val;i++)
			{
				var row = table.insertRow(table.rows.length);
				var td_index = row.insertCell(0);
				var td_field = row.insertCell(1);
				roomCount_Day2++;
				td_index.innerHTML = roomCount_Day2 + ".";
				td_index.className = 'room_td';
				
				td_field.innerHTML = "<input style=\"width:200px;\" id=\"room" + day +"_" +roomCount_Day2+"\" name=\"room" + day + "_" +roomCount_Day2+"\">";
				td_field.className = 'room_td';
				
			}
		}
                
        function addRoom_Day3(val, elementId,  day) // add rooms to day 3
				{
    
			var table = document.getElementById(elementId);

			for(var i=0;i<val;i++)
			{
				var row = table.insertRow(table.rows.length);
				var td_index = row.insertCell(0);
				var td_field = row.insertCell(1);
				roomCount_Day3++;
				td_index.innerHTML = roomCount_Day3 + ".";
				td_index.className = 'room_td';
				
				td_field.innerHTML = "<input style=\"width:200px;\" id=\"room" + day +"_"  +roomCount_Day3+"\" name=\"room" + day + "_" +roomCount_Day3+"\">";
				td_field.className = 'room_td';
				
			}
		}
		 $("#number_of_days").change(function(){
					$("#dayErrorMsg").html ("");
			       if (this.value < 1 || this.value > 3) {
						$("#dayErrorMsg").html ("Please enter a valid number between 1 and 3!");
					}
					else {
						generateTabs(this.value);
						regenerateRoomTable (this.value);
						regenerateTimeSlotTable(this.value);
						//reset room count for each day since the no of days change
						roomCount_Day1 = <?php echo $roomCount; ?>;
						roomCount_Day2 = <?php echo $roomCount_day2; ?>;
						roomCount_Day3 = <?php echo $roomCount_day3; ?>;
					}
		});
		  //find some way to loop?
		  $("#roomTableGroup").on("click", "#addRoomBtn1",function () {
			
			addRoom_Day1 (5, "room_table1", 1);
			
		});
	
		  $("#roomTableGroup").on("click", "#addRoomBtn2",function () {
			
			addRoom_Day2 (5, "room_table2", 2);
			
		});
         $("#roomTableGroup").on("click", "#addRoomBtn3",function () {
			
			addRoom_Day3 (5, "room_table3", 3);
			
		});   
		
					
		</script>
		<!-- InstanceEndEditable --> 
		<?php require_once('../../../footer.php'); ?>
	</div>
</body>
</html>

<?php
	unset($settings);
	unset($rooms);
	$conn_db_ntu = null;
?>

