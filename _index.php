<?



// function auth($code, $state, $client_id="https://apps.rhiaro.co.uk/obtainium"){
  
//   $params = "code=".$code."&redirect_uri=".urlencode($state)."&state=".urlencode($state)."&client_id=".$client_id;
//   $ch = curl_init("https://indieauth.com/auth");
//   curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//   curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded", "Accept: application/json"));
//   curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
//   //curl_setopt($ch, CURLOPT_HEADERFUNCTION, "dump_headers");
//   $response = curl_exec($ch);
//   $response = json_decode($response, true);
//   $_SESSION['me'] = $response['me'];
//   $info = curl_getinfo($ch);
//   curl_close($ch);
  
//   if(isset($response) && ($response === false || $info['http_code'] != 200)){
//     $errors["Login error"] = $info['http_code'];
//     if(curl_error($ch)){
//       $errors["curl error"] = curl_error($ch);
//     }
//     return $errors;
//   }else{
//     return true;
//   }
// }

// function get_access_token($code, $state, $client_id="https://apps.rhiaro.co.uk/obtainium"){
  
//   $params = "me={$_SESSION['me']}&code=$code&redirect_uri=".urlencode($state)."&state=".urlencode($state)."&client_id=$client_id";
//   $token_ep = discover_endpoint($_SESSION['me'], "token_endpoint");
//   $ch = curl_init($token_ep);
//   curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//   curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded"));
//   curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
//   $response = Array();
//   parse_str(curl_exec($ch), $response);
//   $info = curl_getinfo($ch);
//   curl_close($ch);
  
//   if(isset($response) && ($response === false || $info['http_code'] != 200)){
//     $errors["Login error"] = $info['http_code'];
//     if(curl_error($ch)){
//       $errors["curl error"] = curl_error($ch);
//     }
//     return $errors;
//   }else{
//     $_SESSION['access_token'] = $response['access_token'];
//     return true;
//   }
  
// }

// function discover_endpoint($url, $rel="micropub"){
//   if(isset($_SESSION[$rel])){
//     return $_SESSION[$rel];
//   }else{
//     $res = head_http_rels($url);
//     $rels = $res['rels'];
//     if(!isset($rels[$rel][0])){
//       $parsed = json_decode(file_get_contents("https://pin13.net/mf2/?url=".$url), true);
//       if(isset($parsed['rels'])){ $rels = $parsed['rels']; }
//     }
//     if(!isset($rels[$rel][0])){
//       // TODO: Try in body
//       return "Not found";
//     }
//     $_SESSION[$rel] = $rels[$rel][0];
//     return $rels[$rel][0];
//   }
// }

include "link-rel-parser.php";

session_start();
date_default_timezone_set(get_tz("https://rhiaro.co.uk/tz"));
if(isset($_GET['logout'])){ session_unset(); session_destroy(); header("Location: /salvage"); }
if(isset($_GET['reset'])){ unset($_SESSION[$_GET['reset']]); }

// $base = "https://apps.rhiaro.co.uk/salvage";
// //$base = "http://localhost";
// if(isset($_GET['code'])){
//   $auth = auth($_GET['code'], $_GET['state']);
//   if($auth !== true){ $errors = $auth; }
//   else{
//     $response = get_access_token($_GET['code'], $_GET['state']);
//     if($response !== true){ $errors = $auth; }
//     else {
//       header("Location: ".$_GET['state']);
//     }
//   }
// }

$_id = "id";
$_type = "type";

// function get_prefs($domain){
//   // TODO: get http://www.w3.org/ns/pim/space#preferencesFile from $domain
//   $prefsfile = discover_endpoint($domain, "http://www.w3.org/ns/pim/space#preferencesFile");
//   $prefsjson = file_get_contents($prefsfile);
//   $prefs = json_decode($prefsjson, true);
//   if(isset($prefs["applications"])){
//     $apps = $prefs["applications"];
//     foreach($apps as $app){
//       if($app["@id"] == "http://apps.rhiaro.co.uk/salvage"){
//         return $app;
//       }
//     }
//   }
// }

// Store config stuff
if(isset($_GET['url'])){
  $_SESSION['url'] = $_GET['url'];
}

// Fetch feed
if(isset($_SESSION['url'])){
  $asfeed = get_feed();
  // Fuck this
  if(isset($asfeed["@type"])){
    $_type = "@type";
    $_id = "@id";
  }
}elseif(isset($_SESSION['me'])){
  $prefs = get_prefs($_SESSION['me']);
  if(isset($prefs["sal:feed"]["@id"])){
    $_SESSION['url'] = $prefs["sal:feed"]["@id"];
    $asfeed = get_feed();
  }
}
if(isset($_GET['week'])){
  $week = week_of($_GET['week']);
}else{
  $week = this_week();
}
$next = new DateTime($week["start"]->format("Y-m-d"));
$prev = new DateTime($week["start"]->format("Y-m-d"));
$next->modify("+ 7 days");
$prev->modify("- 7 days");

?>
<!doctype html>
<html>
  <head>
    <title>Salvage</title>
    <link rel="stylesheet" type="text/css" href="https://rhiaro.co.uk/views/normalize.min.css" />
    <link rel="stylesheet" type="text/css" href="https://rhiaro.co.uk/views/core.css" />
    <link rel="stylesheet" type="text/css" href="https://rhiaro.co.uk/views/base.css" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
     h3 input { font-weight: bold; }
     form#feed { border-bottom: 1px solid silver; }
    </style>
  </head>
  <body>
    <article>
      <h1>Salvage</h1>
      <p>AS2 Consumer.</p>

      <?if(isset($errors)):?>
        <div class="fail">
          <?foreach($errors as $key=>$error):?>
            <p><strong><?=$key?>: </strong><?=$error?></p>
          <?endforeach?>
        </div>
      <?endif?>
      
      <?if(!isset($_SESSION['me'])):?>
        <p class="fail">Sign in so I can look for your budget preferences.</p>
      <?endif?>
      <form role="form" id="feed">
        <p><label for="url" class="neat">Feed</label> <input type="url" class="neat" id="url" name="url" value="<?=isset($_SESSION['url']) ? urldecode($_SESSION['url']) : ""?>" placeholder="http://rhiaro.co.uk/stuff" /></p>
        <p><label for="week" class="neat">Date</label> 
          <input type="date" name="week" id="week" placeholder="<?=isset($week) ? $week["start"]->format("Y-m-d") : "yyyy-mm-dd"?>" value="<?=isset($week) ? $week["start"]->format("Y-m-d") : ""?>" />
          <input type="submit" value="Get" />
          <a href="?week=<?=$prev->format("Y-m-d")?>">&lt; Prev</a> 
          <a href="?week=<?=$next->format("Y-m-d")?>">Next &gt;</a>
        </p>
      </form>

      <?if(isset($asfeed)):?>
        <? $results = sort_week($asfeed, $week); ?>
        <? $left = budget_remaining($results["total"], $week); ?>
        <? $week_max = $left["month"] - $results["total"]["week"]; ?>
        <h2>Week of <?=$week["start"]->format("jS M y")?> - <?=$week["end"]->format("jS M y")?></h2>
        <?if($left["week"] > 0):?>
          <p class="win">You are under your weekly budget by $<?=number_format($left["week"], 2)?> this week!</p>
        <?endif?>
        <?if($left["month"] > 0):?>
          <?if($left["week"] > 0):?>
            <p class="win">You can spend another $<?=number_format($left["week"], 2)?> this week!</p>
          <?else:?>
            <p class="fail">You are over your weekly budget ($<?=number_format($left["week"], 2)?>), stop.</p>
          <?endif?>
          <?if($week_max > 0):?>
            <p class="win">You have an additional $<?=number_format($week_max)?> from underspending earlier this month.</p>
          <?endif?>
        <?elseif($left !== null):?>
          <p class="fail"><strong>Monthly budget breach alert:</strong> spent $<?=number_format($results["total"]["month"], 2)?> this month.</p>
        <?endif?>

        <?=var_dump($left["month"])?>

        <?foreach($results as $cat => $info):?>
          <?if(is_array($info)):?>
            <h3><?=$cat?>: $<?=number_format($info["total"], 2)?> (<?=count($info["items"])?>)</h3>
            <ul class="wee">
              <?foreach($info["items"] as $one):?>
                <li><a href="<?=$one[$_id]?>"><?=$one["published"]?></a> <?=$one["http://vocab.amy.so/blog#cost"]?> <?=$one["summary"]?></li>
              <?endforeach?>
            </ul>
          <?endif?>
        <?endforeach?>

      <?elseif(isset($_SESSION['url'])):?>
        <p class="fail">Could not find a valid AS2 feed here.</p>
      <?endif?>

<!--       <div class="color3-bg inner">
        <?if(isset($_SESSION['me'])):?>
          <p class="wee">You are logged in as <strong><?=$_SESSION['me']?></strong> <a href="?logout=1">Logout</a></p>
        <?else:?>
          <form action="https://indieauth.com/auth" method="get" class="inner clearfix">
            <label for="indie_auth_url">Domain:</label>
            <input id="indie_auth_url" type="text" name="me" placeholder="yourdomain.com" />
            <input type="submit" value="signin" />
            <input type="hidden" name="client_id" value="http://rhiaro.co.uk" />
            <input type="hidden" name="redirect_uri" value="<?=$base?>" />
            <input type="hidden" name="state" value="<?=$base?>" />
            <input type="hidden" name="scope" value="post" />
          </form>
        <?endif?>
      </div> -->
    </article>
  </body>
</html>