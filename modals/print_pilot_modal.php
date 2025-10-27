<!-- printPilotModal -->

<div class="modal" id="printPilotModal" tabindex="-1" role="dialog" aria-labelledby="personinfo" aria-hidden="true" >
    <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h3 class="modal-title">Select Export Options-print_pilot_modal</h3>
      </div>
      <div class="modal-body">
        <div class="col-md-12 no-float">
          <div class="checkbox">
            <label>
              <input type="checkbox" id="pilot-print-validity" value="validity" checked>
              Validity
            </label>
          </div>
          <div class="checkbox">
            <label>
              <input type="checkbox" id="pilot-print-availability" value="available" checked>
              Availability
            </label>
          </div>
          <div class="checkbox">
            <label>
              <input type="checkbox" id="pilot-print-info" value="personal_info" checked>
              Personal Information <small>(Contact, lisences, crafts, contracts)</small>
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success" id="printPilot">Export</button>
        <button class="btn btn-default" data-dismiss="modal">Cancel</button>
      </div>
    </div>
    </div>
  </div>