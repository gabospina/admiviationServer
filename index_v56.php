<?php
// Put this at the VERY TOP of index.php
session_start(); // Make sure session is started

// Generate a CSRF token if one doesn't already exist for this session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Now, make the token available to your HTML form
$csrf_token = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

    <!-- BASIC INFO (From index.html for better SEO) -->
    <title>Admviation - Helicopter Management Made Easy</title>
    <meta name="keywords"
        content="helicopters,helicopter,offshore, helicopters offshore, helicopter offshore, offshore helicopters,scheduling,flying, flight,schedule,pilots,statistics,log book,log,Angola,management,pilot,planner,easy,simple,software,cloud,web application, application">
    <meta name="description"
        content="Helicopters Offshore offers a way to easily manage schedules, training, tests expiration, keeping a logbook, and keeping in touch within your community.">

    <!-- FAVICONS -->
    <link rel="icon" href="images/favicons/favicon.ico">

    <!-- CSS (Using the clean set from index.html) -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Montserrat:400,700">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,700italic,800italic,400,300,600,700,800">
    <link rel="stylesheet" href="css/bootstrap.min.css" type="text/css">
    <link rel="stylesheet" href="css/font-awesome.min.css" type="text/css">
    <link rel="stylesheet" href="css/animate.min.css" type="text/css">
    <link rel="stylesheet" href="css/style.css" type="text/css">
    <link id="color-css" rel="stylesheet" href="css/colors/orange.css" type="text/css">

    <!-- === Noty CSS belongs in the <head> with other stylesheets === -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noty/3.1.4/noty.min.css">

    <style>
    #header.navbar-fixed-top {
        background-color:rgb(75, 74, 74); /* A dark grey, but you can use #000000 for pure black */
    }
    </style>

</head>

<!-- Added classes from index.html for animations -->

<body class="enable-animations enable-preloader">
    <div id="document" class="document">

        <!-- HEADER (Using the more complete version from index.html) -->
        <header id="header" class="header-section section section-dark navbar navbar-fixed-top">
            <div class="container-fluid">
                <div class="navbar-header navbar-left">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navigation">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <!-- Using your Admviation branding -->
                    <a class="navbar-logo navbar-brand anchor-link" href="#hero">
                        Admviation - HELICOPTERS OFFSHORE
                    </a>
                </div>
                <nav id="navigation" class="navigation navbar-collapse collapse navbar-right">
                    <ul id="header-nav" class="nav navbar-nav">
                        <li><a href="#hero" class="hidden">Top</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#benefits">Features</a></li>
                        <li><a href="#how-it-works">How it Works</a></li>
                        <li><a href="#future">Our Goals</a></li>
                        <li><a href="#pricing">Pricing</a></li>
                        <!-- <li><a href="news.php">News</a></li> -->

                        <li class="header-action-button">
                            <a href="#" class="btn btn-primary" data-toggle="modal" data-target="#signupModal">Sign
                                Up</a>
                        </li>
                        <li class="header-action-button"><a href="#" class="btn btn-primary" data-toggle="modal"
                                data-target="#loginModal">Log In</a></li>
                    </ul>
                </nav>
            </div>
        </header>

        <!-- HERO SECTION (Using the rich version from index.html) -->
        <section id="hero" class="hero-section hero-layout-simple hero-fullscreen section section-dark">
            <div class="section-background">

                <div class="section-background-image parallax" data-stellar-ratio="0.4">
                    <img src="images/backgrounds/head_banner.jpg" alt="helicopter dashboard" style="opacity: 0.3;">
                </div>
            </div>

            <div class="container">
                <div class="hero-content">
                    <div class="hero-content-inner">
                        <div class="row">
                            <div class="col-md-10 col-md-offset-1">
                                <!-- <div class="hero-heading" data-animation="fadeIn"> -->
                                <div class="hero-heading">
                                    <h1 class="hero-title">Helicopter management made easy</h1>
                                    <p class="hero-tagline">We help you organize all aspects of helicopter piloting and
                                        management.</p>
                                </div>
                                <p class="hero-buttons">
                                    <a href="#about" class="btn btn-lg btn-default anchor-link">Learn More</a>
                                    <a href="#" data-toggle="modal" data-target="#signupModal"
                                        class="btn btn-lg btn-primary">Sign Up</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <a href="#headline" class="hero-start-link anchor-link"><span class="fa fa-angle-double-down"></span></a>
        </section>

        <!-- ======================================================= -->
        <!-- === START: All content sections from index.html ===== -->
        <!-- ======================================================= -->

        <section id="headline" class="headline-section section-gray section">
            <div class="container">
                <div class="row">
                    <div class="col-md-10 col-md-offset-1">
                        <p class="headline-text">
                            Designed by people in the business, <strong>HELICOPTERS OFFSHORE</strong> makes managing
                            your workflow, keeping track of records and schedules, and connecting with others simple and
                            time effective.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section id="about" class="about-section section">
            <div class="container">
                <h2 class="section-heading text-center">About Helicopters Offshore</h2>
                <div class="about-row row">
                    <!-- <div class="about-image col-md-6" data-animation="fadeIn"> -->
                    <div class="about-image col-md-6">
                        <img src="images/backgrounds/about_us_2.jpg" alt="Helicopter fleet"
                            style="border-radius: 15px;">
                    </div>
                    <div class="about-text col-md-6">
                        <p class="lead">Our duty is to make your duty simple.</p>
                        <p>At <strong>HELICOPTERS OFFSHORE</strong>, one of our many visions is <u>planning made
                                easy</u>. We've come up with a way for you to easily keep track and manage all of the
                            things that are important to you. How do we know? Because we're in the business too. Here's
                            some of our features:</p>
                        <ul class="icon-list">
                            <li><span class="icon-list-icon fa fa-calendar"></span>
                                <h4 class="icon-list-title">Scheduling</h4>
                                <p>From managing your weekly flights, to your training schedules, we've got it.</p>
                            </li>
                            <li><span class="icon-list-icon fa fa-users"></span>
                                <h4 class="icon-list-title">Community</h4>
                                <p>We've created a community for helicopter pilots. Communicate, share, explore. We keep
                                    you and your colleagues in touch</p>
                            </li>
                            <li><span class="icon-list-icon fa fa-clock-o"></span>
                                <h4 class="icon-list-title">Keep Track</h4>
                                <p>With HELICOPTER OFFSHORE, we keep track for you. All of your data is in one place.
                                </p>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- ======================================================= -->
        <!-- === START: CORRECTED "More Features" Section        === -->
        <!-- ======================================================= -->
        <section id="benefits" class="benefits-section section-gray section">
            <div class="container">
                <h2 class="section-heading text-center">More Features</h2>

                <!-- This is the main container for all our benefit items -->
                <div class="benefits-row">

                    <!-- START: First Row of Features -->
                    <div class="row">
                        <!-- Benefit 1: Adding Pilots -->
                        <div class="col-md-4 col-sm-6">
                            <div class="benefit">
                                <span class="benefit-icon fa fa-user-plus"></span>
                                <h4 class="benefit-title">Adding Pilots</h4>
                                <p class="benefit-description">Adding a pilot to your fleet is just a few keys and a
                                    click away. You can keep track of test expiration, contact information, and
                                    enable/disable aircrafts, contracts and positions.</p>
                            </div>
                        </div>

                        <!-- Benefit 2: Notifications -->
                        <div class="col-md-4 col-sm-6">
                            <div class="benefit">
                                <span class="benefit-icon fa fa-bell"></span>
                                <h4 class="benefit-title">Notifications</h4>
                                <p class="benefit-description">With just a click of the button, you can easily notify
                                    all of the scheduled pilots when and what they're flying. We send them a message
                                    straight to their phone telling them all the details.</p>
                            </div>
                        </div>

                        <!-- Benefit 3: Flight Schedule -->
                        <div class="col-md-4 col-sm-6">
                            <div class="benefit">
                                <span class="benefit-icon fa fa-dashboard"></span>
                                <h4 class="benefit-title">Flight Schedule</h4>
                                <p class="benefit-description">We cut your planning time by 50% giving you more time to
                                    enjoy yourself. Keeping track of who's available, who's flown too much, who's test
                                    are expired; We do it so you don't have to.</p>
                            </div>
                        </div>
                    </div>
                    <!-- END: First Row of Features -->

                    <!-- START: Second Row of Features -->
                    <div class="row">
                        <!-- Benefit 4: Training Schedule -->
                        <div class="col-md-4 col-sm-6">
                            <div class="benefit">
                                <span class="benefit-icon fa fa-calendar"></span>
                                <h4 class="benefit-title">Training Schedule</h4>
                                <p class="benefit-description">In addition to our key flight schedule, we have the
                                    training schedule. Plan your trainers, examiners, and trainees all in one place.</p>
                            </div>
                        </div>

                        <!-- Benefit 5: Tests and Paperwork -->
                        <div class="col-md-4 col-sm-6">
                            <div class="benefit">
                                <span class="benefit-icon fa fa-file-text-o"></span>
                                <h4 class="benefit-title">Tests and Paperwork</h4>
                                <p class="benefit-description">Easily keep track of when your documents and tests are
                                    going to expire. For management, easily print out lists of pilots who's validity is
                                    going to expire or already has.</p>
                            </div>
                        </div>

                        <!-- Benefit 6: Crafts And Contracts -->
                        <div class="col-md-4 col-sm-6">
                            <div class="benefit">
                                <span class="benefit-icon fa fa-book"></span>
                                <h4 class="benefit-title">Crafts And Contracts</h4>
                                <p class="benefit-description">Keep track of all your aircrafts and contracts. Simply
                                    add the model and registration and get started scheduling.</p>
                            </div>
                        </div>
                    </div>
                    <!-- END: Second Row of Features -->

                    <!-- START: Third Row of Features (Centered) -->
                    <div class="row">
                        <!-- Benefit 7: Documents -->
                        <!-- The col-md-offset-2 pushes this block to the right, centering the layout -->
                        <div class="col-md-4 col-sm-6 col-md-offset-2">
                            <div class="benefit">
                                <span class="benefit-icon fa fa-file-o"></span>
                                <h4 class="benefit-title">Documents</h4>
                                <p class="benefit-description">Upload important documents that you need your fleet to
                                    see. We also keep track of who has viewed the document so you can know who to
                                    contact.</p>
                            </div>
                        </div>

                        <!-- Benefit 8: Permission Levels -->
                        <div class="col-md-4 col-sm-6">
                            <div class="benefit">
                                <span class="benefit-icon fa fa-key"></span>
                                <h4 class="benefit-title">Permission Levels</h4>
                                <p class="benefit-description">We know that you might have different levels of users in
                                    your account. We let you set what your users have access to. You can give control to
                                    users that need it.</p>
                            </div>
                        </div>
                    </div>
                    <!-- END: Third Row of Features -->

                </div>
            </div>
        </section>
        <!-- ======================================================= -->
        <!-- === END: CORRECTED "More Features" Section          === -->
        <!-- ======================================================= -->

        <section id="how-it-works" class="how-it-works-section section">
            <div class="container-fluid">
                <h2 class="section-heading text-center">How it Works</h2>
                <div class="hiw-row row">
                    <!-- <div class="col-md-3 col-sm-6" data-animation="fadeIn"> -->
                    <div class="col-md-3 col-sm-6">
                        <div class="hiw-item"><img class="hiw-item-picture" src="images/backgrounds/addpilot.png"
                                alt="pilot management">
                            <div class="hiw-item-text"><span class="hiw-item-icon">1</span>
                                <h4 class="hiw-item-title">Add Some Pilots</h4>
                                <p class="hiw-item-description">Add some pilots to your fleet. Set their contact
                                    information, permissions, contracts and crafts, positions and tests and that's it!
                                </p>
                            </div>
                        </div>
                    </div>
                    <!-- <div class="col-md-3 col-sm-6" data-animation="fadeIn"> -->
                    <div class="col-md-3 col-sm-6">
                        <div class="hiw-item even"><img class="hiw-item-picture" src="images/backgrounds/contract.png"
                                alt="aircraft management">
                            <div class="hiw-item-text"><span class="hiw-item-icon">2</span>
                                <h4 class="hiw-item-title">Crafts And Contracts</h4>
                                <p class="hiw-item-description">Add aircrafts to manage and schedule, and create some
                                    contracts. Simply select which pilots can fly on that contract and you're done.</p>
                            </div>
                        </div>
                    </div>
                    <div class="hidden-md hidden-lg clear"></div>
                    <!-- <div class="col-md-3 col-sm-6" data-animation="fadeIn"> -->
                    <div class="col-md-3 col-sm-6">
                        <div class="hiw-item"><img class="hiw-item-picture" src="images/backgrounds/schedule.png"
                                alt="helicopter scheduling">
                            <div class="hiw-item-text"><span class="hiw-item-icon">3</span>
                                <h4 class="hiw-item-title">Schedule Away</h4>
                                <p class="hiw-item-description">With just a few clicks you can be done your week's
                                    schedule. What used to take hours can now take minutes. No more stressing over
                                    papers.</p>
                            </div>
                        </div>
                    </div>
                    <!-- <div class="col-md-3 col-sm-6" data-animation="fadeIn"> -->
                    <div class="col-md-3 col-sm-6">
                        <div class="hiw-item even"><img class="hiw-item-picture"
                                src="images/backgrounds/how_it_works_banner_2.jpg" alt="helicopter flying">
                            <div class="hiw-item-text"><span class="hiw-item-icon">4</span>
                                <h4 class="hiw-item-title">Relax & Fly!</h4>
                                <p class="hiw-item-description">We take care of all the heavy lifting so you can do you
                                    job without the stress. It really can be that easy.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- NUMBERS -->

        <!-- ======================================================= -->
        <!-- === START: CORRECTED "Our Numbers" Section          === -->
        <!-- ======================================================= -->
        <section id="numbers" class="numbers-section section-dark section">

            <div class="section-background">
                <!-- IMAGE BACKGROUND -->
                <div class="section-background-image parallax" data-stellar-ratio="0.4">
                    <img src="images/backgrounds/numbers_banner_2.jpg" alt="helicopter statistics"
                        style="opacity: 0.2;">
                </div>
            </div>

            <div class="container">
                <h2 class="section-heading text-center">Our Numbers</h2>

                <!-- <?php
        // --- START: Improved and Safer PHP Logic ---

        // Initialize variables with default values
        $user_count_display = '0';
        $craft_count_display = '0';

        // Check if the database connection object exists before trying to use it
        if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->ping()) {
            
            // --- Fetch Total Users ---
            // Using the correct 'users' table name.
            $user_result = $mysqli->query("SELECT COUNT(id) AS count FROM users");
            if ($user_result) {
                $count = $user_result->fetch_assoc()['count'];
                // Format the number (e.g., 12000 becomes 12k)
                $user_count_display = ($count < 10000) ? $count : round($count / 1000) . 'k';
            }

            // --- Fetch Total Crafts ---
            $craft_result = $mysqli->query("SELECT COUNT(id) AS count FROM crafts");
            if ($craft_result) {
                $count = $craft_result->fetch_assoc()['count'];
                $craft_count_display = ($count < 10000) ? $count : round($count / 1000) . 'k';
            }
        } else {
            // If there's no DB connection, the numbers will just show their default '0' value
            // You could also log an error here if needed.
            error_log("Database connection (\$mysqli) not available for 'Our Numbers' section.");
        }
        // --- END: Improved PHP Logic ---
        ?> -->

                <div class="numbers-row row">

                    <!-- NUMBERS - ITEM 1 -->
                    <div class="col-md-3 col-sm-6">
                        <div class="numbers-item">
                            <div class="numbers-item-counter"><span class="counter-up">2</span></div>
                            <div class="numbers-item-caption">Stage</div>
                        </div>
                    </div>

                    <!-- NUMBERS - ITEM 2 -->
                    <div class="col-md-3 col-sm-6">
                        <div class="numbers-item">
                            <div class="numbers-item-counter"><span class="counter-up">95</span>%</div>
                            <div class="numbers-item-caption">Complete</div>
                        </div>
                    </div>

                    <!-- NUMBERS - ITEM 3 (Now using the PHP variable) -->
                    <div class="col-md-3 col-sm-6">
                        <div class="numbers-item">
                            <div class="numbers-item-counter">
                                <span class="counter-up">
                                    <!-- <?php echo htmlspecialchars($user_count_display, ENT_QUOTES, 'UTF-8'); ?> -->
                                </span>
                            </div>
                            <!-- <div class="numbers-item-caption">Total Users</div> -->
                        </div>
                    </div>

                    <!-- NUMBERS - ITEM 4 (Now using the PHP variable) -->
                    <div class="col-md-3 col-sm-6">
                        <div class="numbers-item">
                            <div class="numbers-item-counter">
                                <span class="counter-up">
                                    <!-- <?php echo htmlspecialchars($craft_count_display, ENT_QUOTES, 'UTF-8'); ?> -->
                                </span>
                            </div>
                            <!-- <div class="numbers-item-caption">Crafts</div> -->
                        </div>
                    </div>

                </div>
            </div>
        </section>
        <!-- ======================================================= -->
        <!-- === END: CORRECTED "Our Numbers" Section            === -->
        <!-- ======================================================= -->

        <section id="future" class="future-section section-gray section">
            <div class="container">
                <div class="row">
                    <div class="col-md-10 col-md-offset-1">
                        <p class="headline-text">We're continuing to expand to give you everything you want, all in one
                            place.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- TWO COLS DESCRIPTION SECTION
			================================= -->
        <section class="two-cols-description-section section-accent section">

            <div class="container-fluid two-cols-description-row">

                <!-- TWO COLS DESCRIPTION TEXT -->
                <div class="two-cols-description-text">

                    <div class="two-cols-description-text-inner">

                        <h2 class="section-heading text-left">Your Community</h2>

                        <p>Manageer can easily stay in contact with all of your fellow pilots.</p>

                        <ul class="nice-list">
                            <!-- <li><strong>News Feed</strong> <small>NEW</small>. Find out all the important updates around
                                the world that apply to pilots.</li> -->
                            <li><strong>Messaging</strong> <small>NEW</small>. If you want to send a private message,
                                we've got that too. For quick messaging in the workplace, there's our messaging center.
                            </li>
                            <!-- <li><strong>Store</strong> <small>Coming Soon!</small>. List and purchase things from your
                                fellow colleagues. From headsets to computers, it's a digital garage sale.</li>
                            <li><strong>Posts</strong> <small>Coming Soon!</small>. Stay in the know and see what your
                                friends are talking about. Have something on your mind? Just post about it.</li> -->
                        </ul>
                    </div>
                </div>

                <!-- TWO COLS DESCRIPTION IMAGE -->
                <div class="two-cols-description-image">
                    <img src="images/backgrounds/community_image_2.jpg" alt="Angolan offshore helicopter">
                </div>

            </div>

        </section>

        <!-- PRICING SECTION
		================================= -->
        <section id="pricing" class="pricing-section section">
            <div class="container">
                <h2 class="section-heading text-center">Pricing Table</h2>
                <div class="row text-center">
                    <div class="col-md-10 col-md-offset-1 col-sm-12">
                        <!-- The 'text-nowrap' class prevents the text from wrapping to a new line -->
                        <p class="text-nowrap">Right now, it's free to try it out, see our designs, and we can customize
                            it to fit your needs.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="closing" class="closing-section section-dark section">
            <div class="section-background">
                <div class="section-background-image parallax" data-stellar-ratio="0.4">
                    <img src="images/backgrounds/11.jpg" alt="offshore helicopter view" style="opacity: 0.15;">
                </div>
            </div>
            <div class="container">
                <h3 class="closing-shout">Ready to start? Take the first step by clicking the button below</h3>
                <div class="closing-buttons" data-animation="tada"><a href="#" class="btn btn-lg btn-primary"
                        data-toggle="modal" data-target="#signupModal">Sign Up</a></div>
            </div>
        </section>

        <!-- ================================= -->
        <!-- GOOGLE MAPS                 ===== -->
        <!-- ================================= -->
        <section id="contact" class="maps-section section">

            <div class="container-fluid maps-row">

                <!-- MAPS IMAGE -->
                <div class="maps-image">
                    <div id="gmap"></div>
                </div>

                <!-- MAPS TEXT -->
                <div class="maps-text">

                    <div class="maps-text-inner">

                        <h3 class="section-heading text-left">Want to find out more?</h3>

                        <p>To see if we can fit your pilots' schedule and and fleet management requirements, just send
                            us a message. We'd love to hear from you and get you on board.</p>

                        <div class="row">
                            <div class="form-group">
                                <label for="msg-name">Name (Optional)</label>
                                <input type="text" class="form-control" name="msg-name" id="msg-name">
                            </div>
                            <div class="form-group">
                                <label for="msg-email">Email</label>
                                <input type="text" class="form-control" name="msg-email" id="msg-email">
                            </div>
                            <div class="form-group">
                                <textarea cols="54" rows="8" class="form-control" placeholder="Message"
                                    id="msg-content"></textarea>
                            </div>
                            <div class="form-group">
                                <button class="btn btn-primary form-control" id="sendMessage">Send Message</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ======================================================= -->
        <!-- === END: All content sections from index.html ======= -->
        <!-- ======================================================= -->

        <!-- FOOTER (Using the rich version from index.html) -->
        <section id="footer" class="footer-section section">
            <div class="container">
                <p class="footer-logo">HELICOPTERS OFFSHORE</p>
                <div class="footer-socmed">
                    <a href="https://www.facebook.com/HelicoptersOffshore" target="_blank"><span
                            class="fa fa-facebook"></span></a>
                    <a href="https://twitter.com/Heli_Offshore" target="_blank"><span class="fa fa-twitter"></span></a>
                    <a href="https://plus.google.com/+HelicoptersoffshoreManagement/about" target="_blank"><span
                            class="fa fa-google-plus"></span></a>
                </div>
                <div class="footer-copyright">
                    © 2015 Flux Solutions
                </div>
            </div>
        </section>

        <!-- ================================= -->
        <!-- === MODALS                  ===== -->
        <!-- ================================= -->
        <?php include 'loginModal.php'; ?>
        <?php include 'login_signupModal.php'; ?>

        <!-- ================================= -->
        <!-- === USER AGREEMENT BELOW      ======== -->
        <!-- ================================= -->

        <div class="modal" id="user-agreement" tabindex="-1" role="dialog" aria-labelledby="viewLog" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <h3 class="modal-title text-center">User Agreement</h3>
                    </div>
                    <div class="modal-body">
                        <p class="text-center">By using Helicopters-Offshore.com you are agreeing to the following:</p>
                        <h2 class="text-center">
                            Web Site Terms and Conditions of Use
                        </h2>

                        <h3>
                            1. Terms
                        </h3>

                        <p>
                            By accessing this web site, you are agreeing to be bound by these
                            web site Terms and Conditions of Use, all applicable laws and regulations,
                            and agree that you are responsible for compliance with any applicable local
                            laws. If you do not agree with any of these terms, you are prohibited from
                            using or accessing this site. The materials contained in this web site are
                            protected by applicable copyright and trade mark law.
                        </p>
                        <h3>
                            2. Use License
                        </h3>

                        <ol type="a">
                            <li>
                                Permission is granted to temporarily download one copy of the materials
                                (information or software) on Helicopters Offshore's web site for personal,
                                non-commercial transitory viewing only. This is the grant of a license,
                                not a transfer of title, and under this license you may not:
                                <ol type="i">
                                    <li>modify or copy the materials;</li>
                                    <li>use the materials for any commercial purpose, or for any public display
                                        (commercial
                                        or non-commercial);</li>
                                    <li>attempt to decompile or reverse engineer any software contained on Helicopters
                                        Offshore's web site;</li>
                                    <li>remove any copyright or other proprietary notations from the materials; or</li>
                                    <li>transfer the materials to another person or "mirror" the materials on any other
                                        server.</li>
                                </ol>
                            </li>
                            <li>
                                This license shall automatically terminate if you violate any of these restrictions and
                                may
                                be terminated by Helicopters Offshore at any time. Upon terminating your viewing of
                                these
                                materials or upon the termination of this license, you must destroy any downloaded
                                materials
                                in your possession whether in electronic or printed format.
                            </li>
                        </ol>

                        <h3>
                            3. Disclaimer
                        </h3>
                        <ol type="a">
                            <li>
                                The materials on Helicopters Offshore's web site are provided "as is". Helicopters
                                Offshore
                                makes no warranties, expressed or implied, and hereby disclaims and negates all other
                                warranties, including without limitation, implied warranties or conditions of
                                merchantability, fitness for a particular purpose, or non-infringement of intellectual
                                property or other violation of rights. Further, Helicopters Offshore does not warrant or
                                make any representations concerning the accuracy, likely results, or reliability of the
                                use
                                of the materials on its Internet web site or otherwise relating to such materials or on
                                any
                                sites linked to this site.
                            </li>
                        </ol>

                        <h3>
                            4. Limitations
                        </h3>
                        <p>
                            In no event shall Helicopters Offshore or its suppliers be liable for any damages
                            (including,
                            without limitation, damages for loss of data or profit, or due to business interruption,)
                            arising out of the use or inability to use the materials on Helicopters Offshore's Internet
                            site, even if Helicopters Offshore or a Helicopters Offshore authorized representative has
                            been
                            notified orally or in writing of the possibility of such damage. Because some jurisdictions
                            do
                            not allow limitations on implied warranties, or limitations of liability for consequential
                            or
                            incidental damages, these limitations may not apply to you.
                        </p>

                        <h3>
                            5. Revisions and Errata
                        </h3>
                        <p>
                            The materials appearing on Helicopters Offshore's web site could include technical,
                            typographical, or photographic errors. Helicopters Offshore does not warrant that any of the
                            materials on its web site are accurate, complete, or current. Helicopters Offshore may make
                            changes to the materials contained on its web site at any time without notice. Helicopters
                            Offshore does not, however, make any commitment to update the materials.
                        </p>

                        <h3>
                            6. Links
                        </h3>
                        <p>
                            Helicopters Offshore has not reviewed all of the sites linked to its Internet web site and
                            is
                            not responsible for the contents of any such linked site. The inclusion of any link does not
                            imply endorsement by Helicopters Offshore of the site. Use of any such linked web site is at
                            the
                            user's own risk.
                        </p>

                        <h3>
                            7. Site Terms of Use Modifications
                        </h3>
                        <p>
                            Helicopters Offshore may revise these terms of use for its web site at any time without
                            notice.
                            By using this web site you are agreeing to be bound by the then current version of these
                            Terms
                            and Conditions of Use.
                        </p>

                        <h3>
                            8. Governing Law
                        </h3>
                        <p>
                            Any claim relating to Helicopters Offshore's web site shall be governed by the laws of the
                            State
                            of Quebec without regard to its conflict of law provisions.
                        </p>
                        <p>
                            General Terms and Conditions applicable to Use of a Web Site.
                        </p>

                        <h2>
                            Privacy Policy
                        </h2>
                        <p>
                            Your privacy is very important to us. Accordingly, we have developed this Policy in order
                            for
                            you to understand how we collect, use, communicate and disclose and make use of personal
                            information. The following outlines our privacy policy.
                        </p>
                        <ul>
                            <li>
                                Before or at the time of collecting personal information, we will identify the purposes
                                for
                                which information is being collected.
                            </li>
                            <li>
                                We will collect and use of personal information solely with the objective of fulfilling
                                those purposes specified by us and for other compatible purposes, unless we obtain the
                                consent of the individual concerned or as required by law.
                            </li>
                            <li>
                                We will only retain personal information as long as necessary for the fulfillment of
                                those
                                purposes.
                            </li>
                            <li>
                                We will collect personal information by lawful and fair means and, where appropriate,
                                with
                                the knowledge or consent of the individual concerned.
                            </li>
                            <li>
                                Personal data should be relevant to the purposes for which it is to be used, and, to the
                                extent necessary for those purposes, should be accurate, complete, and up-to-date.
                            </li>
                            <li>
                                We will protect personal information by reasonable security safeguards against loss or
                                theft, as well as unauthorized access, disclosure, copying, use or modification.
                            </li>
                            <li>
                                We will make readily available to customers information about our policies and practices
                                relating to the management of personal information.
                            </li>
                        </ul>
                        <p>
                            We are committed to conducting our business in accordance with these principles in order to
                            ensure that the confidentiality of personal information is protected and maintained.
                        </p>
                    </div>
                </div>
            </div>
        </div>


    </div> <!-- End #document -->

    <!-- JAVASCRIPT (Merged list for full functionality) -->
    <script src="js/jquery-3.7.1.min.js"></script>
    <script src="js/jquery-migrate-3.5.2.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/respimage.min.js"></script>
    <script src="js/jpreloader.min.js"></script>
    <script src="js/jquery.counterup.min.js"></script> <!-- For numbers section -->
    <script src="js/jquery.stellar.min.js"></script> <!-- For parallax effect -->
    <script src="js/script.js"></script>

    <!-- Library scripts needed by loginfunctions.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/noty/3.1.4/noty.min.js"></script>

    <script src="loginfunctions.js"></script>

</body>
</html>