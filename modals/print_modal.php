<!--Print Modal ============= -->

<div class="modal" id="printModal" tabindex="-1" role="dialog" aria-labelledby="viewLog" aria-hidden="true">
                <div class="modal-dialog" style="width: 70%">
                <div class="modal-content">
                  <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h1 class="modal-title text-center">Report Settings-print_modal</h1>
                  </div>
                  <div class="modal-body">
                    <div class="container">
                      <div class="col-md-12 no-padding">
                        <div class="col-md-6">
                          <div class="lbl">Expiration Type</div>
                          <div class="radio">
                            <label>
                              <input type="radio" name="expiration_type" value="expired">
                              Expired
                            </label>
                          </div>
                          <div class="radio">
                            <label>
                              <input type="radio" name="expiration_type" value="soon">
                              Expires within 3 months.
                            </label>
                          </div>
                          <div class="radio">
                            <label>
                              <input type="radio" name="expiration_type" value="both" checked>
                              Both
                            </label>
                          </div>
                        </div>
                        <div class="col-md-6 no-padding">
                          <div class="lbl">Expiration Field</div>
                          <div class="col-md-12" id="expirationTypeSection"></div>
                        </div>
                      </div>
                    </div><!-- ENDS CONTAINER -->
                 </div>
                 
                 <div class="modal-footer">
                  <button class="btn btn-success" id="launchLog">View Report</button>
                  <button class="btn" data-dismiss="modal">Cancel</button>
                 </div>
                </div>
                </div>
              </div>