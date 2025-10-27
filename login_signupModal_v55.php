<!-- 
    File: signupModal.php
    Description: CORRECTED version of the signup modal.
    - Restores the 3-step process to include Company Information.
    - Fixes the final button ID and removes the inline 'onclick' to work with loginfunctions.js.
    - Adds the required CSRF token for security.
-->

<!-- Signup Modal -->
<div class="modal fade" id="signupModal" tabindex="-1" role="dialog" aria-labelledby="signupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                <h3 class="modal-title text-center" id="signupModalLabel">Sign Up</h3>
            </div>
            <div class="modal-body">
                <!-- The form action and method are set, but submission is handled by JavaScript -->
                <form action="signup.php" method="post" class="text-center" id="signUpForm">

                    <!-- 
                        STEP 1: ACCOUNT (COMPANY) INFORMATION
                        This step is required by your signup.php script.
                    -->
                    <div class='step' data-step="1">
                        <h3 class="page-header">Account Information</h3>
                        <div class="form-group">
                            <label for="signup_companyName">Account Name or Company*</label>
                            <input type="text" id="signup_companyName" class="form-control" name="companyName" required="required" />
                        </div>
                        <div class="form-group">
                            <label for="signup_companyNationality">Operation Nationality*</label>
                            <input type="text" id="signup_companyNationality" class="form-control" name="companyNationality" />
                        </div>
                        <div class="form-group">
                            <input type="button" class="form-control btn btn-primary step-btn" value="Next" data-increment="1">
                        </div>
                    </div>

                    <!-- 
                        STEP 2: USER INFORMATION
                    -->
                    <div class="step" data-step="2" style="display: none;">
                        <h3 class="page-header">User Information</h3>
                        <div class="form-group">
                            <label for="signup_firstname">First Name*</label>
                            <input type="text" id="signup_firstname" class="form-control" name="firstname" />
                        </div>
                        <div class="form-group">
                            <label for="signup_lastname">Last Name*</label>
                            <input type="text" id="signup_lastname" class="form-control" name="lastname" />
                        </div>
                        <div class="form-group">
                            <label for="signup_user-nationality">Nationality</label>
                            <input type="text" id="signup_user-nationality" class="form-control" name="user-nationality" />
                        </div>
                        <div class="form-group">
                            <label for="signup_email">Email*</label>
                            <input type="text" id="signup_email" class="form-control" name="email" />
                        </div>
                        <div class="form-group">
                            <label for="signup_phone">Cellular Phone</label>
                            <input type="text" id="signup_phone" class="form-control" name="phone" />
                        </div>
                        <div class="form-group col-md-6">
                            <input type="button" class="form-control btn btn-primary step-btn" value="Back" data-increment="-1">
                        </div>
                        <div class="form-group col-md-6">
                            <input type="button" class="form-control btn btn-primary step-btn" value="Next" data-increment="1">
                        </div>
                    </div>

                    <!-- 
                        STEP 3: LOGIN INFORMATION & SUBMISSION
                    -->
                    <div class="step" data-step="3" style="display: none;">
                        <h3 class="page-header">Log in Information</h3>
                        <div class="form-group">
                            <label for="signup_username">Username*<span id="usernameTaken"></span></label>
                            <input type="text" id="signup_username" class="form-control" name="username" placeholder="Choose a username" />
                        </div>
                        <div class="form-group">
                            <label for="signup_password">Password*</label>
                            <input type="password" id="signup_password" class="form-control" name="password" placeholder="Enter a password" />
                        </div>
                        <div class="form-group">
                            <label for="signup_confpassword">Confirm Password*</label>
                            <input type="password" id="signup_confpassword" class="form-control" name="confpassword" placeholder="Re-enter password" />
                        </div>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" id="user-agreement-check"> I hereby agree to the <a data-toggle="modal" data-target="#user-agreement">Terms of Use.</a>
                            </label>
                        </div>
                        <div class="form-group col-md-6">
                            <input type="button" class="form-control btn btn-primary step-btn" value="Back" data-increment="-1">
                        </div>
                        <div class="form-group col-md-6">
                            <!-- CORRECTED: ID is "submitSignupBtn" and there is NO onclick attribute -->
                            <input type="button" id="submitSignupBtn" class="form-control btn btn-primary" value="Sign Up Now!">
                        </div>
                    </div>
                    
                    <!-- ADDED: CSRF Token for security -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <p class="outer-top-xxs outer-bottom-xxs">* Required Information</p>
                </form>
            </div>
            <div class="modal-footer">
                Already have an account? <button class="btn btn-primary btn-sm" data-dismiss="modal" data-toggle="modal" data-target="#loginModal">Log In</button>
            </div>
        </div>
    </div>
</div>