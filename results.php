<!DOCTYPE html>

<html>

    <head>
        <link rel="stylesheet" type="text/css" href="stylesheet.css">
        <meta http-equiv="Cache-Control" content="no-cache">
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta http-equiv="Lang" content="en">
    </head>

    <body>
        <center>
        <div id="result_box">

        <?php

        if(isset($_GET['q']) && $_GET['q']!='') {
            include_once(dirname(__FILE__).'/config.php');
            include_once(dirname(__FILE__).'/lib/TwitterSentimentAnalysis.php');
        
            $TwitterSentimentAnalysis = new TwitterSentimentAnalysis(DATUMBOX_API_KEY,TWITTER_CONSUMER_KEY,TWITTER_CONSUMER_SECRET,TWITTER_ACCESS_KEY,TWITTER_ACCESS_SECRET);

            $twitterSearchParams=array(
                'q'=>$_GET['q'],
                'lang'=>'en',
                'count'=>20,
        );
        $results=$TwitterSentimentAnalysis->sentimentAnalysis($twitterSearchParams);

        ?>


        <h1>Results for "<?php echo $_GET['q']; ?>"</h1>
        <table border="1">
            <tr>
                <td>Id</td>
                <td>created_at</td>
                <td>User</td>
                <td>Text</td>
                <td>Twitter Link</td>
                <td>Sentiment</td>
            </tr>
            <?php
            foreach($results as $tweet) {
                $color=NULL;
                if($tweet['sentiment']=='positive') {
                    $color='#00FF00';
                }
                else if($tweet['sentiment']=='negative') {
                    $color='#FF0000';
                }
                else if($tweet['sentiment']=='neutral') {
                    $color='#FFFFFF';
                }
            ?>
            <tr style="background:<?php echo $color; ?>;">
                <td><?php echo $tweet['id']; ?></td>
                <td><?php echo $tweet['created_at']; ?></td>
                <td><?php echo $tweet['user']; ?></td>
                <td><?php echo $tweet['text']; ?></td>
                <td><a href="<?php echo $tweet['url']; ?>" target="_blank">View</a></td>
                <td><?php echo $tweet['sentiment']; ?></td>
            </tr>
            
            <?php
                }
            	if(!$results) {
            	//echo "<td>Nothing.</td>";
            	}
            ?>    
        </table>


        <?php
       }
    ?>


    <form action="index.php">
    <input type="submit" id='goback' value="Go Back/Search Again"/>
    </form>

    </div>

    </center>
    </body>
</html>
