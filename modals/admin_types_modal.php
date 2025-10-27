<div class="modal" id="adminTypesModal" tabindex="-1" role="dialog" aria-labelledby="adminModal" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                      <h1 class="modal-title text-center">Permission Types</h1>
                    </div>
                    <div class="modal-body">
                      <table class=\'table no-shadow table-striped\'>
                        <thead>
                          <th>Type</th>
                          <th>Restrictions</th>
                        </thead>
                        <tbody>
                          <tr>
                            <td>Administrator - Pilot</td>
                            <td><ul><li>No Restrictions</li></ul></td>
                          </tr>
                          <tr>
                            <td>Administrator</td>
                            <td>
                              <ul>
                                <li>Does not show up as a pilot</li>
                                <li>doesn\'t have pilot attributes (My Schedule, Validity, On-Off dates)</li>
                              </ul>
                            </td>
                          </tr>
                          <tr>
                            <td>Manager</td>
                            <td>
                              <ul>
                                <li>Cannot manage the account (payment, status, information)</li>
                                <li>Does not show up as a pilot.</li>
                                <li>Does not have pilot attributes (My Schedule, Validity, On-Off dates)</li>
                              </ul>
                            </td>
                          </tr>
                          <tr>
                            <td>Training Manager</td>
                            <td>
                              <ul>
                                <li>Cannot edit Main Schedule</li>
                                <li>Cannot manage the account (payment, status, information)</li>
                                <li>Does not show up as a pilot.</li>
                                <li>Does not have pilot attributes (My Schedule, Validity, On-Off dates)</li>
                                <li>Cannot send SMS messages (Optional)</li>
                                <li>Can only view Schedule Tab in Daily Management</li>
                                <li>Cannot change Permissions types</li>
                              </ul>
                            </td>
                          </tr>
                          <tr>
                            <td>Schedule Manager</td>
                            <td>
                              <ul>
                                <li>Cannot edit Training Schedule</li>
                                <li>Cannot manage the account (payment, status, information)</li>
                                <li>Does not show up as a pilot.</li>
                                <li>Does not have pilot attributes (My Schedule, Validity, On-Off dates)</li>
                                <li>Cannot change Permissions types</li>
                              </ul>
                            </td>
                          </tr>
                          <tr>
                            <td>Manager - Pilot</td>
                            <td>
                              <ul>
                                <li>Cannot manage the account (payment, status, information)</li>
                              </ul>
                            </td>
                          </tr>
                          <tr>
                            <td>Training Manager - Pilot</td>
                            <td>
                              <ul>
                                <li>Cannot edit Training Schedule</li>
                                <li>Cannot manage the account (payment, status, information)</li>
                                <li>Can only view Schedule Tab in Daily Management</li>
                                <li>Cannot send SMS messages (Optional)</li>
                                <li>Cannot change Permissions types</li>
                              </ul>
                            </td>
                          </tr>
                          <tr>
                            <td>Schedule Manager - Pilot</td>
                            <td>
                              <ul>
                                <li>Cannot edit Main Schedule</li>
                                <li>Cannot manage the account (payment, status, information)</li>
                                <li>Cannot change Permissions types</li>
                              </ul>
                            </td>
                          </tr>
                          <tr>
                            <td>Pilot</td>
                            <td>
                              <ul>
                                <li>Cannot edit anything except information in My Hangar.</li>
                                <li>Cannot send SMS messages (Optional)</li>
                              </ul>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                   </div>
                   <div class="modal-footer">
                    <button class="btn" data-dismiss="modal">Close</button>
                   </div>
                  </div>
                </div>
              </div>';