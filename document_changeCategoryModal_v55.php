<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// This file assumes it's included within a PHP context where sessions might be relevant,
// but it doesn't directly use session data itself.
// It relies on JavaScript to populate its content based on the selected document.
?>
<div class="modal fade" id="changeCategoryModal" tabindex="-1" role="dialog" aria-labelledby="changeCategoryModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <h4 class="modal-title" id="changeCategoryModalLabel">Change Document Category</h4>
            </div>
            <div class="modal-body">
                <!-- Form elements for changing the category -->
                <div class="form-group">
                    <label for="changeCategory">Assign to Existing Category:</label>
                    <select class="form-control" id="changeCategory">
                        <!-- Options will be loaded by getCategories() JavaScript function -->
                        <option value="">LoadingP categories...</option>
                    </select>
                </div>

                <div class="form-group" id="changeNewCategoryGroup" style="display: none;"> <!-- Initially hidden -->
                    <label for="changeCategoryInput">Or Create New Category:</label>
                    <input type="text" class="form-control" id="changeCategoryInput" placeholder="Enter new category name">
                    <small class="help-block">Entering a name here will create and assign this new category.</small>
                </div>

                 <!-- Note: No actual <form> tag is needed if using AJAX via button click -->

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="changeCategorySave">Save Changes</button>
            </div>
        </div>
    </div>
</div>