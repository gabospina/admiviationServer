<?php
  error_reporting(-1);
  if (session_status() == PHP_SESSION_NONE) {
      session_start();
  }
  if(!isset($_SESSION["HeliUser"])){
    header("Location: index.php");
  }

  $page = "messaging";
  include_once "header.php";
?>
    <div class="light-bg">
      <div class="inner-xs inner-left-xs inner-right-sm">    
        <h1 class="page-header">Message Center</h1>
        <div id="messaging-container">
          <div id="messaging-left">
            <div id="messaging-search-bar">
              <input type="text" id="messaging-search" placeholder="Search.."/>
            </div>
            <div id="messaging-list">
              <div class="messaging-list-item">Gabriel Ospina</div>
            </div>
          </div>
          <div id="messaging-right">
            <div id="messaging-menubar">
              <button class="btn btn-default" id="newMessage" data-toggle="modal" data-target="#newMessageModal">New Message</button>
              <!-- <button class="btn btn-danger" id="deleteMessage"><div class="fa fa-trash-o"></div></button> -->
            </div>
            <div id="messaging-content"></div>
            <div id="messaging-text-container">
              <textarea id="messaging-text"></textarea>
              <div id="messaging-send-bar">
                <button class="btn btn-default disabled" id="attach-file" data-toggle="modal" data-target="#uploadModal"><div class="fa fa-paperclip"></div> Attach File</button>
                <label class="outer-left-xxs" style="font-weight: 100; margin-bottom: 0px; color: #7F7F7F;"><input type="checkbox" id="sendOnEnter" checked> Send when enter is pressed.</label>
                <button class="btn btn-default disabled" id="sendMessage">Send</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="modal" id="newMessageModal" tabindex="-1" role="dialog" aria-labelledby="viewLog" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
            <h1 class="modal-title text-center">Select Users</h1>
          </div>
          <div class="modal-body">
            <div class="col-md-12 no-float">
              <div id="search-users-section">
                <input type="text" class="form-control" id="search-users" placeholder="Search users, crafts, contracts, permissions, positions..">
              </div>
              <div class="outer-bottom-xxs outer-top-xxs inner-left-md">
                <button class="btn btn-primary" id="userCheckAll">Select/Deselect All</button>
              </div>
              <div id="user-list"></div>
            </div>
         </div>
         <div class="modal-footer">
          <button class="btn btn-success" id="startMessage">Select User</button>
          <button class="btn" data-dismiss="modal">Cancel</button>
         </div>
        </div>
      </div>
    </div>

    <div class="modal" id="uploadModal" tabindex="-1" role="dialog" aria-labelledby="uploadModal" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
            <h1 class="modal-title">Upload Documents And Files</h1>
          </div>
          <div class="modal-body">
            <div class="col-md-12 no-float">
              <form action="php/upload_document.php" class="dropzone no-float" id="uploadDocuments">
                <p class="dz-message">Drag and Drop, or click, to upload files.</p>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div> 
  <?php include_once "footer.php";?>