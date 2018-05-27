<?php
namespace Rhiaro;

use \DateTime;
use EasyRdf_Graph;
use Requests;
use phpish\link_header;

function get_feed($url){
  $response = Requests::get($url, array('Accept' => 'text/turtle'));
  $graph = new EasyRdf_Graph();
  $graph->parse($response->body, 'turtle');
  return $graph->toRdfPhp();
}

function get_page_uri($graph){
  $ns = namespaces();
  foreach($graph as $uri => $item){
    if(has_type($graph, "as:CollectionPage", $uri)){
      // Let's pretend/hope there's only one
      return $uri;
    }
  }
}

function prev_page($collection_page_graph){
    $prev = get_value($collection_page_graph, "as:prev");
    $feed = get_feed($prev);
    return $feed;
}

/*
 * Pages backwards ('prev') through a Paged Collection and stops when the end date is reached. Assumes collection is ordered. 
 */
function get_posts_between($start, $end, $graph, $page_uri, $posts=array(), $count=0){
  $contents = get_item_uris($graph, $page_uri);
  $count += count($contents);
  
  foreach($contents as $uri){
    $post = $graph[$uri];
    $published = get_value(array($uri=>$post), "as:published", $uri);
    $pubdate = new DateTime($published);
    if($pubdate >= $start && $pubdate <= $end){
      $posts[$uri] = $post;
    }
  }
  
  if(count($posts) >= $count){
    $prev_uri = get_value($graph, "as:prev", $page_uri);
    $prev_page = prev_page(array($page_uri=>$graph[$page_uri]));
    $posts = get_posts_between($start, $end, $prev_page, $prev_uri, $posts, $count);
  }

  return $posts;
}

function get_item_uris($graph, $collection_uri){
  $uris = get_values($graph, "as:items", $collection_uri);
  return $uris;
}

function get_month($url, $week_start="1 week ago"){

  $ns = namespaces();

  // Get month of $week_start
  $date = new DateTime($week_start);
  $month = $date->format("F");
  $start = day_of_month("first", "day", $month);
  $end = day_of_month("23:59:59, last", "day", $month);

  // Fetch feed
  $graph = get_feed($url);
  if(isset($graph[$url])){
    $feed = $graph[$url];
    $contents = get_item_uris($graph, $url);
    $total = $feed[$ns->expand("as:totalItems")];
    if(isset($content)){
      $count = count($items);
    }else{
      $count = 0;
    }
    // TODO: catch missing totalItems
    if($count < $total){
      // This probably means it's a CollectionPage, so use this.
      $page_uri = get_page_uri($graph);
      $contents = get_item_uris($graph, $page_uri);
    }
  }

  // Page through collection and keep posts in the current month
  $posts = get_posts_between($start, $end, $graph, $page_uri);
  return $posts;

}

function sort_week($feed=null, $week=null){
  
  if(!isset($week)){
    $week = this_week();  
    $month = this_month();
  }else{
    $m = $week["start"]->format("F");
    $month["start"] = day_of_month("first", "day", $m);
    $month["end"] = day_of_month("last", "day", $m);
  }
  
  $categories = array();
  $ids = array();
  $total = 0;
  $eur_total = $usd_total = $gbp_total = 0;
  
  foreach($feed["items"] as $uri => $item){
    
    $pub = new DateTime(get_value(array($uri=>$item), "as:published"));
    $eur = get_value(array($uri=>$item), "asext:amountEur");
    $usd = get_value(array($uri=>$item), "asext:amountUsd");
    $gbp = get_value(array($uri=>$item), "asext:amountGbp");

    $eur_total += $eur;
    $usd_total += $usd;
    $gbp_total += $gbp; // HERENOW

        if($pub > $week["start"] && $pub <= $week["end"]){
          if(!in_array($item["@id"], $ids)){
            $total += $amt;
            $ids[] = $item["@id"];
          }
          
          if(isset($prefs)){
            foreach($prefs["sal:categories"] as $cat){
              $name = $cat["sal:name"];
              $tags = $cat["sal:tags"];
              if(!isset($categories[$name])){
                $categories[$name] = array("total" => 0, "items" => array());
              }      
              // TODO: tags shouldn't be like this in feed
              if(!is_array($item["tag"])){
                $item["tag"] = array($item["tag"]);
              }
              $r = array_intersect($tags, $item["tag"]);
              
              if(count($r) > 0){
                $categories[$name]["items"][] = $item;
                $categories[$name]["total"] += $amt;
              }elseif(empty($tags)){ // All
                $categories[$name]["items"][] = $item;
                $categories[$name]["total"] += $amt;
              }

            }
          }else{
            $categories["Total"]["items"][] = $item;
            $categories["Total"]["total"] += $amt;
          }
        }

      }
    }
  }
  $categories["total"] = array("week" => $total, "month" => $month_total);
  return $categories;
}

function budget_remaining($total, $week){
  $prefs = get_prefs($_SESSION['me']);
  $budget = array();
  if(isset($prefs["sal:monthlyBudget"])){
    $budget["month"] = relative_budget_remaining($total["month"], $week["start"], $prefs["sal:monthlyBudget"]);
  }
  if(isset($prefs["sal:weeklyBudget"])){
    $budget["week"] = $prefs["sal:weeklyBudget"] - $total["week"];
  }
  return $budget;
}

function relative_budget_remaining($total, $week_start, $monthly_budget){
  $month = $week_start->format("F Y");
  $month_start = day_of_month("first", "day", $month);
  $month_end = day_of_month("last", "day", $month);

  $monthly_remaining = $monthly_budget - $total;
  $days_remaining = $month_end->format("j") - $week_start->format("j") +1;
  $weeks_remaining = ceil($days_remaining / 7);
  $per_week_remaining = $monthly_remaining / $weeks_remaining;

  if($per_week_remaining > 100){
    return 100;
  }else{
    return $per_week_remaining;
  }
}

/****** Date stuff ********/

function get_tz($url){
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/ld+json"));
  $response = curl_exec($ch);
  $response = json_decode($response, true);
  $tz = $response["time:timeZone"];
  return $tz;
}

function this_week(){
  return week_of();
}

function this_month(){
  $today = new DateTime();
  $month = $today->format("F");
  $start = day_of_month("first", "day", $month);
  $end = day_of_month("last", "day", $month);
  return array("start" => $start, "end" => $end);
}

function day_of_month($when, $day, $month){
  if(!isset($when)) $when = "first";
  if(!isset($day)) $day = "monday";
  return new DateTime("$when $day of $month");
}

function week_of($date="today"){
  // Weeks start at 00:00:00 Monday and end at 23:59:59 Sunday.

  $cur = new DateTime($date);
  $start = new DateTime($date);
  $end = new DateTime($date);

  if($cur->format("D") == "Mon"){
    $start->modify("midnight today");
    $end->modify("midnight today + 7 days - 1 minute");
  }else{
    $start->modify("last Monday");
    $end->modify("last Monday + 7 days - 1 minute");
  }
  return array("start" => $start, "end" => $end);
}

date_default_timezone_set(get_tz("https://rhiaro.co.uk/tz"));

?>