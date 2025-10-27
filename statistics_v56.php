<?php
  if (session_status() == PHP_SESSION_NONE) {
      session_start();
  }
  if(!isset($_SESSION["HeliUser"])){
    header("Location: index.php");
    exit();
  }

// require_once is safe to use here. It ensures the DB connection is ready.
require_once "db_connect.php"; 

$company_id_for_limits = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 0;

// --- Array to hold limits for the modal (with defaults) ---
$modal_pilot_limits = [
    'max_in_day' => 'N/A', 'max_last_7' => 'N/A', 'max_last_28' => 'N/A',
    'max_last_365' => 'N/A', 'max_duty_in_day' => 'N/A',
    'max_duty_7' => 'N/A', 'max_duty_28' => 'N/A', 'max_duty_365' => 'N/A'
];

if ($company_id_for_limits > 0) {
    $sql = "SELECT max_in_day, max_last_7, max_last_28, max_last_365, 
                   max_duty_in_day, max_duty_7, max_duty_28, max_duty_365 
            FROM pilot_max_times 
            WHERE company_id = ? LIMIT 1";
            
    $stmt_limits = $mysqli->prepare($sql);
    if ($stmt_limits) {
        $stmt_limits->bind_param("i", $company_id_for_limits);
        if ($stmt_limits->execute()) {
            $result_limits = $stmt_limits->get_result();
            if ($row_limits = $result_limits->fetch_assoc()) {
                foreach ($row_limits as $key => $value) {
                    if (array_key_exists($key, $modal_pilot_limits) && $value !== null) {
                        $modal_pilot_limits[$key] = $value;
                    }
                }
            }
        }
        $stmt_limits->close();
    }
}

// =========================================================================
// === STEP 2: Include the Header (Displays the top part of the page)    ===
// =========================================================================
$page = "statistics";
include_once "header.php";
?>

<!-- ========================================================================= -->
<!-- === statistics.php - PAGE-SPECIFIC STYLES                             === -->
<!-- ========================================================================= -->

<style>
    #graphSection {
        width: 100%;
        height: 400px;
        min-height: 350px;
    }
    .tab-content > .tab-pane#graphs.active {
        display: block;
    }
    #graph-tooltip {
        position: absolute;
        display: none;
        padding: 8px;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        border-radius: 4px;
        font-size: 12px;
        pointer-events: none;
        z-index: 1000;
        max-width: 250px;
    }
    .text-danger {
        color: #e74c3c;
    }
</style>

<style>
    /*
     * Override the default 'not-allowed' cursor on read-only Flatpickr inputs.
     * This makes the date fields feel clickable and more intuitive for the user.
     */
    /* Replace your existing style with this */
  .form-control.flatpickr-input[readonly] {
      cursor: pointer !important;
      background-color: #fff !important;
  }

  .form-control.datepicker[readonly] {
      cursor: pointer !important;
      background-color: #fff !important;
  }
</style>

    <div class="light-bg" id="personalSection">
      <div class="container">
        <div class="row">
          <div class="col-md-12">
            <h1 class="page-header text-center" style="margin-top: 100px; margin-bottom: 40px;">Flight Hours Management</h1>
            
            <!-- Tab Navigation -->
            <ul class="nav nav-tabs nav-justified" role="tablist">
              <li role="presentation" class="active">
                <a href="#tables" aria-controls="tables" role="tab" data-toggle="tab">
                  <i class="fa fa-table"></i> Manage Statistics
                </a>
              </li>
              <li role="presentation">
                <a href="#graphs" aria-controls="graphs" role="tab" data-toggle="tab">
                  <i class="fa fa-line-chart"></i> Limited Times
                </a>
              </li>
              <li role="presentation">
                <a href="#crafts" aria-controls="crafts" role="tab" data-toggle="tab">
                  <i class="fa fa-plane"></i> Craft Experience
                </a>
              </li>
            </ul>

            <!-- CORRECTED Tab Content Wrapper -->
            <div class="tab-content">  <!-- OPEN .tab-content HERE -->

              <!-- Manage Statistics Tab -->
              <div role="tabpanel" class="tab-pane active" id="tables">
                <!-- Panel for "Add Flight Hours" -->
                <div class="panel panel-default">
                  <div class="panel-heading">
                    <h3 class="panel-title">Add Flight Hours</h3>
                  </div>

                  <div class="panel-body">
                    <form id="addHourForm">
                      <!-- ... Your complete form for adding flight hours ... -->
                       <!-- Row 1 -->
                        <div class="row">
                          <div class="col-md-2">
                            <div class="form-group">
                              <label for="addDate">Date</label>
                              <input type="text" class="form-control datepicker" id="addDate" value="May-10-2025" required style="max-width: 8em;">
                            </div>
                          </div>

                          <div class="col-md-2">
                            <div class="form-group">
                              <label for="addCraftType">Craft Type *</label>
                              <select class="form-control" id="addCraftType" required style="max-width: 11em;">
                                <option value="">Select Craft Type</option>
                              </select>
                            </div>
                          </div>

                          <div class="col-md-2">
                            <div class="form-group">
                              <label for="addCraftRegistration">Registration *</label>
                              <select class="form-control" id="addCraftRegistration" required style="max-width: 11em;">
                                <option value="">Select Registration</option>
                              </select>
                            </div>
                          </div>
                        </div>

                        <!-- Row 2 - Crew Role -->
                        <div class="row">
                              <div class="col-md-3">
                                <div class="form-group">
                                  <label>Crew Role *</label>
                                  <div class="form-check">
                                    <input class="form-check-input" type="radio" name="crew_role" id="crew_pic" value="PIC" required checked>
                                    <label class="form-check-label" for="crew_pic">PIC</label>
                                  </div>
                                  <div class="form-check">
                                    <input class="form-check-input" type="radio" name="crew_role" id="crew_sic" value="SIC">
                                    <label class="form-check-label" for="crew_sic">SIC</label>
                                  </div>
                                </div>
                              </div>
                              
                              <div class="col-md-3">
                                <div class="form-group">
                                  <label for="addPIC">Pilot in Command (PIC) *</label>
                                  <input type="text" class="form-control" id="addPIC" style="max-width: 13em;">
                                </div>
                              </div>
                              <div class="col-md-3">
                                <div class="form-group">
                                  <label for="addSIC">Copilot (SIC)</label>
                                  <input type="text" class="form-control" id="addSIC" style="max-width: 12em;">
                                </div>
                              </div>
                            </div>
                            
                            <!-- Row 3 - Flight Details -->
                            <div class="row">
                              <div class="col-md-4">
                                <div class="form-group">
                                  <label for="addRoute">Route</label>
                                  <input type="text" class="form-control" id="addRoute" style="max-width: 30em;">
                                </div>
                              </div>

                              <div class="col-md-1">
                                <div class="form-group">
                                  <label for="addIFRInstrument">IFR *</label>
                                  <input type="number" step="0.1" min="0" class="form-control" id="addIFRInstrument" value="" placeholder= "0.0" style="max-width: 4em;">
                                </div>
                              </div>

                              <div class="col-md-2">
                                <div class="form-group">
                                  <label for="addVFRVisual">VFR *</label>
                                  <input type="number" step="0.1" min="0" class="form-control" id="addVFRVisual" value="" placeholder= "0.0" style="max-width: 4em;">
                                </div>
                              </div>

                              <div class="col-md-2">
                                <div class="form-group">
                                  <label for="addNightTime">Night Time</label>
                                  <input type="number" step="0.1" min="0" class="form-control" id="addNightTime" value="" placeholder= "0.0" style="max-width: 4em;">
                                </div>
                              </div>

                              <div class="col-md-2">
                                <div class="form-group">
                                  <label for="addTotalFlightTime">Total Flight Time</label>
                                  <input type="number" step="0.1" min="0" class="form-control" id="addTotalFlightTime" value="" placeholder= "0.0" readonly style="max-width: 4em;">
                                </div>
                              </div>
                            </div>

                        <!-- ... other rows for addHourForm ... -->
                        <div class="row">
                          <div class="col-md-12 text-right">
                            <button type="submit" class="btn btn-primary">Add Entry</button>
                          </div>
                        </div>
                    </form>
                  </div>
                </div> <!-- Closing Panel for "Add Flight Hours" -->

<!-- Panel for "Log Book" (This is part of the #tables tab) -->
                <div class="panel panel-default">
                  <div class="panel-heading clearfix">
                    <h3 class="panel-title pull-left">Log Book</h3>
                    <div class="pull-right">
                      <button class="btn btn-primary" id="openViewLogbookModalBtn">
                          <i class="fa fa-book"></i> View Logbook
                      </button>
                      <button class="btn btn-info" id="openMonthlyReportModalBtn">
                          <i class="fa fa-file-text"></i> Monthly Report
                      </button>
                    </div>
                  </div>
                  <div class="panel-body">
                    <div class="row">
                      <div class="col-md-3">
                        <div class="form-group">
                          <label for="log-date">Log book beginning from:</label>
                          <input type="text" id="log-date" class="form-control datepicker" placeholder="Select Starting Date"/>
                        </div>
                      </div>
                      <div class="col-md-9 text-right"> 
                        <div id="page-container" style="display: none; margin-top: 15px;">
                          <div class="pages pagination"></div>
                        </div>
                      </div>
                    </div>
                    <div id="manageHoursSection" class="table-responsive">
                      <!-- Hours will be loaded here -->
                    </div>
                  </div>
                </div> <!-- Closing Panel for "Log Book" -->
              </div> <!-- CLOSING #tables .tab-pane -->
              
              <!-- Limited Times Tab (Now correctly INSIDE .tab-content) -->
              <div role="tabpanel" class="tab-pane" id="graphs">
                <div class="panel panel-default">
                  <div class="panel-heading clearfix">
                    <h3 class="panel-title pull-left">Hour Statistics</h3>
                    <button class="btn btn-primary pull-right" id="openCrewLimitationsModalBtn" data-toggle="modal" data-target="#limitationsModal">
                        <i class="fa fa-clock-o"></i> Crew Time Limitations
                    </button>
                  </div>
                  <div class="panel-body">
                    <div class="row">
                      <div class="col-md-9">
                        <div class="btn-group" role="group">
                          <button class="btn btn-default view-change active" data-view="past7">Last 7 days</button>
                          <button class="btn btn-default view-change" data-view="past28">Last 28 days</button>
                          <button class="btn btn-default view-change" data-view="month">Month</button>
                          <button class="btn btn-default view-change" data-view="year">Year</button>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="well well-sm">
                          <strong>Total Hours: </strong><span id="totalHours">0</span>
                        </div>
                      </div>
                    </div>
                    <div id="graphSection" class="chart-container">
                      <!-- Graph will be loaded here -->
                    </div>
                  </div>
                </div>
              </div> <!-- CLOSING #graphs .tab-pane -->

              <!-- Craft Experience Tab (Now correctly INSIDE .tab-content) -->
              <div role="tabpanel" class="tab-pane" id="crafts">
                  <div class="panel panel-default">
                      <div class="panel-heading">
                          <h3 class="panel-title">Craft Experience</h3>
                      </div>
                      <div class="panel-body">
                          <div class="row row-tight-gutter">
                              <!-- Add Craft Experience column -->
                              <div class="col-md-3 column-tight-gutter">
                                  <h4>Add Initial / Base Experience</h4>
                                  <form id="addExperienceForm">
                                      <!-- ... Your complete "Add Initial / Base Experience" form ... -->
                                      <div class="form-group">
                                          <label for="addInitCraftType">Aircraft Type</label>
                                          <input type="text" class="form-control" id="addInitCraftType" placeholder="Enter Aircraft Type" required style="max-width: 10em;"> 
                                      </div>

                                      <div class="row">
                                          <div class="col-xs-6" style="padding-left: 0; padding-right: 0;"> 
                                              <hr style="border-top: 1px solid #ccc; margin: 10px 0 5px 0;">
                                          </div>
                                      </div>

                                      <h5>PIC Experience</h5>
                                      
                                      <div class="row no-gutters"> 
                                          <div class="col-xs-4" style="padding-right: 2px;"> 
                                              <div class="form-group" style="margin-bottom: 5px;">
                                                  <label for="addInitPicIfrHours" style="font-size: 0.9em;">IFR</label>
                                                  <input type="number" step="0.1" min="0" value="" class="form-control input-sm pic-calc-input" id="addInitPicIfrHours" placeholder="0.0" required style="max-width: 6em;"> 
                                              </div>
                                          </div>
                                          <div class="col-xs-4" style="padding-left: 2px; padding-right: 2px;">
                                              <div class="form-group" style="margin-bottom: 5px;">
                                                  <label for="addInitPicVfrHours" style="font-size: 0.9em;">VFR</label>
                                                  <input type="number" step="0.1" min="0" value="" class="form-control input-sm pic-calc-input" id="addInitPicVfrHours" placeholder="0.0" required style="max-width: 6em;">
                                              </div>
                                          </div>
                                          <div class="col-xs-4" style="padding-left: 2px;">
                                              <div class="form-group" style="margin-bottom: 5px;">
                                                  <label for="addInitPicNightHours" style="font-size: 0.9em;">Night</label>
                                                  <input type="number" step="0.1" min="0" value="" class="form-control input-sm" id="addInitPicNightHours" placeholder="0.0" required style="max-width: 6em;">
                                              </div>
                                          </div>
                                      </div>
                                      <br>
                                      <div class="form-group" style="margin-bottom: 10px; margin-top: 0;">
                                          <label style="font-size: 1em; font-weight:normal;">Total PIC (Calculated):</label>
                                          <p class="form-control-static" id="addInitPicTotalDisplay" style="font-weight: bold; padding-left:0; display: inline; margin-left: 5px;">0.0</p>
                                      </div>
                                    
                                      <div class="row">
                                          <div class="col-xs-6" style="padding-left: 0; padding-right: 0;">
                                              <hr style="border-top: 1px solid #ccc; margin: 10px 0 5px 0;">
                                          </div>
                                      </div>

                                      <h5>SIC Experience</h5>
                                      <div class="row no-gutters">
                                          <div class="col-xs-4" style="padding-right: 2px;">
                                              <div class="form-group" style="margin-bottom: 5px;">
                                                  <label for="addInitSicIfrHours" style="font-size: 0.9em;">IFR</label>
                                                  <input type="number" step="0.1" min="0" value="" class="form-control input-sm sic-calc-input" id="addInitSicIfrHours" placeholder="0.0" required style="max-width: 6em;">
                                              </div>
                                          </div>
                                          <div class="col-xs-4" style="padding-left: 2px; padding-right: 2px;">
                                              <div class="form-group" style="margin-bottom: 5px;">
                                                  <label for="addInitSicVfrHours" style="font-size: 0.9em;">VFR</label>
                                                  <input type="number" step="0.1" min="0" value="" class="form-control input-sm sic-calc-input" id="addInitSicVfrHours" placeholder="0.0" required style="max-width: 6em;">
                                              </div>
                                          </div>
                                          <div class="col-xs-4" style="padding-left: 2px;">
                                              <div class="form-group" style="margin-bottom: 5px;">
                                                  <label for="addInitSicNightHours" style="font-size: 0.9em;">Night</label>
                                                  <input type="number" step="0.1" min="0" value="" class="form-control input-sm" id="addInitSicNightHours" placeholder="0.0" required style="max-width: 6em;">
                                              </div>
                                          </div>
                                      </div>
                                      <br>

                                      <div class="form-group" style="margin-bottom: 10px; margin-top: 0;">
                                          <label style="font-size: 1em; font-weight:normal;">Total SIC (Calculated):</label>
                                          <p class="form-control-static" id="addInitSicTotalDisplay" style="font-weight: bold; padding-left:0; display: inline; margin-left: 5px;">0.0</p>
                                      </div>
                                      
                                      <div class="row">
                                          <div class="col-xs-6" style="padding-left: 0; padding-right: 0;">
                                              <hr style="border-top: 1px solid #ccc; margin: 10px 0 5px 0;">
                                          </div>
                                      </div>

                                      <div class="text-left"> 
                                          <button type="submit" class="btn btn-success" id="addExperienceBtn" style="margin-top: 10px; margin-bottom: 5px;">
                                            <i class="fa fa-plus"></i> Add / Update Experience
                                          </button>
                                      </div>
                                      <small class="help-block text-left" style="margin-top:5px;">Adds to existing initial experience.</small>
                                  </form>
                              </div>

                              <!-- Current Experience column -->
                              <div class="col-md-9 column-tight-gutter">
                                  <div class="clearfix" style="margin-bottom: 10px;">
                                      <h4 class="pull-left" style="margin-top: 5px;">Current Experience</h4>
                                  </div>
                                  <!-- Your static test heading (optional) -->
                                  <!-- <h3>THIS IS A STATIC TEST FROM STATISTICS.PHP</h3> -->
                                  <div id="experience-section" class="table-responsive"> <!-- Ensure this ID is unique if problems persist -->
                                      <table class='table table-striped table-bordered table-condensed no-shadow'>
                                          <thead>
                                              <tr>
                                                  <th class="text-center">Aircraft</th>
                                                  <th class="text-center">PIC</th>
                                                  <th class="text-center">SIC</th>
                                                  <th class="text-center">IFR</th>
                                                  <th class="text-center">VFR</th>
                                                  <th class="text-center">Night</th>
                                                  <th class="text-center">Total</th>
                                                  <th class="text-center">Del</th>
                                              </tr>
                                          </thead>
                                          <tbody>
                                              <tr><td colspan="8" class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>
                                          </tbody>
                                      </table>
                                  </div>
                                  <div style="margin-top: 15px; text-align: right;">
                                    <button class="btn btn-info btn-sm" id="openPrintExperienceModalBtn">
                                        <i class="fa fa-print"></i> Print Experience
                                    </button>
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>
              </div> <!-- CLOSING #crafts .tab-pane -->


        <!-- =========================== =============================  ========================== -->

    
<!-- MODAL FOR MONTHLY REPORT (viewLogModal) -->
<div class="modal" id="monthlyReportModal" tabindex="-1" role="dialog" aria-labelledby="#monthlyReportModalLabel" aria-hidden="true">
  <div class="modal-dialog" style="width: 50%"> <!-- Consider reducing width if only month/year needed -->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h1 class="modal-title text-center" id="monthlyReportModalLabel">Monthly Report Details</h1>
      </div>
      <div class="modal-body">
        <div class="container-fluid">
          <h3 class="page-header" style="margin-top: 0;">Please select month and year</h3>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="lbl" for="reportMonth">Month</label>
                <select class="form-control" id="reportMonth">
                  <option value="01">January</option>
                  <option value="02">February</option>
                  <option value="03">March</option>
                  <option value="04">April</option>
                  <option value="05">May</option>
                  <option value="06">June</option>
                  <option value="07">July</option>
                  <option value="08">August</option>
                  <option value="09">September</option>
                  <option value="10">October</option>
                  <option value="11">November</option>
                  <option value="12">December</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="lbl" for="reportYear">Year</label>
                <input type="number" class="form-control" id="reportYear" placeholder="YYYY" min="2000" max="2099">
                <!-- Or use a select for year if preferred range is limited -->
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="launchLog">View Report as PDF</button>
      </div>
    </div>
  </div>
</div>

    <!-- MODAL FOR VIEW LOGBOOK (viewLogbookModal) -->
    <div class="modal" id="viewLogbookModal" tabindex="-1" role="dialog" aria-labelledby="viewLogbookModalLabel" aria-hidden="true">
      <div class="modal-dialog" style="width: 50%">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
            <h1 class="modal-title text-center" id="viewLogbookModalLabel">Log Details</h1> <!-- Added id -->
          </div>
          <div class="modal-body">
            <div class="container-fluid"> <!-- Use container-fluid -->
              <h3 class="page-header" style="margin-top: 0;">Please select a date range</h3>
              <div class="row"> <!-- Use row -->
                <div class="col-md-6">
                  <div class="form-group"> <!-- Added form-group -->
                    <label class="lbl" for="logbookStartDate">Start Date</label> <!-- Added label -->
                    <input type="text" class="form-control" id="logbookStartDate">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group"> <!-- Added form-group -->
                    <label class="lbl" for="logbookEndDate">End Date</label> <!-- Added label -->
                    <input type="text" class="form-control" id="logbookEndDate">
                  </div>
                </div>
              </div>
              <div class="row"> <!-- New row for format selector -->
                <div class="col-md-6">
                  <div class="form-group"> <!-- Added form-group -->
                    <label class="lbl" for="logbookFormat">Output Format</label>
                    <!-- If ONLY PDF is needed, you can disable or hide this -->
                    <select class="form-control" id="logbookFormat">
                      <option value="pdf" selected>PDF</option>
                      <!-- <option value="xlsx">Excel</option> --> <!-- Keep Excel if you might support it later -->
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button> <!-- Standard button type -->
            <button type="button" class="btn btn-success" id="printLog">View Log as PDF</button> <!-- Changed text, standard button type -->
          </div>
        </div>
      </div>
    </div>

</div> <!-- End of your existing outer container -->

        <!-- statistics.php -->
<!-- ... (other HTML) ... -->

<!-- MODAL FOR CREW TIME LIMITATIONS -->
<div class="modal" id="limitationsModal" tabindex="-1" role="dialog" aria-labelledby="limitationsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h1 class="modal-title text-center" id="limitationsModalLabel">Crew Time Limitations</h1>
      </div>
      <div class="modal-body">
        <div class="container-fluid"> <!-- Use container-fluid for better padding -->
          <div class="row">
            <div class="col-xs-12">
              <?php
                if (!function_exists('display_limit_in_modal')) {
                  function display_limit_in_modal($limit_value_from_array, $default_text = 'N/A') {
                     return isset($limit_value_from_array) && $limit_value_from_array !== 'N/A' ? htmlspecialchars($limit_value_from_array) : $default_text;
                  }
              }
              ?>
              <style>
                .limit-item-modal { margin-bottom: 8px; font-size: 1.1em; }
                .limit-item-modal .lbl-modal { display: inline-block; width: 70%; font-weight: bold; color: #337ab7; }
                .limit-item-modal .limit-value-modal { display: inline-block; width: 25%; text-align: right; }
              </style>

              <div class="limit-item-modal">
                <span class="lbl-modal">Maximum Flying hours / Day:</span>
                <span class="limit-value-modal"><?php echo display_limit_in_modal($modal_pilot_limits['max_in_day']); ?></span>
              </div>
              <div class="limit-item-modal">
                <span class="lbl-modal">Maximum Flying hours / 7 Days:</span>
                <span class="limit-value-modal"><?php echo display_limit_in_modal($modal_pilot_limits['max_last_7']); ?></span>
              </div>
              <div class="limit-item-modal">
                <span class="lbl-modal">Maximum Flying hours / 28 Days:</span>
                <span class="limit-value-modal"><?php echo display_limit_in_modal($modal_pilot_limits['max_last_28']); ?></span>
              </div>
              <div class="limit-item-modal">
                <span class="lbl-modal">Maximum Flying hours / 365 Days:</span>
                <span class="limit-value-modal"><?php echo display_limit_in_modal($modal_pilot_limits['max_last_365']); ?></span>
              </div>
              
              <!-- Assuming max_days_in_row might not be in your pilot_max_times table based on schema provided earlier -->
              <?php if (isset($modal_pilot_limits['max_days_in_row']) && $modal_pilot_limits['max_days_in_row'] !== 'N/A'): ?>
              <div class="limit-item-modal">
                <span class="lbl-modal">Maximum Flying Days in a Row:</span>
                <span class="limit-value-modal"><?php echo display_limit_in_modal($modal_pilot_limits['max_days_in_row']); ?></span>
              </div>
              <?php endif; ?>

              <hr>

              <div class="limit-item-modal">
                <span class="lbl-modal">Maximum Duty hours / Day:</span>
                <span class="limit-value-modal"><?php echo display_limit_in_modal($modal_pilot_limits['max_duty_in_day']); ?></span>
              </div>
              <div class="limit-item-modal">
                <span class="lbl-modal">Maximum Duty hours / 7 Days:</span>
                <span class="limit-value-modal"><?php echo display_limit_in_modal($modal_pilot_limits['max_duty_7']); ?></span>
              </div>
              <div class="limit-item-modal">
                <span class="lbl-modal">Maximum Duty hours / 28 Days:</span>
                <span class="limit-value-modal"><?php echo display_limit_in_modal($modal_pilot_limits['max_duty_28']); ?></span>
              </div>
              <div class="limit-item-modal">
                <span class="lbl-modal">Maximum Duty hours / 365 Days:</span>
                <span class="limit-value-modal"><?php echo display_limit_in_modal($modal_pilot_limits['max_duty_365']); ?></span>
              </div>

            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL FOR PRINT CRAFT EXPERIENCE -->
<div class="modal fade" id="printExperienceModal" tabindex="-1" role="dialog" aria-labelledby="printExperienceModalLabel">
  <div class="modal-dialog modal-sm" role="document"> <!-- Smaller modal -->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
        <h4 class="modal-title" id="printExperienceModalLabel">Print Craft Experience</h4>
      </div>
      <div class="modal-body">
        <p>This will prepare your current craft experience summary for printing.</p>
        <p>Ensure your browser's print settings (scale, margins, headers/footers) are configured appropriately.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmPrintExperienceBtn">Print</button>
      </div>
    </div>
  </div>
</div>

<!-- ... (other HTML) ... -->

      </div>

<?php
// =========================================================================
// === DEFINE PAGE-SPECIFIC SCRIPTS (STATISTICS)                         ===
// =========================================================================
$page_stylesheets = [
    // === THE FIX IS HERE: Add the CSS for X-Editable ===
    'https://cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.1/bootstrap3-editable/css/bootstrap-editable.css'
];

$page_scripts = [
    // Flot Charting Libraries (must load before your scripts)
    'lib/jquery.flot.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/flot/4.2.6/jquery.flot.pie.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/flot/4.2.6/jquery.flot.categories.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/flot/4.2.6/jquery.flot.resize.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/flot/4.2.6/jquery.flot.stack.min.js', // stack must be at the end bar vertical stack 
    'https://cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.1/bootstrap3-editable/js/bootstrap-editable.min.js',
    
    // Your custom scripts for the statistics page
    'stats-logbook.js',
    'stats-experience.js',
    'stats-graphs.js',
    'stats-reports.js',
    'stats-main.js'
];

include_once "footer.php"; 
?>

