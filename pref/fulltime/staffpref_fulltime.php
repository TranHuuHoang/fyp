<?php require_once('../../Connections/db_ntu.php'); 
	 require_once('../entity.php'); 
	 require_once('../../CSRFProtection.php');
	 require_once('../../Utility.php');?>
	  
<?php
	$csrf = new CSRFProtection();
	
	
    
	$_REQUEST['csrf'] = $csrf->cfmRequest();
	

	$staffid = $_SESSION['id'];
	
	
	$staffPrefOpened = false;
	
	// Exam Year & Sem settings
	$today						= new DateTime();
	$defaultExamSemValue		= 1;
	$defaultExamYearValue	 	= $today->format('Y');	//Current Year (Default)
	
	try {
				
			$stmt = $conn_db_ntu->prepare("SELECT * FROM ".$TABLES['staff']." WHERE id = ?");
			$stmt->bindParam(1, $staffid);
			$stmt->execute();
			$session_staff  = $stmt->fetch();
		}
		catch (PDOException $e) {
			die($e->getMessage());
		}
	
		$query_rsOtherSettings = "SELECT * FROM ".$TABLES['allocation_settings_others']." as g WHERE type = 'FT'";
		
		$otherSettings 	= $conn_db_ntu->query($query_rsOtherSettings )->fetch();
		
		

		/* Check if staff pref is open for selection */
		try
		{
			$examYearValue 		= (int)$otherSettings['exam_year'];
			$examSemValue 		= (int)$otherSettings['exam_sem'];
			
			
			$startDate 		 	= DateTime::createFromFormat('Y-m-d H:i:s', $otherSettings['pref_start']);
			$endDate 			= DateTime::createFromFormat('Y-m-d H:i:s', $otherSettings['pref_end']);
			

			if ($startDate != null && $endDate != null)
			{
				$startDate = $startDate->format('Y-m-d');
				$endDate = $endDate->format('Y-m-d');
				
				$requestDate = new DateTime(); 	// Today
				$requestDate = $requestDate->format('Y-m-d');
				
				if ( ($requestDate >= $startDate) && 
					 ($requestDate <= $endDate) ){
					$staffPrefOpened = true;
				}
			}
		}
		catch(Exception $e)
		{
			$staffPrefOpened = false;
		}
		
		$query_rsSupervisingProject = "SELECT p1.project_id, p1.year, p1.sem, p1.staff_id, p2.title, p3.examine_year, p3.examine_sem FROM ".$TABLES['fyp_assign']." as p1 LEFT JOIN ".$TABLES['fyp']." as p2 ON p1.project_id=p2.project_id LEFT JOIN ". $TABLES['fea_projects'] . " as p3 on p1.project_id = p3.project_id WHERE p1.complete = 0 AND p1.staff_id= ? AND p3.examine_year =?  AND p3.examine_sem = ?". " ORDER BY p1.project_id";
		
		try {
			$stmt = $conn_db_ntu->prepare($query_rsSupervisingProject);
			$stmt->bindParam(1, $staffid);
			$stmt->bindParam(2, $examYearValue);
			$stmt->bindParam(3, $examSemValue);
			
			$stmt->execute();
			$rsSupervisingProject 	= $stmt->fetchAll();
			
		}
		catch (PDOException $e) {
			die($e->getMessage());
		}
		
		
		//Default Values
		if ($startDate == null || $endDate == null){
			$staffPrefOpened = false;
		}
		if ($staffPrefOpened)
		{
			$query_rsStaff	 	= "SELECT s.id as staffid, s.name as staffname, s.position as salutation FROM ".$TABLES['staff']." as s";
			
			//not used 
			//$query_rsProject  	= "SELECT p2.project_id as pno, p2.staff_id as staffid, p1.title as ptitle FROM ".$TABLES['fyp_assign']." as p2 LEFT JOIN ".$TABLES['fyp']." as p1 ON p2.project_id=p1.project_id WHERE p2.complete = 0 ORDER BY p2.project_id ASC";

			$query_rsProject = "SELECT p3.project_id as pno, p2.staff_id as staffid, p1.title as ptitle FROM ".$TABLES['fea_projects']." as p3 LEFT JOIN ".$TABLES['fyp_assign']." as p2 ON p3.project_id = p2.project_id LEFT JOIN ".$TABLES['fyp']." as p1 ON p2.project_id = p1.project_id WHERE p2.complete = 0 and p3.examine_year = ".$examYearValue." and p3.examine_sem = ".$examSemValue." ORDER BY p3.project_id ASC ";
			
			$query_rsArea	= "SELECT * FROM ".$TABLES['interest_area']." where title <> '-' ORDER BY title ASC, `key` ASC";
			
			$query_staffProjPref	= "SELECT * FROM ".$TABLES['staff_pref']." WHERE staff_id = ? AND (prefer LIKE 'SCE%' OR prefer LIKE 'SCSE%')  AND archive =0 ORDER BY choice ASC";
			
			$query_staffAreaPref	= "SELECT * FROM ".$TABLES['staff_pref']." as sp INNER JOIN ". $TABLES['interest_area'] ." as ia ON sp.prefer= ia.key AND archive =0 WHERE staff_id = ?  ORDER BY choice ASC";
			
			try {
				$rsProject 	= $conn_db_ntu->query($query_rsProject);
				$rsStaff	= $conn_db_ntu->query($query_rsStaff);
				$rsArea 	= $conn_db_ntu->query($query_rsArea);
			
				$stmt = $conn_db_ntu->prepare($query_staffProjPref);
				$stmt->bindParam(1, $staffid);
				$stmt->execute();
				$proj_prefs = $stmt->fetchAll();
				
				$stmt = $conn_db_ntu->prepare($query_staffAreaPref);
				$stmt->bindParam(1, $staffid);
				$stmt->execute();
				$area_prefs = $stmt->fetchAll();
				
				
				
		    }
			catch (PDOException $e) {
				die($e->getMessage());
			}
		
			//Staff
			$staffList = array();
			foreach($rsStaff as $staff) { //Index Staff by staffid
				$staffList[ $staff['staffid'] ] = new Staff($staff['staffid'],
															$staff['salutation'],
															$staff['staffname']);
			}
			
			
			
			//Projects
			$projectList = array();
			foreach($rsProject as $project) { //Index Project By pno
				$projectList[ $project['pno'] ] = new Project(	$project['pno'], 
																$project['staffid'],
																"",	//To be replaced if there's examiner already
																$project['ptitle'] );
			}
			
			foreach($projectList as $project) {
				foreach ($rsSupervisingProject as $row_rsProject) {
					if ($row_rsProject['project_id'] == $project->getID()) {
						
						$id =$row_rsProject['project_id'];
						//remove projects that are being supervised by staff
						unset($projectList["$id"]);
						
					}
				}
			}
			//Areas
			$areaList = array();
			foreach($rsArea as $area) { 	//Index area by key
				$areaList[ $area['key'] ] = new Area( $area['key'], 
													  $area['title'] );
			}
		}
		
	
	function getStaff($s)
	{
		global $staffList;
		
		if ($s === null || $s == -1) return "-";
		if (!array_key_exists($s, $staffList)) return "?";
		return $staffList[$s]->toString();
	}
	
	function getProject($s)
	{
		global $projectList;
		
		if ($s === null || $s == -1) return null;
		if (!array_key_exists($s, $projectList)) return null;
		return $projectList[$s];
	}
	
	function getArea($s)
	{
		global $areaList;
		
		if ($s === null || $s == -1) return null;
		if (!array_key_exists($s, $areaList)) return null;
		return $areaList[$s];
	}
	$conn_db_ntu = null;
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	
	<title>Staff Preference</title>
	<?php require_once('../../head.php'); ?>	

	<script src="https://code.jquery.com/jquery-3.3.1.js"></script>
	<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
	<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js"></script>

	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.1/css/bootstrap.css"/>
	<link rel="stylesheet" href="https://cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css"/>

    <script src="http://maxcdn.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js"></script> 
	
	<!-- <script src="http://cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"></script>
	<script src="http://cdn.datatables.net/1.10.16/js/dataTables.bootstrap.min.js"></script> -->
	<script src="https://cdn.datatables.net/rowreorder/1.1.0/js/dataTables.rowReorder.min.js"></script> 

	<!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" integrity="sha384-WskhaSGFgHYWDcbwN70/dfYBj47jz9qbsMId/iRN3ewGhXQFZCSftd1LZCfmhktB" crossorigin="anonymous"> 
    <!-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css"/> -->
	<!-- <link rel="stylesheet" href="https://cdn.datatables.net/1.10.16/css/dataTables.bootstrap.min.css"/>
	<link rel="stylesheet" href="https://cdn.datatables.net/rowreorder/1.1.0/css/rowReorder.dataTables.min.css"/> -->

		<?php
		if (!$staffPrefOpened){
			$unavailableUrl = "../staffpref_unavailable.php";
			header("location: " . $unavailableUrl);
		    exit;
		}
	?>
	
	<style>
		#content {
			padding-left:0px;
			margin-left:0px;
			width:1024px;
		}
		
		.selbtn:hover {
			text-decoration:none;
		}
		
		.selected {
			background-color: brown;
			color: #FFF;
		}
		
		.pref_table tbody tr:hover {
			cursor: pointer;
		}
		
		.editor tbody tr:hover {
			cursor: pointer;
		}
		
		.table_cell {
			padding: 15px;
		}

		.dataTable.editor > thead > tr > th[class*="sort"]:after{
			content: "" !important;
		}
		
		.input-sm {
			min-height: 22px;
		}
		hr {
			height: 1px;
			background-color: black; /* Modern Browsers */
		}
		
		.thCol {
			background-color: lightgrey;
			text-align : center;
			
		}
		
		.sorting_1:hover {
			background-color: lightgrey;
			font-size: 120%;
			font-weight: bold;
			color: white;
		}
		
		.hidden {
			display: none;
		}
	</style>
	
	<script type="text/javascript">
		/* $(document).ready(function() is a jQuery that detects state of readiness bcuz a page can't be manipulated safely until the document is "ready". 
		the codes within the function will only run once the page document object model is ready for javascript code to execute */
		$(document).ready(function() {
			var update_sp = false;
			var update_ap = false;
      var save = false;
			var modified = false;
            // enable warning when leaving the page
            /* 1. window object represents an open window in a browser, the browser creates one window object for the HTML document
            2. on() method attaches one or more handlers for the selected elements and child elements, 
            syntax: 
            $(selector).on(event,childSelector,data,function,map)
            3. onbeforeunload event occurs when the document is about to be unloaded, this event allows you to display a message in a confirmation dialog box to inform the user whether he/she wants to stay or leave the current page*/
            

			//Tabbed Tables
			/* jQuery selectors 
			1. syntax: [attribute=value] , $("[href='default.htm']"), All elements with a href attribute value equal to "default.htm"
			a[data-toggle="tab"] , all elements with href and data-toggle attribute value equal to "tab"
			2. 'shown.bs.tab' is a bootstrap event.
			When showing a new tab, the events fire in the following order:
			1) hide.bs.tab (on the current active tab)
			2) show.bs.tab (on the to-be-shown tab)
			3) hidden.bs.tab (on the previous active tab, the same one as for the hide.bs.tab event)
			4) shown.bs.tab (on the newly-active just-shown tab, the same one as for the show.bs.tab event): this event fires on tab show after a tab has been shown. Use event.target and event.relatedTarget to target the active tab and the previous active tab (if available) respectively.
			$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
			  e.target // newly activated tab
			  e.relatedTarget // previous active tab
			})
			*/
			$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
				/* $.fn.dataTable.tables is using DataTables.Api to get a list of existing DataTables on a page, particularly in situations where the table has scrolling enabled and needs to have its column widths adjusted when it is made visible. 
				syntax: tables([visible])
				parameters: 
				1. visible : a boolean value this options is used to indicate if you want all tables on the page should be returned false or true 
				e.g to adjust column widths when a table is made visible in a Bootstrap tab
				$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
				    $.fn.dataTable
				        .tables( { visible: true, api: true } )
				        .columns.adjust();
				})*/
				$.fn.dataTable.tables( {visible: true, api: true} ).columns.adjust();
			} );

			
			//Data Tables
			/* Integrating Bootstrap and DataTables extensions. DataTables is a table enhancing plug-in for JQuery Javascript library, adding sorting, paging and filtering abilities to plain HTML tables.
			Syntax: 
			1. Initializing DataTables in a single line of Javascript, 
			$('table').dataTable();
			$('table.pref_table').DataTable : all elements table with class "pref_table"
			 */
			$('table.pref_table').DataTable( {
				scrollY:        400, // enable scrolling
				scrollCollapse: false,
				paging:         false, //disable paging
				ordering:		false,
				filter:			true, //enable filtering
				// when info option is enabled, Datatables will show information about the table including information about filtered data if that action is being performed
				info:			false, 
				/* initComplete to know when your table has been fully initialized, data loaded and drawn. it is an option in DataTables */ 
				  initComplete: function () {
				  	/* call API functions inside the DataTables callback functions(e.g initComplete), you can use the Javascript special variable "this" to access the API "this.api()" to create an API instance */
					//add drop down list for supervisor name column					
                    addDropDown(this.api().column(1));
					
				  }
            } );
        
		function addDropDown(column) {
						
						var selectBox = $('<select style ="width:90%"><option value="">Select </option></select>')
						/* 1. appending dropdown list to the header cell used for a column.
						2. empty() removes all child nodes and content from the selected elements*/
						.appendTo( $(column.header()).empty() ) 
						/* on change event, this method will be called */
						.on( 'change', function () {
						/* $.fn.dataTable.util.escapeRegex is used to escape input so formatted strings with characters that have special meaning in a regular expression will simply perform a character match */
                        var val = $.fn.dataTable.util.escapeRegex(
                            $(this).val() // Return the value attribute, syntax: $(selector).val()
                        );
  
  						/* 1. column.search() - get the currently applied column search 
  						2. .search(input [, regex [, smart [, caselnsen]]])
  						parameters: 
  						1) input: string
  						2) regex: boolean, treat as a regular expression when set true
  						3) smart: boolean, perform smart search when set true
  						4) caseInsen: boolean, do case-insensitive match when set true */
                        column
                            .search( val ? '^'+val+'$' : '', true, false )
                            .draw(); // redraw the table
                    } );
  				/* column.data() - used to get the data used for the cells in the column matched by the selector from DataTables 
  				.unique() - to remove duplicate items in the data 
  				.sort() - to sort the data 
  				.each(function(value,index)) - iterate over the contents of the API result set */ 
				column.data().unique().sort().each( function ( d, j ) {
						selectBox.append( '<option value="'+d+'">'+d+'</option>' )
					} );
		}
			
		
			$('#selected_proj_table').DataTable( {

				scrollY:        400,
				scrollCollapse: false,
				paging:         false,
				filter:			false,
				info:			false,
				columnDefs: 	[ { orderable: false, targets: [0,1,2,3] } ],
				rowReorder: 	true
			} );
	
			$('#selected_area_table').DataTable( {
				scrollY:        400,
				scrollCollapse: false,
				paging:         false,
				filter:			false,
				info:			false,
				columnDefs: 	[ { orderable: false, targets: [0,1] } ],
				rowReorder: 	true
			} );
			
			//Event Handlers
			var selectedHandler = function() {
				$(this).addClass('selected').siblings().removeClass('selected');
			};

			//General Bindings
			$('.pref_table tbody tr').bind( "click", selectedHandler );
			
			//Project Table
			function UpdateProjTable(value, reduce_shift)
			{
                modified = true;
				update_sp = false;
				
				var table = $('#selected_proj_table').DataTable();
				table.rows().every( function () {
					var d = this.data();
					if (d[0] > value)
					{
						if (reduce_shift) d[0]--;
						var project_id = d[1].match(/<input.*\/>(.*)/);
						d[1] = '<input type="hidden" id="projpref'+d[0]+'" name="projpref'+d[0]+'" value="'+project_id[1]+'"/>'+project_id[1];
					}
					table
						.row( this )
						.data( d )
						.draw();
				} );
			}
			
			function AddSelectedProject(project_id, supervisor, title)
			{
				var selected_project_table = $('#selected_proj_table').DataTable();
				var new_index = selected_project_table.data().length+1;
				var input_project = '<input type="hidden" id="projpref'+new_index+'" name="projpref'+new_index+'" value="'+project_id+'"/>'+project_id;
				/* table.row.add(data) - add a new row to the table 
				parameters: 
				1. data - this maybe an array, object, javascript object instance or a tr tag element */
				var new_row = selected_project_table.row.add( [new_index, input_project, supervisor, title] );
				new_row.draw();
				//Editor table event binding
				/*$(selector).bind(event,data,function,map) - attach a click event to element with class editor tbody tr and call for function "selectedHandler"
				parameter: 
				1. event - required. specifies one or more events to attach to the elements 
				2. data - optional. specifies additional data to pass along to the function 
				3. function - required. specifies the function to run when the event occurs */
				$('.editor tbody tr').bind("click", selectedHandler );
			}
			
			<?php
				foreach($proj_prefs as $project)
				{
					$cur_project = getProject($project['prefer']);
					if ($cur_project == null) continue;
					$js = sprintf("AddSelectedProject('%s', '%s', '%s');",
									$cur_project->getID(),
									getStaff($cur_project->getStaff()),
									$cur_project->getTitle());
					echo $js;
				}
			?>
			$('#selected_proj_table').DataTable().on('row-reorder', function ( e, diff, edit ) {
				update_sp = true;
			} );

			$('#selected_proj_table').DataTable().on('order', function () {
				if (update_sp)
					UpdateProjTable(0, false);
			} );

			$('#addProjectBtn').on('click', function(e){
				//$("#proj_table tbody tr.selected") to get the selected row of table with id = "proj_table"
				var selected = $("#proj_table tbody tr.selected");
				//$(selector).html() - returns content
				if (selected.html() == null) return;
				
				var project_id 	= selected.find("td").eq(0).text(); //select the first <td> element
				var supervisor 	= selected.find("td").eq(1).text();
				var title 		= selected.find("td").eq(2).text();
				
				var exists = false;
				//loop thru each list in table with id selected_proj_table to see if the project id already exist 
				var temp = $("#selected_proj_table tbody").find("tr").each( function(){
					var $this = $(this);
					if(project_id == $('td:eq(1)', $this).text()){
						exists = true;
						return false;
					}
				});

				if (!exists)
				{
                    modified = true;
					AddSelectedProject(project_id, supervisor, title);
				}
				else
				{
					alert("Project [" + project_id + "] is already on the list!");
				}
			});
			
			$('#removeProjectBtn').on('click', function(e){
				var selected = $("#selected_proj_table tbody tr.selected");
				if (selected.html() == null) return;
				
				var selected_project_table = $('#selected_proj_table').DataTable();
				UpdateProjTable(selected_project_table.row(selected).data()[0], true);
				selected_project_table.row( selected ).remove().draw();
			});
			
			function AddSelectedArea(area_id, title)
			{
				var selected_project_table = $('#selected_area_table').DataTable();
				var new_index = selected_project_table.data().length+1;
				var input_area = '<div class="hidden"><input type="hidden" id="areapref'+new_index+'" name="areapref'+new_index+'" value="'+area_id+'">'+area_id+'</input></div>'+title;
				var new_row = selected_project_table.row.add( [new_index, input_area] );
				new_row.draw();
				//Editor table event binding
				$('.editor tbody tr').bind("click", selectedHandler );
			}
			
			<?php
				foreach($area_prefs as $area)
				{
					$cur_area = getArea($area['prefer']);
					if ($cur_area == null) continue;
					$js = sprintf("AddSelectedArea('%s', '%s');",
									$cur_area->getID(),
									$cur_area->getTitle());
					echo $js;
				}
			?>
			/* row-reorder provides the end user with the ability to click and drag a row in the table and change its position */
			$('#selected_area_table').DataTable().on('row-reorder', function ( e, diff, edit ) {
				update_ap = true;
			} );

			/* when the table is order, the codes within the function will be called */
			$('#selected_area_table').DataTable().on('order', function () {
				if (update_ap)
					UpdateAreaTable(0, false);
			} );
      
			//Area Table
			function UpdateAreaTable(value, reduce_shift)
			{
                modified = true;
				update_ap = false;
				
				var table = $('#selected_area_table').DataTable();
				table.rows().every( function () {
					var d = this.data();
					if (d[0] > value)
					{
						if (reduce_shift) d[0]--;
						var area_id = d[1].match(/<div class="hidden"><input.*\>(.*)<\/input><\/div>(.*)/);
						d[1] = '<div class="hidden"><input type="hidden" id="areapref'+d[0]+'" name="areapref'+d[0]+'" value="'+area_id[1]+'">'+area_id[1]+'</input></div>'+area_id[2];
					}
					table
						.row( this )
						.data( d )
						.draw();
				} );
			}
			
			$('#addAreaBtn').on('click', function(e){
				var selected = $("#area_table tbody tr.selected");
				if (selected.html() == null) return;
				
				var area_id 	= selected.find("td").eq(0).text();
				var title 		= selected.find("td").eq(1).text();
				
				var exists = false;
				var temp = $("#selected_area_table tbody").find("tr").each( function(){
					var $this = $(this);
					if((area_id+title) == $('td:eq(1)', $this).text()){
						exists = true;
						return false;
					}
				});

				if (!exists)
				{
                    modified = true;
					AddSelectedArea(area_id, title);
				}
				else
				{
					alert("Area [" + title + "] is already on the list!");
				}
			});
			
			$('#removeAreaBtn').on('click', function(e){
				var selected = $("#selected_area_table tbody tr.selected");
				if (selected.html() == null) return;
				
				var selected_area_table = $('#selected_area_table').DataTable();
				UpdateAreaTable(selected_area_table.row(selected).data()[0], true);
				selected_area_table.row( selected ).remove().draw();
			});
			
			$(window).on('beforeunload', function ()
      {
          if($("input[name=save]").val() === "false")
            return false;
      });
			
		} );
		function saveChanges(e)
      {
        if(confirm("Save changes?"))
        {
          // return true here to save
          $("input[name=save]").val("true");
          return true;
        };
        return false;
      }
      
	</script>
</head>

<body style="background-color: #dce7f3;">
	<!-- need to include header manually so that the javascript does not clash --> 
   <div class="container-fluid bg-dark text-white" style="background-color: white; opacity: 0.9; filter: alpha(opacity=90);">
			<h4 style="font-family: 'Poppins', sans-serif;font-size: 1.2em;
    font-weight: 300; line-height: 1.7em;padding:4px">SCSE | FYP Examiner Allocation System</h4>
			
	                <?php if (isset($_SESSION['success'])) {
	                    //echo "<p class='success'>[Login] ".$_SESSION['success']."</p>";
	                    unset ($_SESSION['success']);
	                    }
	                        if (isset($_SESSION['displayname'])){
	                            $displayname = trim($_SESSION['displayname'], '#');
	                            echo "<p class='credentials' style='color: white; float:right; margin-top:-42px'>Welcome, ".$displayname. " <a href='/logout.php' title='Logout'>
	                            <img src='/images/logout.png' width='25px' height='25px' alt='Logout'/></a></p>";

	                            } 
	                ?>         
	        
			
	</div>
	<br/>
	<div class="container col-sm-11 col-sm-11">
		<h3>Staff Preference for Full Time Projects</h3>
		<br/>
		<?php if(isset($_REQUEST['save']))
					echo "<p class='success'> Preferences saved.</p>";
				if(isset($_REQUEST['call']))
					echo "<p class='warn'> All preferences cleared.</p>";
				if(isset($_REQUEST['clear']))
					echo "<p class='warn'> Preference changes cleared.</p>";
				if (isset ($_REQUEST['validate']) || isset ($_REQUEST['csrf'])) {
					    echo "<p class='warn'> CSRF validation failed.</p>";	
				}
	            else {?>
					<!-- Supervising Projects -->
					<h4><u>Supervising Projects</u></h4>
					<p>You are currently supervising the following projects (to be examined in this semester):</p>
					<table border="1" cellpadding="0" cellspacing="0" width="100%" style="background-color: white; opacity: 0.9; filter: alpha(opacity=90);">
						<col width="10%" />
						<col width="10%" />
						<col width="10%" />
						<col width="10%" />
						<col width="10%" />
						<col width="50%" />
				
						<tr>
							<td class="bg-dark text-white text-center">Project ID</td>
							<td class="bg-dark text-white text-center">Year</td>
							<td class="bg-dark text-white text-center">Semester</td>
							<td class="bg-dark text-white text-center">Exam Year</td>
							<td class="bg-dark text-white text-center">Semester</td>
							<td class="bg-dark text-white text-center">Project Title</td>
							
						</tr>
						<?php foreach ($rsSupervisingProject as $row_rsProject) { ?>
						<tr>	   
							<td class="text-center"><?php echo $row_rsProject['project_id']; ?></td>
							<td class="text-center"><?php echo $row_rsProject['year']; ?></td>
							<td class="text-center"><?php echo $row_rsProject['sem']; ?></td>	
							<td class="text-center"><?php echo $row_rsProject['examine_year']; ?></td>
							<td class="text-center"><?php echo $row_rsProject['examine_sem']; ?></td>
							<td class="text-center"><?php echo $row_rsProject['title']; ?></td>
							
						</tr>
						<?php } ?>
					</table>
					<hr/>

					<h4><u>Preference Selection</u></h4>
					<p>Please select the projects and areas that you are interested in.</p>
					<p class="desc" style="padding-left:0px; color:#222; font-size:14px;">
					Notes: <br/><br/>
					1. You may choose projects and areas from the list, or begin typing keywords related to the projects to see related list.<br/><br/>
					2. You may re-arrange your choices under the 'No' column, with the most preferred at No. 1.<br/><br/>
					3. There is no limit on the number of preferences selected.<br/><br/>
					4. Please note that if you did not choose any preferences, you will be randomly allocated a project to examine.
					<br/><br/>
					</p>
					
					<form action="submitpref.php" method="post" onsubmit="return saveChanges();">
						<?php $csrf->echoInputField();?>
						<input name="staffid" id="staffID" type="text" value="<?php echo $staffid; ?>" style="display:none;" />
			  
						<!-- TABS -->
						<ul class="nav nav-tabs" id="myTab" role="tablist">
							<li class="nav-item">
								<a class="nav-link active" href="#tab-table1" role="tab" data-toggle="tab">Project Preference</a>
							</li>
							<li class="nav-item">
								<a class="nav-link" href="#tab-table2" role="tab" data-toggle="tab">Area Preference</a>
							</li>
						</ul>
						
						<div class="tab-content">
							<!--Project Preference-->
							<div class="tab-pane active" id="tab-table1">
								<table id="proj_frame" border="1" cellpadding="0" cellspacing="0" width="100%" style="background-color: white; opacity: 0.9; filter: alpha(opacity=90);"> 
								<col width="48%" />
								<col width="4%" />
								<col width="48%" />
								
								<tr class="bg-dark text-white text-center">
								   <td>Available Projects</td>
								   <td></td>
								   <td>Selected Projects</td>
								</tr>
						
								<td class="table_cell">
									<table id="proj_table" class="table table-bordered pref_table" cellspacing="0"  width="100%">
										<col width="40%" />
										<col width="45%" />
										<col width="43%" />
										
										<thead>
											<tr>
												<th class="bg-dark text-white text-center">Project ID</th>
												<th class="bg-dark text-white text-center">Supervisor</th>
												<th class="bg-dark text-white text-center">Title</th>
											</tr>
											<tr>
											<th class="bg-dark text-white text-center"></th>
											<th class="bg-dark text-white text-center"></th>
											<th class="bg-dark text-white text-center"></th>
											
											</tr>
										</thead>
										
										<tbody>
											<?php foreach ($projectList as $project) { ?>
											<tr>
												<td ><?php echo $project->getID(); ?></td>
												<td><?php echo getStaff($project->getStaff()); ?></td>
												<td><?php echo $project->getTitle(); ?></td>
											</tr>
											<?php } ?>
										</tbody>
									</table>
								</td>
								
								<td class="text-center">
									<a id="addProjectBtn" class="bt selbtn" title="Add To Selection" style="width:50%;">&gt;&gt;</a><br/><br/>
									<a id="removeProjectBtn" class="bt selbtn" title="Remove From Selection" style="width:50%;">&lt;&lt;</a>
								</td>
								
								<td class="table_cell" width="100%">
									<table id="selected_proj_table" class="table table-bordered editor" cellspacing="0" style="width:100%">
										<col width="10%"/>
										<col width="25%"/>
										<col width="30%"/>
										<col width="35%"/>
										
										<thead>
											<tr>
												<th class="bg-dark text-white text-center" width="10%">No</th>
												<th class="bg-dark text-white text-center" width="25%">Project ID</th>
												<th class="bg-dark text-white text-center" width="30%">Supervisor</th>
												<th class="bg-dark text-white text-center" width="35%">Title</th>
											</tr>
										</thead>
										
										<tbody>
										</tbody>
									</table>
								</td>
								
								</table>
							</div>
							
							<!--Area Preference-->
							<div class="tab-pane" id="tab-table2">
								<table id="area_frame" border="1" cellpadding="0" cellspacing="0" width="100%" style="background-color: white; opacity: 0.9; filter: alpha(opacity=90);">
								<col width="48%" />
								<col width="4%" />
								<col width="48%" />
								
								<tr class="bg-dark text-white text-center">
								   <td>Available Areas</td>
								   <td></td>
								   <td>Selected Areas</td>
								</tr>
						
								<td class="table_cell">
									<table id="area_table" class="table table-bordered pref_table" cellspacing="0">
										<thead>
											<tr>
												<th class="thCol hidden">Area ID</th>
												<th class="bg-dark text-white text-center">Area</th>
											</tr>
											<tr>
												<th class="thCol hidden"></th>
												<th class="bg-dark text-white text-center"></th>
											</tr>
										</thead>
										
										<tbody>
											<?php foreach ($areaList as $area) { ?>
											<tr>
												<td class="hidden"><?php echo $area->getID(); ?></td>
												<td><?php echo $area->getTitle(); ?></td>
											</tr>
											<?php } ?>
										</tbody>
									</table>
								</td>
								
								<td class="text-center">
									<a id="addAreaBtn" class="bt selbtn" title="Add To Selection" style="width:50%;">&gt;&gt;</a><br/><br/>
									<a id="removeAreaBtn" class="bt selbtn" title="Remove From Selection" style="width:50%;">&lt;&lt;</a>
								</td>
								
								<td class="table_cell">
									<table id="selected_area_table" class="table table-bordered editor" cellspacing="0">
										<col width="10%" />
										<col width="90%" />
										
										<thead>
											<tr>
												<th class="bg-dark text-white text-center">No</th>
												<th class="bg-dark text-white text-center">Area</th>
											</tr>
										</thead>
										
										<tbody>
										</tbody>
									</table>
								</td>
								
								</table>
							</div>
						</div>
						
						<!--Buttons-->
						<div style="float:right; padding-top:25px;">
							<a href="clearpref.php" class="btn bg-dark text-white text-center" title="Clear all saved preferences" style="font-size:12px;">Delete All</a>
							<a href="staffpref_fulltime.php?clear=1" class="btn bg-dark text-white text-center" title="Clear all selected preferences" style="font-size:12px;">Clear Changes</a>
							<input type="submit" class="btn bg-dark text-white text-center" title="Save all selected preferences" value="Save Changes" style="font-size:12px !important;"/>
						</div>
						<br/><br/><br/>
					</form>	
				
	             <?php }?>
	</div>		


	<!-- InstanceEndEditable --> 
	<?php require_once('../../footer.php'); ?>
	 		
	
</body>
</html>