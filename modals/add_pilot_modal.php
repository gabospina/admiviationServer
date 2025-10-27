<!-- ADD PILOT MODAL ====================== -->
<div class="modal addPilot" tabindex="-1" role="dialog" aria-labelledby="addPilot" aria-hidden="true">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                      <h1 class="modal-title">Add a new pilot</h1>
                    </div>
                    <div class="modal-body">
                        <div class="col-md-12 no-float center-block">
                         <!--  <table style="-webkit-box-shadow: 0px 0px 0px 0px rgba(0,0,0,0); -moz-box-shadow: 0px 0px 0px 0px rgba(0,0,0,0); box-shadow: 0px 0px 0px 0px rgba(0,0,0,0);">
                            <thead>
                              <th colspan="2"><h2 class="page-header">Pilot Information</h2></th>
                            </thead>
                            <tbody>
                              <tr>
                                <td style="padding-top: 10px"><span class="lbl">First Name*</span></td>
                                <td style="padding-top: 10px"><span class="lbl">Last Name*</span></td>
                              </tr>
                              <tr>
                                <td><input type="text" id="fname" style="margin-right: 25px;"/></td>
                                <td><input type="text" id="lname" style="margin-right: 25px;"/></td>
                              </tr>
                              <tr>
                                <td colspan="2" style="padding-top: 10px"><span class="lbl">Email</span></td>
                              </tr>
                              <tr>
                                <td colspan="2"><input type="text" id="email" style="width: 85%; margin-right: 25px" /></td>
                              </tr>
                              <tr>
                                <td colspan="2" style="padding-top: 10px"><span class="lbl">Phone</span></td>
                              </tr>
                              <tr>
                                <td colspan="2"><input type="text" id="phone" style="width: 85%; margin-right: 25px" /></td>
                              </tr>
                              <tr>
                                <td style="padding-top: 10px;"><span class="lbl">Nationality</span></td>
                                <td style="padding-top: 10px;"><span class="lbl">Angolan License*</span></td>
                              </tr>
                              <tr>
                                <td><input type="text" id="nationality" style="margin-right: 25px;" /></td>
                                <td><input type="text" id="ang_license" style="margin-right: 25px;" /></td>
                              </tr>
                              <tr>
                                <td style="padding-top: 10px"><span class="lbl">Position*</span></td>
                                <td style="padding-top: 10px"><span class="lbl">Foreign License</span></td>
                              </tr>
                              <tr>
                                <td><select id="comandante" class="form-control" style="width: 90%; margin-right: 0px"><option value="1">Comandante</option><option value="0">Piloto</option></select></td>
                                <td><input type="text" id="for_license" style="margin-right: 25px"/></td>
                              </tr>
                            </tbody>
                          </table> -->
                          <h2 class="page-header">Pilot Information</h2>
                          <div class="col-md-6">
                            <div class="lbl">First Name*</div>
                            <input type="text" class="form-control" id="fname"/>
                          </div>
                          <div class="col-md-6">
                            <div class="lbl">Last Name*</div>
                            <input type="text" class="form-control" id="lname"/>
                          </div>
                          <div class="col-md-6">
                            <div class="lbl">Username*</div>
                            <input type="text" class="form-control" id="addUsername" />
                          </div>
                          <div class="col-md-6">
                            <div class="lbl">Permission Type* <a class="pull-right" data-toggle="modal" data-target="#adminTypesModal">Learn More</a></div>
                            <select class="form-control" id="adminType">
                              <option value=\'8\'>Administrator - Pilot</option>
                              <option value=\'7\'>Administrator</option>
                              <option value=\'6\'>Manager</option>
                              <option value=\'5\'>Training Manager</option>
                              <option value=\'4\'>Schedule Manager</option>
                              <option value=\'3\'>Manager - Pilot</option>
                              <option value=\'2\'>Training Manager - Pilot</option>
                              <option value=\'1\'>Schedule Manager - Pilot</option>
                              <option value=\'0\'>Pilot</option>
                            </select>
                          </div>
                          <div class="col-md-12">
                            <div class="lbl">Email</div>
                            <input type="text" class="form-control" id="email" style="width: 68%;"/>
                          </div>
                          <div class="col-md-12">
                            <div class="col-md-6" class="form-control" style="padding-left: 0px;">
                              <div class="lbl">Phone</div>
                              <input type="text" class="form-control" id="phone"/>
                            </div>
                            <div class="col-md-6">
                              <div class="lbl">Secondary Phone</div>
                              <input type="text" class="form-control" id="phonetwo"/>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="lbl">Nationality</div>
                            <input type="text" class="form-control" id="nationality"/>
                          </div>
                          <div class="col-md-6">
                            <div class="lbl"><?php echo $accountNationality ?> License</div>
                            <input type="text" class="form-control" id="ang_license"/>
                          </div>
                          <div class="col-md-6">
                            <div class="lbl">Position*</div>
                            <select id="comandante" class="form-control" style="margin-top: 6px;"><option value="1">Comandante</option><option value="0">Piloto</option></select>
                          </div>
                          <div class="col-md-6 outer-bottom-xxs">
                            <div class="lbl">Foreign License</div>
                            <input type="text" class="form-control" id="for_license"/>
                          </div>
                          <div class="col-md-6">
                            <div class="lbl">Training</div>
                            <div class="radio">
                              <label>
                                <input type="radio" name="training" value="0" checked>
                                None
                              </label>
                            </div>
                            <div class="radio">
                              <label>
                                <input type="radio" name="training" value="2">
                                TRE
                              </label>
                            </div>
                            <div class="radio">
                              <label>
                                <input type="radio" name="training" value="1">
                                TRI
                              </label>
                            </div>
                          </div>

                          <!--ON OFF -->
                          <!-- <div class="col-md-12">
                            <h3 class="page-header">On/Off Schedule</h3>
                          </div>
                          <div class="col-md-12 outer-bottom-xs">
                            <button id="addDates" class="btn btn-primary"><strong>+</strong></button>
                            <button id="removeDates" class="btn btn-warnin  g"><strong>-</strong></button>
                          </div>
                          <div id="onOffDates">
                            <div class="col-md-6">
                              <div class="lbl">On</div>
                              <input type="text" class="dp on-off on-date"  placeholder=\'YYYY-mm-dd\' />
                            </div>
                            <div class="col-md-6">
                              <div class="lbl">Off</div>
                              <input type="text" class="dp on-off off-date" placeholder=\'YYYY-mm-dd\' />
                            </div>
                          </div>
                          <div class="col-md-12 outer-top-xs" id="pilotContracts">
                            <h3 style="width: 85%">Contracts <small>Contracts the pilot can fly (ctrl+click for multiple)</small></h3>
                            <select class="form-control" size="6" multiple>
                              <option value="none">None</option>
                            </select>
                          </div>
                          <div class="col-md-12 outer-top-xs" id="pilotCrafts">
                            <h3 style="width: 85%">Crafts <small>Crafts the pilot can fly (ctrl+click for multiple)</small></h3>
                            <select class="form-control" size="6" multiple>
                              <option value="none">None</option>
                            </select>
                          </div>
                        </div>

                        <div class="col-md-4">
                          <table style="width:100%; -webkit-box-shadow: 0px 0px 0px 0px rgba(0,0,0,0); -moz-box-shadow: 0px 0px 0px 0px rgba(0,0,0,0); box-shadow: 0px 0px 0px 0px rgba(0,0,0,0);">
                            <thead>
                              <th colspan="2"><h2 style="border-bottom: 1px solid rgba(75,75,75,0.4); width: 85%">Validity</h2></th>
                            </thead>
                            <tbody>
                              <tr>
                                <td colspan="2"><h3 style="border-bottom: 1px solid rgba(75,75,75,0.4); width: 85%">Tests And Misc.</h3></td>
                              </tr>
                              <tr>
                                <td style="padding-top: 10px"><span class="lbl">Angolan License</span></td>
                                <td style="padding-top: 10px"><span class="lbl">Foreigner License</span></td>
                              </tr>
                              <tr>
                                <td><input type="text" id="ang_lic" class="dp" placeholder=\'YYYY-mm-dd\' style="margin-right: 0px;"/></td>
                                <td><input type="text" id="for_lic" class="dp" placeholder=\'YYYY-mm-dd\' style="margin-right: 0px;"/></td>
                              </tr>
                              <tr>
                                <td style="padding-top: 10px"><span class="lbl">Passport</span></td>
                                <td style="padding-top: 10px"><span class="lbl">Angolan Visa</span></td>
                              </tr>
                              <tr>
                                <td><input type="text" id="passport" class="dp" placeholder=\'YYYY-mm-dd\' style="margin-right: 0px;"/></td>
                                <td><input type="text" id="ang_visa" class="dp" placeholder=\'YYYY-mm-dd\' style="margin-right: 0px;"/></td>
                              </tr>
                              <tr>
                                <td style="padding-top: 10px"><span class="lbl">Instrumental Valid</span></td>
                                <td style="padding-top: 10px"><span class="lbl">USA Visa</span></td>
                              </tr>
                              <tr>
                                <td><input type="text" id="instruments" class="dp" placeholder=\'YYYY-mm-dd\' style="margin-right: 0px" /></td>
                                <td><input type="text" id="us_visa" class="dp" placeholder=\'YYYY-mm-dd\' style="margin-right: 0px" /></td>
                              </tr>
                              <tr>
                                <td style="padding-top: 10px"><span class="lbl">Medical</span></td>
                                <td style="padding-top: 10px"><span class="lbl">Caderneta de Voo</span></td>
                              </tr>
                              <tr>
                                <td><input type="text" id="med" class="dp" placeholder=\'YYYY-mm-dd\' style="margin-right: 0px"/></td>
                                <td><input type="text" id="booklet" class="dp" placeholder=\'YYYY-mm-dd\' style="margin-right: 0px"/></td>
                              </tr>
                              <tr>
                                <td style="padding-top: 10px"><span class="lbl">SIM Cert.</span></td>
                                <td style="padding-top: 10px"><span class="lbl">Training Record</span></td>
                              </tr>
                              <tr>
                                <td><input type="text" id="sim" class="dp" placeholder=\'YYYY-mm-dd\' style="margin-right: 0px" /></td>
                                <td><input type="text" id="train_rec" class="dp" placeholder=\'YYYY-mm-dd\' style="margin-right: 0px" /></td>
                              </tr>
                              <tr>
                                <td style="padding-top: 10px"><span class="lbl">Flight Training</span></td>
                                <td></td>
                              </tr>
                              <tr>
                                <td><input type="text" id="flight_train" class="dp" placeholder=\'YYYY-mm-dd\' style="margin-right: 0px;"/></td>
                                <td></td>
                              </tr>
                              <tr>
                                <td colspan="2"><h3 style="border-bottom: 1px solid rgba(75,75,75,0.4); width: 85%">Checks</h3></td>
                              </tr>
                              <tr>
                                <td style="margin-top: 10px"><span class="lbl">Base Check</span></td>
                                <td style="margin-top: 10px"><span class="lbl">IFR Currency</span></td>
                              </tr>
                              <tr>
                                <td><input id="base_check" type="text" class="dp" placeholder=\'YYYY-mm-dd\' /></td>
                                <td><input id="ifr_cur" type="text" class="dp" placeholder=\'YYYY-mm-dd\' /></td>
                              </tr>
                              <tr>
                                <td style="margin-top: 10px"><span class="lbl">Night Currency</span></td>
                                <td style="margin-top: 10px"><span class="lbl">IFR Check</span></td>
                              </tr>
                              <tr>
                                <td><input id="night_cur" type="text" class="dp" placeholder=\'YYYY-mm-dd\' /></td>
                                <td><input id="ifr_check" type="text" class="dp" placeholder=\'YYYY-mm-dd\' /></td>
                              </tr>
                              <tr>
                                <td style="margin-top: 10px"><span class="lbl">Night Check</span></td>
                                <td style="margin-top: 10px"><span class="lbl">Line Check</span></td>
                              </tr>
                              <tr>
                                <td><input id="night_check" type="text" class="dp" placeholder=\'YYYY-mm-dd\' /></td>
                                <td><input id="line_check" type="text" class="dp" placeholder=\'YYYY-mm-dd\' /></td>
                              </tr>
                              <tr>
                                <td colspan="2"><h3 style="border-bottom: 1px solid rgba(75,75,75,0.4); width: 85%">HOIST</h3></td>
                              </tr>
                              <tr>
                                <td style="margin-top: 10px"><span class="lbl">Check</span></td>
                                <td style="margin-top: 10px"><span class="lbl">Currency</span></td>
                              </tr>
                              <tr>
                                <td><input id="hoist_check" type="text" class="dp" placeholder=\'YYYY-mm-dd\' /></td>
                                <td><input id="hoist_cur" type="text" class="dp" placeholder=\'YYYY-mm-dd\' /></td>
                              </tr>
                              <tr>
                                <td colspan="2"><h3 style="border-bottom: 1px solid rgba(75,75,75,0.4); width: 85%">Other</h3></td>
                              </tr>
                              <tr>
                                <td style="margin-top: 10px"><span class="lbl">CRM</span></td>
                                <td style="margin-top: 10px"><span class="lbl">HUET</span></td>
                              </tr>
                              <tr>
                                <td><input id="crm" type="text" class="dp" placeholder=\'YYYY-mm-dd\' /></td>
                                <td><input id="huet" type="text" class="dp" placeholder=\'YYYY-mm-dd\'/></td>
                              </tr>
                              <tr>
                                <td style="margin-top: 10px"><span class="lbl">HOOK</span></td>
                                <td style="margin-top: 10px"><span class="lbl">English Leve</span></td>
                              </tr>
                              <tr>
                                <td><input id="hook" type="text" class="dp" placeholder=\'YYYY-mm-dd\' /></td>
                                <td><input id="english" type="text" class="dp" placeholder=\'YYYY-mm-dd\' /></td>
                              </tr>
                              <tr>
                                <td style="margin-top: 10px"><span class="lbl">HERDS</span></td>
                                <td style="margin-top: 10px"><span class="lbl">F.AIDS</span></td>
                              </tr>
                              <tr>
                                <td><input id="herds" type="text" class="dp" placeholder=\'YYYY-mm-dd\'/></td>
                                <td><input id="faids" type="text" class="dp" placeholder=\'YYYY-mm-dd\' /></td>
                              </tr>
                              <tr>
                                <td style="margin-top: 10px"><span class="lbl">Dangerous Goods</span></td>
                                <td style="margin-top: 10px"><span class="lbl">Fire Fight</span></td>
                              </tr>
                              <tr>
                                <td><input id="dang_good" type="text" class="dp" placeholder=\'YYYY-mm-dd\' /></td>
                                <td><input id="fire" type="text" class="dp" placeholder=\'YYYY-mm-dd\' /></td>
                              </tr>
                            </tbody>
                          </table> 
                        </div>-->
                        <!-- <div class="col-md-4 outer-top-xs">
                          <button class="btn btn-success btn-lg" id="submitPilot">SUBMIT</button>
                        </div> -->
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button class="btn btn-success btn-lg" id="submitPilot">SUBMIT</button>
                  </div>
                  </div>
                </div>
              </div>
              <!-- ENDS PILOT MODAL ========================== -->