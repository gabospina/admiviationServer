<?php
  if (session_status() == PHP_SESSION_NONE) {
      session_start();
  }
  if(!isset($_SESSION["HeliUser"])){
    header("Location: index.php");
  }

$page = "map";
include_once "header.php" ?>
  <div class="inner-sm">
    <div class="row outer-bottom-xs">
      <h2 class="text-black text-center">Charter Course</h2>
    </div>

    <div id="map-canvas"></div>
    <div class="col-md-12 no-float">
      <h2 class="page-header">Select Course</h2>
      <div class="col-md-4">
        <select class="form-control">
          <option>Contract</option>
        </select>
      </div>
      <div class="col-md-4">
        <select class="form-control">
          <option>Craft</option>
        </select>
      </div>
      <div class="col-md-12 outer-top-xs">
        <table class="table table-condensed table-bordered" style='-webkit-box-shadow: 0px 0px 0px 0px rgba(0,0,0,0); -moz-box-shadow: 0px 0px 0px 0px rgba(0,0,0,0); box-shadow: 0px 0px 0px 0px rgba(0,0,0,0);'>
          <thead>
            <th>Lat</th>
            <th>Lon</th>
          </thead>
          <tbody id="main-course">
            <tr>
              <td>12.455455</td>
              <td>100.300393</td>
            </tr>
            <tr>
              <td><input class="form-control" type="text" placeholder="Wind"></td>
              <td><input class="form-control" type="text" placeholder="Total load minus fuel"></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="col-md-3 center-block">
        <button class="btn btn-primary form-control">Calculate</button>
      </div>
    </div>

    <div id="calculation-results"></div>

    <div class="col-md-3 no-float outer-top-md">
      <button class="btn btn-primary form-control">New Course</button>
      <div id="new-course"></div>
    </div>

    <div class="col-md-12 no-float">
      <h2 class="page-header">Edit Course</h2>
      <div class="col-md-4">
        <select class="form-control">
          <option>Contract</option>
        </select>
      </div>
      <div class="col-md-4">
        <select class="form-control">
          <option>Craft</option>
        </select>
      </div>
    </div>
  </div>
</div>
<?php include_once "footer.php"; ?>