<?php
  error_reporting(-1);
  if (session_status() == PHP_SESSION_NONE) {
      session_start();
  }
  if(!isset($_SESSION["HeliUser"])){
    header("Location: index.php");
  }
  $adminLevel = intval($_SESSION["admin"]);
  if($adminLevel == 0)
    header("Location: index.php");

  $page = "account";
  include_once "header.php";
?>
    <div class="light-bg">
      <div class="container inner-sm">    
        <h1 class="page-header">Account Settings</h1>
        <div class="col-md-12">
          <div class="tab-content">
            <div class="tab-pane active">
            <?php 
              if($adminLevel == 8 || $adminLevel == 7){
                echo '<div class="col-md-6">
                        <h3 class="page-header">Account Information</h3>
                        <div class="lbl">Account/Company Name</div>
                        <p id="account-name" class="edit-account" data-name="name"></p>
                        <div class="lbl">Operation Nationality</div>
                        <p id="account-nationality" class="edit-account" data-name="nationality"></p>
                        <div class="lbl" class="edit-account">Log Book Name</div>
                        <p id="logbook-name" class="edit-account" data-name="logbook"></p>
                      </div>';
              }
            ?>    
              <div class="col-md-6">
                <h3 class="page-header">Account Settings</h3> 
                <div class="lbl">Maximum Flying hours in 1 Day</div>
                <p id="maxInDay" class='edit-account' data-name='max_in_day'></p>      
                <div class="lbl">Maximum Flying hours in 7 Days</div>
                <p id="maxSeven" class='edit-account' data-name='max_last_7'></p>
                <div class="lbl">Maximum Flying hours in 28 Days</div>
                <p id="maxTwentyEight" class='edit-account' data-name='max_last_28'></p>
                <div class="lbl">Maximum Flying hours in 365 Days</div>
                <p id="max365" class='edit-account' data-name='max_last_365'></p>
                <div class="lbl">Maximum Flying Days in a Row</div>
                <p id="maxShifts" class='edit-account' data-name='max_days_in_row'></p>
                <div class="lbl">Maximum Duty hours in 1 Day</div>
                <p id="maxDutyInDay" class='edit-account' data-name='max_duty_in_day'></p>      
                <div class="lbl">Maximum Duty hours in 7 Days</div>
                <p id="maxDutySeven" class='edit-account' data-name='max_duty_7'></p>
                <div class="lbl">Maximum Duty hours in 28 Days</div>
                <p id="maxDutyTwentyEight" class='edit-account' data-name='max_duty_28'></p>
                <div class="lbl">Maximum Duty hours in 365 Days</div>
                <p id="maxDuty365" class='edit-account' data-name='max_duty_365'></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php include_once "footer.php";?>