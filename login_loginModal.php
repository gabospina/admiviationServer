<!-- 
    File: login_loginModal.php
    Description: This code is verified as correct.
-->
<div class="modal fade" id="loginModal" tabindex="-1" role="dialog" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                <h3 class="modal-title text-center" id="loginModalLabel">Log In</h3>
            </div>
            <div class="modal-body">
                <!-- Add onsubmit="return false;" for extra protection against standard submission -->
                <form class="text-center" id="loginForm" method="post" action="#" onsubmit="return false;">
                    
                    <!-- The CSRF token is required for the login to be accepted -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                    
                    <div id="loginError" class="text-danger" style="margin-bottom:15px;"></div>
                    <div class="form-group">
                        <label for="user">Username</label>
                        <input type="text" id="user" class="form-control" name="username" required placeholder="Username" />
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" class="form-control" name="password" required placeholder="Password" />
                    </div>
                    
                    <div class="form-group">
                        <input type="button" id="submitLoginBtn" class="form-control btn btn-primary" value="Log In">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                Not yet a member? <button class="btn btn-primary btn-sm" data-dismiss="modal" data-toggle="modal" data-target="#signupModal">Sign up here</button>
            </div>
        </div>
    </div>
</div>
