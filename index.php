<?
session_start();
date_default_timezone_set(file_get_contents("http://rhiaro.co.uk/tz"));
if(isset($_GET['logout'])){ session_unset(); session_destroy(); header("Location: /salvage"); }
if(isset($_GET['reset'])){ unset($_SESSION[$_GET['reset']]); }

$base = "https://apps.rhiaro.co.uk/salvage";
$_id = "id";
$_type = "type";

function discover_endpoint($url, $rel="micropub"){
  if(isset($_SESSION[$rel])){
    return $_SESSION[$rel];
  }else{
    $res = head_http_rels($url);
    $rels = $res['rels'];
    if(!isset($rels[$rel][0])){
      $parsed = json_decode(file_get_contents("https://pin13.net/mf2/?url=".$url), true);
      if(isset($parsed['rels'])){ $rels = $parsed['rels']; }
    }
    if(!isset($rels[$rel][0])){
      // TODO: Try in body
      return "Not found";
    }
    $_SESSION[$rel] = $rels[$rel][0];
    return $rels[$rel][0];
  }
}

function context(){
  return array(
      "@context" => array("http://www.w3.org/ns/activitystreams#")
    );
}

function get_feed(){
  
  $source = urldecode($_SESSION['url']);
  $ch = curl_init($source);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/activity+json"));
  $response = curl_exec($ch);
  curl_close($ch);
  $collection = json_decode($response, true);
  return $collection;

}

function is_image($item){
  global $_id;
  global $_type;
  if(isset($item[$_type]) && $item[$_type] == "Photo"){
    return true;
  }
  $imgs = array("jpg", "jpeg", "png", "gif");
  $path = explode("/", $item[$_id]);
  $uid = array_pop($path);
  $fn = explode(".", $uid);
  $ext = strtolower(array_pop($fn));
  if(in_array($ext, $imgs)){
    return true;
  }
  return false;
}

function id_from_object($object){
  global $_id;
  return $object[$_id];
}
function arrayids_to_string($array){
  $flat = array_map("id_from_object", $array);
  return implode(",", $flat);
}

function url_to_objectid($url){
  return array("id" => trim($url));
}
function url_strings_to_array($urls){
  $ar = explode(",", $urls);
  return array_map("url_to_objectid", $ar);
}

function form_to_update($post){
  $context = context();
  $type = array("type" => "Update");
  $data = array_merge($context, $type);
  $data['name'] = "Updated an object";
  $data['published'] = date(DATE_ATOM);
  $data['object'] = $post;
  unset($data['object']['submit']);
  
  // TODO: Should really handle empty values on the server end I think. 
  //       ie. It shouldn't set new attributes on the server it receives empty values for attributes that weren't previously set.
  //       Depends on replace/update policy
  // foreach($post as $k => $v){
  //   if(empty($v) || $v == ""){
  //     unset($data[$k]);
  //   }
  // }

  if(isset($data['object']['tags'])){
    $data['object']['tag'] = url_strings_to_array($data['object']['tags']);
    unset($data['object']['tags']);
  }

  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  return $json;
}

function post_to_endpoint($json, $endpoint){
  $ch = curl_init($endpoint);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/activity+json"));
  curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: ".$_SESSION['key']));
  curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
  $response = Array();
  parse_str(curl_exec($ch), $response);
  $info = curl_getinfo($ch);
  curl_close($ch);
  
  return $response;
}

function get_prefs($domain){
  // TODO: get http://www.w3.org/ns/pim/space#preferencesFile from $domain
  $prefsjson = '
{
  "@context": ["http://vocab.amy.gy/prefs#", {"sal": "http://apps.rhiaro.co.uk/salvage/terms#"}],
  "applications": 
  [{
    "@id": "http://apps.rhiaro.co.uk/salvage",
    "sal:weeklyBudget": "100",
    "sal:monthlyBudget": "450",
    "sal:feed": {
        "@id": "http://rhiaro.co.uk/stuff"
      },
    "sal:categories": [
      {
        "sal:name": "Food",
        "sal:tags": ["food", "groceries", "restaurant", "takeaway"]
      },
      {
        "sal:name": "Medical",
        "sal:tags": ["medical"]
      },
      {
        "sal:name": "Travel",
        "sal:tags": ["travel", "transport"]
      },
      {
        "sal:name": "Other",
        "sal:tags": []
      },
      {
        "sal:name": "Split with csarven",
        "sal:tags": ["csarven"]
      }
    ]
  }]
}
  ';
  $prefs = json_decode($prefsjson, true);
  if(isset($prefs["applications"])){
    $apps = $prefs["applications"];
    foreach($apps as $app){
      if($app["@id"] == "http://apps.rhiaro.co.uk/salvage"){
        return $app;
      }
    }
  }
}

function get_week(){
  // PHP you so rad
  $today = new DateTime();
  if($today->format("D") == "Mon"){
    $start = new DateTime("midnight today");
    $end = new DateTime("midnight today + 7 days - 1 minute");
  }else{
    $start = new DateTime("last Monday");
    $end = new DateTime("last Monday + 7 days - 1 minute");
  }
  return array("start" => $start, "end" => $end);
}

function sort_week($feed){
  global $_id;

  $week = get_week();  
  $prefs = get_prefs("http://rhiaro.co.uk");
  var_dump($prefs);
  
  $categories = array();
  
  foreach($feed["items"] as $i => $item){
    $pub = new DateTime($item["published"]);
    if($pub > $week["start"] && $pub <= $week["end"]){

      // Holy shit php you are amazing
      $amt = floatval(substr($item["http://vocab.amy.so/blog#cost"], 1));
      $cur = $item["http://vocab.amy.so/blog#cost"][0];
      if($cur == "$"){ // TODO: One day maybe do currency conversion...
        
        foreach($prefs["sal:categories"] as $cat){
          var_dump($cat);
          $name = $cat["sal:name"];
          $tags = $cat["sal:tags"];
          if(!isset($categories[$name])){
            $categories[$name] = array("total" => 0, "items" => array());
          }      
          // TODO: tags shouldn't be like this in feed
          $r = array_intersect($tags, $item["tag"]);
          
          if(count($r) > 0){
            $categories[$name]["items"][] = $item;
            $categories[$name]["total"] += $amt;
          }elseif(empty($tags)){ // Other
            $categories[$name]["items"][] = $item;
            $categories[$name]["total"] += $amt;
          }

        }

      }
    }
  }
  return $categories;
}

function get_total($subset){
  $total = 0;
  foreach($subset as $name => $cat){
    $total += $cat["total"];
  }
  return $total;
}

function under_weekly_budget($total, $domain="http://rhiaro.co.uk"){
  $prefs = get_prefs($domain);
  if(isset($prefs["sal:weeklyBudget"])){
    if($prefs["sal:weeklyBudget"] >= $total){
      return true;
    }else{
      return false;
    }
  }else{
    return null;
  }
}

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
}

$week = get_week();

?>
<!doctype html>
<html>
  <head>
    <title>Salvage</title>
    <link rel="stylesheet" type="text/css" href="https://apps.rhiaro.co.uk/css/normalize.min.css" />
    <link rel="stylesheet" type="text/css" href="https://apps.rhiaro.co.uk/css/main.css" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
     h3 input { font-weight: bold; }
     form#feed { border-bottom: 1px solid silver; }
    </style>
  </head>
  <body>
    <main class="w1of2 center">
      <h1>Salvage</h1>
      <p>AS2 Consumer.</p>

      <?if(isset($errors)):?>
        <div class="fail">
          <?foreach($errors as $key=>$error):?>
            <p><strong><?=$key?>: </strong><?=$error?></p>
          <?endforeach?>
        </div>
      <?endif?>
      

      <form role="form" id="feed">
        <p><label for="url" class="neat">Feed</label> <input type="url" class="neat" id="url" name="url" value="<?=isset($_SESSION['url']) ? urldecode($_SESSION['url']) : "http://rhiaro.co.uk/stuff"?>" />
        <input type="submit" value="Get" /></p>
      </form>

      <?if(isset($asfeed)):?>
        <? $results = sort_week($asfeed); ?>

        <h2>This week is <?=$week["start"]->format("jS M y")?> - <?=$week["end"]->format("jS M y")?></h2>
        <?if(under_weekly_budget(get_total($results))):?>
          <p class="win">You can spend more money this week!</p>
        <?else:?>
          <p class="fail">You are over budget, stop.</p>
        <?endif?>

        <?foreach($results as $cat => $info):?>
          <h3><?=$cat?>: $<?=number_format($info["total"], 2)?> (<?=count($info["items"])?>)</h3>
          <ul class="wee">
            <?foreach($info["items"] as $one):?>
              <li><a href="<?=$one[$_id]?>"><?=$one["published"]?></a> <?=$one["http://vocab.amy.so/blog#cost"]?> <?=$one["summary"]?></li>
            <?endforeach?>
          </ul>
        <?endforeach?>

      <?elseif(isset($_SESSION['url'])):?>
        <p class="fail">Could not find a valid AS2 feed here.</p>

      <?endif?>

      <div class="color3-bg inner">
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
      </div>
    </main>
  </body>
</html>