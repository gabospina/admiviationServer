<?php
// This file assumes it's included within a PHP context.
// It relies on JavaScript to fetch and populate the view statistics.
?>
<div class="modal fade" id="viewStatsModal" tabindex="-1" role="dialog" aria-labelledby="viewStatsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document"> <!-- modal-lg for wider view -->
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <h4 class="modal-title" id="viewStatsModalLabel">Document Read Status</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="panel panel-success">
                            <div class="panel-heading">
                                <h3 class="panel-title"><i class="fa fa-check-circle"></i> Viewed By:</h3>
                            </div>
                            <div class="panel-body" style="max-height: 400px; overflow-y: auto;">
                                <ul id="viewedList" class="list-group">
                                    <!-- JS will populate this list, e.g., <li class="list-group-item">User Name (Date)</li> -->
                                    <li class="list-group-item text-muted">Loading...</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="panel panel-warning">
                            <div class="panel-heading">
                                <h3 class="panel-title"><i class="fa fa-times-circle"></i> Not Viewed By:</h3>
                            </div>
                            <div class="panel-body" style="max-height: 400px; overflow-y: auto;">
                                <ul id="notviewedList" class="list-group">
                                    <!-- JS will populate this list, e.g., <li class="list-group-item">User Name</li> -->
                                    <li class="list-group-item text-muted">Loading...</li>
                                </ul>
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