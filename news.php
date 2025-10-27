<?php
  if (session_status() == PHP_SESSION_NONE) {
      session_start();
  }
  if(!isset($_SESSION["HeliUser"])){
    header("Location: index.php");
  }

  $page = "news";
  include_once "header.php";
  echo "<style>.content{ background: #DEF1F8; }</style>";
  include_once "assets/php/db_connect.php";

  //remove notifications
  $mysqli->query("DELETE FROM news_notifications WHERE user_id=$_SESSION[HeliUser]");
  echo "<div class='inner-md'><h2 class='page-header text-center'>Helicopters Offshore News Feed</h2>";
  $page = 0;
  if(isset($_GET["page"]))
    $page = $_GET["page"];
  $offset = $page*20;
  $query = "SELECT * FROM news ORDER BY `timestamp` DESC LIMIT 20 OFFSET $offset";
  $result = $mysqli->query($query);
  if($result != false){
    $content = "<div class='col-md-12 center-block' id='post-container'>";
    $posts = "";
    while($row = $result->fetch_assoc()){
      if($row["link"] != null){
        // $url = mysql_real_escape_string($row["link"]);
        // $ch = curl_init($url);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        // curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.71 Safari/537.36");
        // $res = curl_exec($ch);
        // $xml = simplexml_load_string($res);
        // $linkImage = "uploads/posts/default_link.jpg";
        // for($i = 0; $i < count($xml["meta"]); $i++){
        //   if(strpos($xml["meta"][$i], "og:image") !== FALSE){
        //     $linkImage = substr(strpos($xml["meta"][$i], "content=")+9, stripos($xml["meta"][$i], '"', strpos($xml["meta"][$i], "content=")+9));
        //   }
        // }
        $linkImage = "uploads/news/default_link.jpg";
        $url = $mysqli->real_escape_string("https://api.embed.ly/1/oembed?key=ebb72175376846d3a06a59d3df3895dc&url=".$row["link"]);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.71 Safari/537.36");
        $res = curl_exec($ch);
        $linkRes = json_decode($res, true);
        if(isset($linkRes["thumbnail_url"]))
          $linkImage = $linkRes["thumbnail_url"];
      }
      $posts .= "<div class='post'><div class='post-title-bar'><span class='post-title'>".$row["post_title"]."</span></div><div class='post-date' data-time='$row[timestamp]'></div><div class='post-content'><div class='post-message'>$row[message]</div>".($row["image"] != null ? "<div class='post-image'><img src='uploads/news/$row[image]' /></div>" : "").($row["link"] != null ? "<div class='post-link-container'><div class='post-link-text'>Link: <a href='$row[link]'>$row[link]</a></div><div class='post-link-preview'><a href='$row[link]'><img src='$linkImage'></a></div></div>" : "")."</div></div>";
    }
    if($posts == ""){
      $posts = "<h3>No news here yet. Check back soon.</h3>";
    }
    $content .= $posts."</div>";
    echo $content;
  }else{
    echo "ERROR: ".$mysqli->error;
  }
  include_once "footer.php";
?>