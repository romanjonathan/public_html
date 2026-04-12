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
            padding-left: 0px; 
            padding-right: 50px; 
            /*padding-top: 30px; */
            /*padding-bottom: 30px; */
            display: flex; 
            justify-content: center; /* Center the canvas inside the half */
            align-items: center; 
        }
        
        .right-half {
            width: 50%;
            /* Add padding to prevent content from touching the edge */
            padding-left: 0px; 
            /*box-sizing: border-box;*/
            text-align: left;
            /* padding-top: 10px; /* Space from the top */
        }

        h1 {
            /* Optional: style the name */
            font-family: Arial, sans-serif;
        }

        a {
            /* Style links */
            color: #1a0dab;
            font-family: Arial, sans-serif;
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="left-half" id="sketch-container"></div>
    <div class="right-half">
        <?php echo "<h1>" . $my_name . "</h1>"; ?>
        <!-- <a style="text-decoration:none;" href="Files/Resume.html">Resume</a><br>
        <a style="text-decoration:none;" href="<?php echo $linkedin_url; ?>" target="_blank">LinkedIn</a><br>
        <a style="text-decoration:none;" href="<?php echo $goodreads_url; ?>" target="_blank">Goodreads</a><br> -->
    </div>

    <script src="Files/rect.js"></script>
</body>
</html>