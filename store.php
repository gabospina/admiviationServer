<?php
  error_reporting(-1);
  if (session_status() == PHP_SESSION_NONE) {
      session_start();
  }
  if(!isset($_SESSION["HeliUser"])){
    header("Location: index.php");
  }

  $page = "store";
  include_once "header.php";
?>
    <div class="light-bg">
      <div class="container inner-sm">    
        <h1 class="page-header">Coming Soon!</h1>
        <div class="col-md-12">
          <h3>We're working hard to get this page up and running. Once it's up, you'll be able to easily buy and sell items with your collegues and other Helicopter Offshore users around the world.</h3>
        </div>
      </div>
    </div>
  <?php include_once "footer.php";?>