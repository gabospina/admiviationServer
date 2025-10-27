<?php
// training.php (FINAL VERSION USING ROLES)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION["HeliUser"])){
  header("Location: index.php");
  exit;
}
$page = "training";

// *** NEW: Include helpers and perform ROLE-BASED permission check ***
include_once "db_connect.php"; 
include_once "login_permissions.php"; // VERIFY PATH

$editorRoles = [
    'admin', 
    'manager', 
    'training manager',
    'admin pilot',
    'manager pilot',
    'training manager pilot'
];
$canEditSchedule = userHasRole($editorRoles, $mysqli);
  include_once "header.php";
?>
<style>
  /* Ensure events are visible and draggable in month view */
.fc-month-view .fc-event {
    cursor: move !important;
    z-index: 1000;
}

/* Fix for event dragging in month view */
.fc-event-dragging {
    opacity: 0.7;
    z-index: 1001 !important;
}

/* Ensure events have proper spacing in month view */
.fc-day-grid-event {
    margin: 1px 2px 0px !important;
}

/* Make sure events are visible */
.fc-event {
    border: 1px solid #3a87ad;
    font-size: 12px;
    padding: 1px 2px;
}

/* Visual indication for editable events */
.fc-event.editable {
    cursor: move !important;
}

.fc-event.not-editable {
    cursor: default !important;
    opacity: 0.8;
}

/* Highlight editable events on hover */
.fc-event.editable:hover {
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    transform: translateY(-1px);
}

/*  */

/* Button styles for the one-shot workflow */
.btn-warning {
    background-color: #f0ad4e;
    border-color: #eea236;
    color: #fff;
}

.btn-warning:hover {
    background-color: #ec971f;
    border-color: #d58512;
    color: #fff;
}

.btn-success {
    background-color: #5cb85c;
    border-color: #4cae4c;
    color: #fff;
}

.btn-success:hover {
    background-color: #449d44;
    border-color: #398439;
    color: #fff;
}

/* Visual feedback for editable events */
.fc-event.editable-event {
    cursor: move !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    transition: all 0.2s ease;
}

.fc-event.editable-event:hover {
    box-shadow: 0 4px 10px rgba(0,0,0,0.4);
    transform: translateY(-1px);
}
</style>

  <!-- Global Alert Container for showNotification -->
  <div id="global-alert-container" style="position: fixed; top: 70px; right: 20px; z-index: 1050; width: 350px; max-width: 90%;"></div>

  <div id="eventInfoPopover"></div>
    <div class="light-bg" id="calendarSection">
      <div class="inner-md">
        <div class="col-md-12 no-float">
          <div class="tabs">
            <div class="tab" data-tab-toggle="trainer">Trainers' Duty Schedule</div>
            <div class="tab active" data-tab-toggle="trainee">Trainees and Trainers</div>
          </div>
          <div class="tab-content"> 
          
          <?php
            // *** MODIFIED: Use the new permission variable for the Print button ***
            if ($canEditSchedule) {
              echo '<button class="btn btn-primary pull-right outer-right-xs outer-top-xxs" data-toggle="modal" data-target="#printModal">Print</button>';
            }
          ?>

            <div class="tab-pane inner-right-sm inner-left-sm" data-tab="trainer">
              <h1 class="page-header text-center">Manage Trainer Schedule</h1>
              <div class="row">
                <div class="col-md-12 no-float outer-bottom-xs">
                  <div id="trainer-calendar"></div>
                </div>
              </div>
            </div>

            <div class="tab-pane active inner-right-sm inner-left-sm" data-tab="trainee">
              <h1 class="page-header text-center">
                <?php echo $canEditSchedule ? 'Manage' : 'View'; ?> Trainee Schedule
              </h1>
              <div class="row">
                <div class="col-md-12 no-float outer-bottom-xs">
                  <div class="col-md-7 display-inline outer-bottom-xxs">
                    <div class="lbl display-inline outer-right-xxs">View As:</div>
                    <button class="btn btn-default view-as-btn display-inline active" data-value="trainee">Trainee</button>
                    <button class="btn btn-default view-as-btn display-inline" data-value="trainer">Trainer</button>
                  </div>

                  <!-- --- THIS IS THE NEW BUTTON --- -->
                  <!-- It's only shown to users who have editing permissions. -->
                  <?php if ($canEditSchedule): ?>
                  <div class="col-md-2 display-inline">
                      <button class="btn btn-warning" id="toggle-edit-mode-btn" data-mode="read">
                          <i class="fa fa-pencil"></i> Enable Drag & Drop
                      </button>
                  </div>
                  <?php endif; ?>
                  <!-- --- END OF NEW BUTTON --- -->


                  <div class="col-md-4 display-inline">
                    <div class="lbl display-inline outer-right-xxs">Search</div>
                    <input type= "text" class="form-control display-inline" id="search_trainees"/>
                  </div>
                  <div id="calendar"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php
      $adminLevel = intval($_SESSION["admin"]);
      if ($canEditSchedule) {
        echo '
        
        <div class="modal" id="editEvent" tabindex="-1" role="dialog" aria-labelledby="editEventCal" aria-hidden="true">
                <div class="modal-dialog" style="width: 50%;">
                <div class="modal-content">
                  <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                    <h1 class="modal-title">Edit Selection</h1>
                  </div>

                  <div class="modal-body">
                    <div class="container">
                      <h4 class="page-header">Current Selection: <span id="currentEventSelection"></span></h4>
                      
                      <div class="col-md-12 outer-bottom-xxs">
                        <h3 class="page-header">Change Selection</h3>

                        <div class="col-md-12 no-padding center-block outer-bottom-xxs">
                          <div class="col-md-12 no-padding no-margin">
                            
                          <div class="col-md-4 outer-bottom-xxs display-inline">
                              <div class="lbl">Start Date</div>
                              <input type="text" class="form-control" id="editStartDate" />
                            </div>
                            <div class="col-md-4 outer-bottom-xxs display-inline">
                              <div class="lbl">End Date</div>
                              <input type="text" class="form-control" id="editEndDate" />
                            </div>
                          </div>
                          
                          <div class="col-md-4">
                            <div class="lbl">TRI (Select Up to Two)</div>
                            <div id="triList" class="list"></div>
                          </div>
                          
                          <div class="col-md-4">
                            <div class="lbl">Trainees (Select Up to Four)</div>
                            <div id="traineeList" class="list"></div>
                          </div>
                          
                          <div class="col-md-4">
                            <div class="lbl">TRE</div>
                            <div id="treList" class="list"></div>
                          </div>
                        
                          </div>
                        <div class="text-right col-md-12 outer-top-xxs" >
                          <button class="btn btn-success btn-lg" id="confirmChange">Change</button>
                        </div>
                      </div>
                      <div class="col-md-12" style="border-top: 1px solid #ccc;">
                        <h3 class="text-center">Would you like to remove <span id="removePilotName"></span> on <span id="removePilotDates"></span></h3>
                      </div>
                    </div><!-- ENDS CONTAINER -->
                  </div>

                  <div class="modal-footer inner-right-md">
                    <button type="button" class="btn btn-lg btn-danger" id="confirmRemoval">Remove</button>
                    <button type="button" class="btn btn-lg btn-default" data-dismiss="modal" aria-hidden="true">Cancel</button>
                  </div>
                </div>
                </div>
              </div>

              <div class="modal" id="eventModal" tabindex="-1" role="dialog" aria-labelledby="editEventCal" aria-hidden="true">
                <div class="modal-dialog modal-lg" style="width: 50%;">
                  <div class="modal-content">

                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                      <h1 class="modal-title text-center">Flight Simulator Schedule</h1>
                    </div>

                    <div class="modal-body">
                      <div class="container">
                        <div class="col-md-12 outer-bottom-sm eventAvailSection">
                          <h3 class="text-center">
                            <span id="availabilityHeader"></span><br><br>
                            <span class="text-center startEndDates"></span>
                          </h3>
                          <div class="col-md-12 text-center">
                            <button class="btn btn-lg btn-success" id="confirmEnableAvailability">Enable</button>
                            <button class="btn btn-lg btn-danger" id="confirmDisableAvailability">Disable</button>  
                          </div>
                        </div>

                        <div class="col-md-12 eventDetailsSection">
                          <h3 class="page-header">Select Craft and Pilots</h3>
                          <div class="row outer-top-xxs">

                            <div class="col-md-4 outer-bottom-xxs display-inline">
                              <div class="lbl">Start Date</div>
                              <input type="text" class="form-control" id="addStartDate" />
                            </div>

                            <div class="col-md-4 outer-bottom-xxs display-inline">
                              <div class="lbl">End Date</div>
                              <input type="text" class="form-control" id="addEndDate" />
                            </div>

                            <div class="col-md-12 outer-bottom-xxs" style="padding-left: 0px;">
                              <div class="col-md-6">
                                <div class="lbl">Aircraft</div>
                                <select class="form-control" id="addEventCraft"></select>
                              </div>
                            </div>

                            <div class="col-md-12 outer-bottom-xxs" style="padding-left: 0px;">
                              <div class="col-md-4">
                                <div class="lbl">TRI (0-2)</div>
                                <div class="list" id="triListSection"></div>
                              </div>

                              <div class="col-md-4">
                                <div class="lbl">Trainees (1-4)</div>
                                <div class="list pilotListSection" id="TRIpilotListSection"></div>
                              </div>

                              <div class="col-md-4">
                                <div class="lbl">TRE (0-1)</div>
                                <div class="list" id="treListSection"></div>
                              </div>

                              <!-- <div class="col-md-12 outer-top-xs text-right">
                                <button class="btn btn-lg btn-danger" id="confirmAddTRI">Add TRI Selection</button>
                                <button class="btn btn-lg btn-default" data-dismiss="modal" aria-hidden="true">Cancel</button>
                              </div> -->
                            </div>
                            <!-- <div class="col-md-12 outer-bottom-xxs" style="padding-left: 0px; border-top: 1px solid #ccc;">
                              <div class="col-md-6">
                                <div class="lbl">TRE</div>
                                <div class="list" id="treListSection"></div>
                              </div>

                              <div class="col-md-6">
                                <div class="lbl">Trainee</div>
                                <div class="list pilotListSection" id="TREpilotListSection"></div>
                              </div>
                            </div> -->
                          </div>
                        </div>
                      </div><!-- ENDS CONTAINER -->
                    </div>
                    <div class="modal-footer inner-right-md">
                      <button class="btn btn-lg btn-danger" id="confirmAdd">Add Selection</button>
                      <button class="btn btn-lg btn-default" data-dismiss="modal" aria-hidden="true">Cancel</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- TRAINER MODALS ============================================ -->
              <div class="modal" id="trainerEventModal" tabindex="-1" role="dialog" aria-labelledby="editEventCal" aria-hidden="true">
                <div class="modal-dialog modal-lg" style="width: 50%;">
                  <div class="modal-content">
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                      <h1 class="modal-title text-center">Flight Simulator Schedule</h1>
                    </div>
                    <div class="modal-body">
                      <div class="container">
                        <div class="col-md-12 outer-bottom-sm eventAvailSection">
                          <h3 class="text-center">
                            <span id="availabilityHeader"></span><br><br>
                            <span class="text-center startEndDates"></span>
                          </h3>
                          <div class="col-md-12 text-center">
                            <button class="btn btn-lg btn-success" id="confirmEnableAvailabilityTrainer">Enable</button>
                            <button class="btn btn-lg btn-danger" id="confirmDisableAvailabilityTrainer">Disable</button>  
                          </div>
                        </div>
                        <div class="col-md-12 eventDetailsSection">
                          <h3 class="page-header">Select Trainer And Position</h3>
                          <div class="row outer-top-xxs">
                            <div class="col-md-12 outer-bottom-xxs" style="padding-left: 0px;">
                              <div class="col-md-6">
                                <div class="lbl">Trainers</div>
                                <div class="list" id="addTrainerList"></div>
                              </div>
                              <div class="col-md-4">
                                <div class="lbl">Position</div>
                                <select id="addTrainerPosition" class="form-control">
                                  <option value="tri">TRI</option>
                                  <option value="tre">TRE</option>
                                  <option value="tri/tre">TRI/TRE</option>
                                </select>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div><!-- ENDS CONTAINER -->
                    </div>

                    <div class="modal-footer inner-right-md">
                      <button class="btn btn-lg btn-danger" id="confirmAddTrainer">Add Trainer</button>
                      <button class="btn btn-lg btn-default" data-dismiss="modal" aria-hidden="true">Cancel</button>
                    </div>
                  </div>
                </div>
              </div>

              
              <div class="modal" id="editTrainerEvent" tabindex="-1" role="dialog" aria-labelledby="editEventCal" aria-hidden="true">
                <div class="modal-dialog" style="width: 50%;">
                <div class="modal-content">
                  <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                    <h1 class="modal-title">Edit Trainer Selection</h1>
                  </div>
                  <div class="modal-body">
                    <div class="container">
                      <h4 class="page-header">Current Selection: <span id="currentEventSelectionTrainer"></span></h4>
                      <div class="col-md-12 outer-bottom-xxs">
                        <h3 class="page-header">Change Selection</h3>
                        <div class="col-md-12 no-padding center-block outer-bottom-xxs">
                          <div class="col-md-6">
                            <div class="lbl">Trainer</div>
                            <div id="changeTrainerList" class="list"></div>
                          </div>
                          <div class="col-md-4">
                            <div class="lbl">Position</div>
                            <select id="changeTrainerPosition" class="form-control">
                              <option value="tri">TRI</option>
                              <option value="tre">TRE</option>
                              <option value="tri/tre">TRI/TRE</option>
                            </select>
                            <div class="lbl">Duty Start</div>
                            <input type="text" class="form-control" id="changeTrainerStart" />
                            <div class="lbl">Duty End</div>
                            <input type="text" class="form-control" id="changeTrainerEnd" />
                            <div class="text-right col-md-12 no-padding outer-top-sm">
                              <button class="btn btn-success btn-lg col-md-12" id="confirmTrainerChange">Change</button>
                            </div>
                          </div>
                        </div>
                        <div class="text-right col-md-12 outer-top-xxs" >
                          &nbsp;
                        </div>
                      </div>
                      <div class="col-md-12" style="border-top: 1px solid #ccc;">
                        <h3 class="text-center">Would you like to remove <span id="removeTrainerName"></span> on <span id="removeTrainerDates"></span></h3>
                      </div>
                    </div><!-- ENDS CONTAINER -->
                  </div>
                  <div class="modal-footer inner-right-md">
                    <button class="btn btn-lg btn-danger" id="confirmTrainerRemoval">Remove</button>
                    <button class="btn btn-lg btn-default" data-dismiss="modal" aria-hidden="true">Cancel</button>
                  </div>
                </div>
                </div>
              </div>

              <!-- PRINT MODAL =============================================================== -->
              <div class="modal" id="printModal" tabindex="-1" role="dialog" aria-labelledby="printmod" aria-hidden="true">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                      <h1 class="modal-title text-center">Print Schedules</h1>
                    </div>
                    <div class="modal-body">
                      <div class="col-md-12 no-float outer-bottom-xxs">
                        <div class="lbl">Type</div>
                        <select class="form-control" id="printType">
                          <option selected disabled>Select Type</option>
                          <option value="trainerDuty">Trainer Duty Schedule</option>
                          <option value="training">Training Schedule</option>
                        </select>
                        <div class="printDates">
                          <div class="lbl">Start</div>
                          <input type="text" class="form-control datepicker" id="printStart"/>
                          <div class="lbl">End</div>
                          <input type="text" class="form-control datepicker" id="printEnd"/>
                        </div>
                        <div class="lbl">Output Format</div>
                        <div class="col-md-4">
                          <select class="form-control" id="outputFormat">
                            <option value="xlsx">Excel</option>
                            <option value="pdf">PDF</option>
                          </select>
                        </div>
                      </div>
                    </div>
                    <div class="modal-footer inner-right-md">
                      <button class="btn btn-lg btn-primary" id="printBtn">Print</button>
                      <button class="btn btn-lg btn-default" data-dismiss="modal" aria-hidden="true">Cancel</button>
                    </div>
                  </div>
                </div>
              </div>';
      }
    ?>
              
    
    <div class="modal" id="yearLoadingModal" tabindex="-1" role="dialog" aria-labelledby="editEventCal" aria-hidden="true">
      <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-body">
          <div class="container">
            <h2 class="text-center">Loading</h2>
          </div><!-- ENDS CONTAINER -->
       </div>
      </div>
      </div>
    </div>

              
<!-- ... all your training.php HTML, modals, etc., end here ... -->
      </div>
    </div>

<?php
// =========================================================================
// === DEFINE PAGE-SPECIFIC STYLESHEETS & SCRIPTS (TRAINING) - FINAL     ===
// =========================================================================

$page_stylesheets = [
    // Libraries for this page
    // 'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.1/bootstrap3-editable/js/bootstrap-editable.min.js',
];

$page_scripts = [
    
    // 'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.1/bootstrap3-editable/js/bootstrap-editable.min.js',
    
    // 1. Generic Utilities (can be used anywhere in your application)
    'training-date-utils.js',             // NEW: Load our date helpers first
    'training-utilities.js',       // Load training-specific helpers
    'training-modal-utilities.js',

    // 2. Module-specific files (will be populated next)
    'training-ajax-operations.js', 
    'training-modal-handlers.js',  
    'training-event-handlers.js',
    'training-year-view.js',

    'training-calendar.js',        
    
    // 3. Main Initializer (must be LAST)
    'training-main.js'
    
];
?>

<!-- Pass the PHP permission variable to JavaScript -->
<script>
    // const canUserEditSchedule =;
</script>

<script>
// Add this script block in your training.php file
var canUserEditSchedule = <?php echo $canEditSchedule ? 'true' : 'false'; ?>;
</script>

<?php
include_once "footer.php"; 
?>

<!-- FOR WHATEVER REASSON YEAR CALEBDAR WORK WITH THIS hrref, not at $page_stylesheets  -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.css" integrity="sha512-liDnOrsa/NzR+4VyWQ3fBzsDBzal338A1VfUpQvAcdt+eL88ePCOd3n9VQpdA0Yxi4yglmLy/AmH+Lrzmn0eMQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />

