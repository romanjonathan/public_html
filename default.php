<?php
// PHP code starts here
$my_name = "Jonathan Roman";
$page_title = "Jonathan Roman";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.3/p5.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            /* Use 100vh to make sure the containers can fill the screen height */
            min-height: 100vh;
            display: flex; /* Use flexbox to easily manage the two halves */
        }
        
        .left-half {
            width: 50%;
            padding: 0;
            height: 100vh;
            position: relative;
        }

        #sketch-container canvas {
            display: block;
            width: 100% !important;
            height: 100% !important;
        }
        
        .right-half {
            width: 50%;
            text-align: center;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .photo-container {
            flex: 1;
            min-height: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 25px;
            box-sizing: border-box;
        }

        .profile-photo {
            max-height: 100%;
            max-width: 100%;
            width: auto;
            height: auto;
        }

        h1 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 10vw;
            color: #081f48;
            margin: 0;
            line-height: 1;
            width: 100%;
            position: relative;
        }

        h1:hover::after {
            content: "Jonathan\ARoman";
            white-space: pre;
            position: absolute;
            top: 5px;
            left: 5px;
            width: 100%;
            text-align: center;
            color: #235c91;
            pointer-events: none;
        }

        .divider {
            width: 1px;
            background-color: #081f48;
            align-self: stretch;
        }

        a {
            color: #1a0dab;
            font-family: Arial, sans-serif;
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="left-half" id="sketch-container"></div>
    <div class="divider"></div>
    <div class="right-half">
        <a href="https://www.linkedin.com/in/jonathan-roman-a9764b203/" target="_blank" style="text-decoration:none;">
            <h1>Jonathan<br>Roman</h1>
        </a>
        <!-- <a style="text-decoration:none;" href="Files/Resume.html">Resume</a><br>
        <a style="text-decoration:none;" href="<?php echo $linkedin_url; ?>" target="_blank">LinkedIn</a><br>
        <a style="text-decoration:none;" href="<?php echo $goodreads_url; ?>" target="_blank">Goodreads</a><br> -->
        <div style="margin-top: 12px;">
            <a style="text-decoration:none;" href="tradegame/index.php">Trade Game</a><br>
            <a style="text-decoration:none;" href="unittracker/index.php">Unit Tracker</a><br>
            <a style="text-decoration:none;" href="healthtracker/index.php">Health Tracker</a>
        </div>
        <div class="photo-container">
            <img src="profile.jpg" alt="Profile photo" class="profile-photo">
        </div>
    </div>

    <script src="Files/rect.js"></script>
</body>
</html>