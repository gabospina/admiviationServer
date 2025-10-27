<?php
// error_reporting(-1); // Use stricter reporting for dev if needed
ini_set('display_errors', 0); // Production setting
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// INCLUDE DATABASE CONNECTION AND PERMISSION HANDLER
include_once 'db_connect.php'; 
include_once 'login_csrf_handler.php'; // Corrected name
include_once 'login_permissions.php';  // Corrected name
include_once 'permissions.php';  // Corrected name

// --- AUTH CHECKS ---
// In document_docufile.php - REPLACE the permission check with this
$canUpload = false;
$userRoleInfo = [];

if (isset($_SESSION["HeliUser"])) {
    $userRoleInfo = getUserRoleInfo($_SESSION["HeliUser"], $mysqli);
    $canUpload = $userRoleInfo['can_upload'];
}

$page = "docufile";
$pageTitle = "Documents";
include_once "header.php"; // Should include CSS links (Bootstrap etc.)

?>
<?php if (!$canUpload): ?>
<!-- Message for users who cannot upload -->
<div class="panel panel-warning">
    <div class="panel-heading">
        <h3 class="panel-title">Upload Permission</h3>
    </div>
    <div class="panel-body">
        <p class="text-center text-muted">
            <i class="fa fa-info-circle"></i><br>
            Document upload is restricted to managers and administrators.
        </p>
    </div>
</div>
<?php endif; ?>

    <div class="light-bg" id="personalSection">
        <div class="container inner-sm"> <!-- Use container for better structure -->
            <div class="row">
                <div class="col-md-12" style="padding-top: 30px;">
                    <h2 class="page-header">Documents Management</h2>
                </div>
            </div>

            <div class="row">
                <!-- Left Column: Filters & Upload -->
                <div class="col-md-4"> <!-- Adjusted width -->

                    <!-- Filter Panel -->
                    <div class="panel panel-default">
                        <div class="panel-heading"><h3 class="panel-title">Filter Documents</h3></div>
                        <div class="panel-body">
                            <div class="form-group">
                                <label for="documentCategories" class="lbl">Filter by Category</label>
                                <select class="form-control" id="documentCategories">
                                    <option value="AllCategories">-- All Categories --</option>
                                    <!-- Options loaded by JS -->
                                </select>
                            </div>
                            <!-- ============================================= -->
                            <!-- === NEW DELETE CATEGORY BUTTON === -->
                            <!-- ============================================= -->
                            <?php if ($canUpload): // Reuse the same permission check ?>
                            <div class="form-group" style="margin-top: 15px;">
                                <button class="btn btn-danger btn-sm btn-block" id="deleteCategoryBtn" disabled>
                                    <i class="fa fa-trash"></i> Delete Selected Category
                                </button>
                                <small class="text-muted">Select a specific category above to enable deletion.</small>
                            </div>
                            <?php endif; ?>
                            <!-- ============================================= -->
                        </div>
                    </div>
                    <!-- End Filter Panel -->

                    <!-- ============================================= -->
                    <!-- == UPLOAD PANEL - WRAPPED IN PERMISSION CHECK == -->
                    <!-- ============================================= -->
                    <?php if ($canUpload): ?>
                    <!-- ===== START: Simple HTML Upload Form (Styled & Enhanced) ===== -->
                    <div class="panel panel-info" id="simpleUploadSection"> <!-- Changed panel type -->
                        <div class="panel-heading">
                            <h3 class="panel-title">Upload New Document</h3>
                        </div>
                        <div class="panel-body">
                            <!-- The Simple Form -->
                            <form action="document_upload.php" method="post" enctype="multipart/form-data" id="simpleUploadForm">

                            <!-- ADD THIS HIDDEN INPUT -->
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">    
                            
                            <!-- Existing Category Selection -->
                                <div class="form-group">
                                    <label for="simpleExistingCategory">Assign to Existing Category (Optional):</label>
                                    <select class="form-control" id="simpleExistingCategory" name="categoryId"> <!-- Name = categoryId -->
                                        <option value="">-- Select Existing --</option>
                                        <!-- Categories populated by JS -->
                                    </select>
                                </div>

                                <!-- New Category Input -->
                                <div class="form-group">
                                    <label for="simpleNewCategoryName">Or Create New Category (Optional):</label>
                                    <input type="text" class="form-control" id="simpleNewCategoryName" name="newCategoryName" placeholder="Type new name (takes priority)">
                                    <!-- Removed hidden isNewCategory field -->
                                </div>

                                <!-- File Input -->
                                <div class="form-group">
                                    <label for="simpleFile">Select File:</label>
                                    <!-- Removed 'required' to allow category creation only -->
                                    <input type="file" class="form-control-file" id="simpleFile" name="file">
                                    <small class="form-text text-muted">You can create a category without uploading a file.</small>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" class="btn btn-success btn-block">Save Category / Upload File</button> <!-- Changed button style -->
                            </form>
                            <!-- End Simple Form -->
                        </div>
                    </div>
                    <?php endif; ?>
                    <!-- ======================================== -->
                    <!-- ===== END: Simple HTML Upload Form ===== -->

                </div><!-- /.col-md-4 -->

                <!-- Right Column: Document List & Details -->
                <div class="col-md-8"> <!-- Adjusted width -->

                     <!-- Document List Panel -->
                     <div class="panel panel-default">
                        <div class="panel-heading"><h3 class="panel-title">Document List</h3></div>
                        <div class="panel-body">
                             <div id="document-list" class="list">
                                 <p class='text-muted text-center' style="padding: 20px 0;">Loading documents...</p>
                             </div>
                        </div>
                     </div>
                     <!-- End Document List Panel -->

                    <!-- ==================================================== -->
                    <!-- == Document Details Panel (Buttons Above Title) == -->
                    <!-- ==================================================== -->
                    <div class="panel panel-default" id="document-panel" style="display: none;">
                         <div class="panel-heading clearfix">
                            <h3 class="panel-title pull-left" id="document-title" style="padding-top: 7.5px;">Select a Document</h3>

                            <!-- Right side buttons container -->
                            <div class="pull-right">
                                <!-- Left group - Preview & Download -->
                                <div class="btn-group" role="group" aria-label="Document Preview Actions" style="margin-right: 20px;">
                                    <button type="button" class="btn btn-primary btn-sm" id="openPreview" title="Open Preview in New Tab">
                                        <i class="fa fa-external-link"></i> Open Preview in New Tab
                                    </button>
                                    <button type="button" class="btn btn-info btn-sm" id="downloadOriginal" title="Download Original Document">
                                        <i class="fa fa-download"></i> Download Original (DOC)
                                    </button>
                                </div>
                                
                                <!-- Right group - Category & Delete -->
                                <div class="btn-group" role="group" aria-label="Document Management Actions">
                                    <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#changeCategoryModal" title="Change Document Category">
                                        <i class="fa fa-pencil"></i> Change Category
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" id="deleteDocument" title="Delete Document Permanently">
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                         </div>
                         <div class="panel-body">
                            <!-- Standard Action Menu (Below Heading) -->
                            <div id="document-menu" class="well well-sm" style="margin-bottom: 15px;">
                                <p style="margin-bottom: 5px;"><strong>Filename:</strong> <span id="document-filename">N/A</span></p>
                                <p style="margin-bottom: 10px;"><strong>Uploaded:</strong> <span id="document-creation">N/A</span></p>

                                <!-- Standard User Actions -->
                                <div class="btn-group" role="group" aria-label="Document Actions">
                                    <button type="button" class="btn btn-primary btn-sm" id="downloadDocument" title="Download Original File">
                                        <i class="fa fa-download"></i> Download
                                    </button>
                                    <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#viewStatsModal" title="View Read Status">
                                        <i class="fa fa-eye"></i> View Stats
                                    </button>
                                </div>
                                <div class="clearfix"></div>
                            </div>
                            <!-- End Standard Action Menu -->

                            <!-- Document Preview Area -->
                            <div id="document-content">
                                <p class="text-muted text-center" style="padding: 40px 0;">Select a document from the list.</p>
                            </div>
                            <!-- End Document Preview Area -->
                         </div>
                    </div><!-- /#document-panel -->
                    <!-- ==================================================== -->
                    <!-- ==================================================== -->

                </div><!-- /.col-md-8 -->

<!-- Include Modal HTML Files -->
<?php include 'document_viewStatusModal.php'; ?>
<?php include 'document_changeCategoryModal.php'; ?>

<!-- Hidden link for downloads -->
<a href="" download id="downloadLink" style="display:none;"></a>

<!-- Internal CSS -->
<style>
    /* Layout & Panel Styles */
    .inner-sm { padding-top: 20px; padding-bottom: 20px; }
    .page-header { margin-top: 0; margin-bottom: 30px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
    .panel { margin-bottom: 25px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,.07); }
    .panel-heading { color: #333; background-color: #f5f5f5; border-color: #ddd; padding: 10px 15px; border-bottom: 1px solid #ddd; border-top-left-radius: 3px; border-top-right-radius: 3px; }
    .panel-info > .panel-heading { color: #31708f; background-color: #d9edf7; border-color: #bce8f1; } /* Style info panel */
    .panel-title { margin-top: 0; margin-bottom: 0; font-size: 16px; }
    .panel-body { padding: 20px; }

    /* Simple Upload Form Styles */
    #simpleUploadForm .form-group { margin-bottom: 18px; }
    #simpleUploadForm label { display: block; margin-bottom: 6px; font-weight: bold; color: #555; font-size: 0.9em;}
    #simpleUploadForm .form-control,
    #simpleUploadForm .form-control-file,
    #simpleUploadForm .btn { margin-top: 10px; } /* Add margin to button */

    /* Document List Styles -->
    #document-list { max-height: 400px; overflow-y: auto; margin-top: 15px;} /* Limit height and add scroll */
    #document-list .list-group-item { cursor: pointer; transition: background-color 0.2s ease; padding: 10px 15px; border-radius: 0 !important; border-left: none; border-right: none;}
    #document-list .list-group-item:first-child { border-top: none; }
    #document-list .list-group-item:last-child { border-bottom: none; }
    #document-list .list-group-item:hover { background-color: #f9f9f9; }
    #document-list .list-group-item.selected { background-color: #e7f3ff; border-left: 4px solid #337ab7; font-weight: bold; }
    #document-list .list-group-item .badge { margin-left: 5px; font-weight: normal; font-size: 0.85em; }
    #document-list .list-group-item .category-badge { background-color: #777; color: white; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle; display: inline-block; margin-right: 5px; padding: 3px 6px;}
    #document-list .list-group-item .date-badge { background-color: #eee; color: #555; padding: 3px 6px;}

    /* Document Panel Styles -->
    #document-panel { margin-top: 0; } /* Remove margin if panels align */
    #document-menu { background-color: #f9f9f9; border: 1px solid #eee; padding: 10px 15px; margin-bottom: 20px; border-radius: 3px; }
    #document-menu p { margin-bottom: 8px; font-size: 0.9em;}
    #document-menu .btn-group { margin-right: 5px; }
    #document-content img { max-width: 100%; height: auto; border: 1px solid #ddd; padding: 3px;}
    #document-content object { border: 1px solid #ddd; }

    /* Document Preview Styles */
    .document-preview {
        border: 1px solid #ddd;
        border-radius: 4px;
        background: white;
        margin-bottom: 20px;
    }

    .pdf-preview {
        position: relative;
    }

    .pdf-preview iframe {
        width: 100%;
        height: 700px;
        border: none;
        background: #525659;
    }

    /* Preview actions styling */
    .preview-actions {
        padding: 10px 15px;
        background: #f8f9fa;
        border-top: 1px solid #ddd;
        text-align: center;
    }

    .preview-actions .btn {
        padding: 6px 12px;
        font-size: 13px;
    }

    .preview-actions .btn-primary {
        background-color: #007bff;
        border-color: #0056b3;
    }

    .preview-actions .btn-secondary {
        background-color: #6c757d;
        border-color: #545b62;
    }

    /* Alert styling */
    .alert-sm {
        padding: 5px 10px;
        margin: 0;
        border-radius: 0;
        border-bottom: 1px solid #bce8f1;
    }

    /* Preview info banner */
    .preview-info {
        background-color: #f8f9fa;
        border-bottom: 1px solid #ddd;
        padding: 8px 15px;
        color: #666;
        font-size: 13px;
    }

    .image-preview img {
        max-height: 600px;
        width: auto;
        margin: 0 auto;
        display: block;
    }

    .unsupported {
        text-align: center;
        padding: 40px 0;
    }


    .modal-backdrop {
        z-index: 1040;
    }


    body.modal-open {
        overflow: hidden;
        padding-right: 0 !important;
    }


    .select2-container--open {
        z-index: 1060;
    }

    /* Modal Styles */
    .modal.fade.show {
        display: block !important;
        opacity: 1 !important;
    }

    .modal-backdrop.fade.show {
        opacity: 0.5;
    }

    body.modal-open {
        overflow: hidden;
        padding-right: 17px;
    }

    /* Update the button group styling */
    .panel-heading .btn-group {
        margin-left: 15px;
        display: inline-block;
    }

    .panel-heading .btn-group .btn {
        margin-left: 8px;
        border-radius: 3px !important;
        position: relative;
    }

    /* Remove default btn-group margins and borders */
    .panel-heading .btn-group > .btn:first-child {
        margin-left: 8px;
    }

    .panel-heading .btn-group > .btn:not(:last-child):not(.dropdown-toggle),
    .panel-heading .btn-group > .btn:not(:first-child) {
        border-radius: 3px !important;
    }

    /* Ensure consistent button sizes */
    .panel-heading .btn {
        padding: 5px 12px;
        min-width: 100px;
    }

    /* Ensure text and icons are properly aligned */
    .panel-heading .btn i {
        margin-right: 5px;
    }

    /* Panel title spacing */
    .panel-heading .panel-title {
        margin-right: 15px;
    }

    /* Document List Styles */
    #document-list { 
        max-height: 400px; 
        overflow-y: auto; 
        margin-top: 15px;
        width: 100%; /* Ensure it takes full width */
    }

    #document-list .list-group-item { 
        display: flex; 
        justify-content: space-between; 
        align-items: center;
        padding: 10px 15px;
        white-space: nowrap; /* Prevent text wrapping */
        overflow: hidden; /* Hide overflow */
        text-overflow: ellipsis; /* Show ellipsis if text is too long */
    }

    #document-list .document-info {
        flex: 1; /* Take up available space */
        min-width: 0; /* Allow text truncation */
        display: flex;
        align-items: center;
        margin-right: 15px;
        overflow: hidden; /* Hide overflow */
    }

    #document-list .category-badge {
        flex-shrink: 0; /* Don't allow category to shrink */
        margin-right: 10px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 150px; /* Adjust as needed */
    }

    #document-list .document-link {
        flex: 1; /* Take remaining space */
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-right: 10px;
    }

    #document-list .document-date {
        flex-shrink: 0; /* Don't allow date to shrink */
        white-space: nowrap;
    }

</style>

<!-- ... all your document_docufile.php HTML content ends here ... -->

<?php
// =========================================================================
// === DEFINE PAGE-SPECIFIC SCRIPTS (DOCUMENT)                           ===
// =========================================================================
$page_scripts = [
    'documentfunctions.js'
];

include_once "footer.php"; 
?>
