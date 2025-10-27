<?php
  error_reporting(-1);
  if (session_status() == PHP_SESSION_NONE) {
      session_start();
  }
  if(!isset($_SESSION["HeliUser"])){
    header("Location: index.php");
  }

  $page = "posts";
  include_once "header.php";
?>
    <div class="light-bg">
      <div class="container inner-sm">    
        <h1 class="page-header">Coming Soon!</h1>
        <div class="col-md-12">
          <h3>We're working hard to get this page up and running. Here you'll be able to find out what your friends and co-workers are up to. Let them know what's going on with a click of the button</h3>
        </div>
      </div>
    </div>
  <?php include_once "footer.php";?>