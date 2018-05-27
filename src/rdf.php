<?

/* Helper functions to deal with RDF serialized as PHP arrays */

function namespaces(){
  $_PREF = array(
           'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#'
          ,'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'
          ,'foaf' =>  'http://xmlns.com/foaf/0.1/'
          ,'dc' => 'http://purl.org/dc/elements/1.1/'
          ,'dct' => 'http://purl.org/dc/terms/'
          ,'sioc' => 'http://rdfs.org/sioc/types#'
          ,'blog' => 'http://vocab.amy.so/blog#'
          ,'as' => 'https://www.w3.org/ns/activitystreams#'
          ,'mf2' => 'http://microformats.org/profile/'
          ,'ldp' => 'http://www.w3.org/ns/ldp#'
          ,'solid' => 'http://www.w3.org/ns/solid#'
          ,'view' => 'https://terms.rhiaro.co.uk/view#'
          ,'asext' => 'https://terms.rhiaro.co.uk/as#'
          ,'dbp' => 'http://dbpedia.org/property/'
          ,'geo' => 'http://www.w3.org/2003/01/geo/wgs84_pos#'
          ,'doap' => 'http://usefulinc.com/ns/doap#'
          ,'time' => 'http://www.w3.org/2006/time#'
        );
  $_NS = array_flip($_PREF);

  $ns = new EasyRdf_Namespace();
  foreach($_PREF as $prefix => $uri){
    EasyRdf_Namespace::set($prefix, $uri);
  }

  return $ns;
}

function get_values($graph, $p, $s=null){
  $ns = namespaces();
  if(!isset($s)){
    $s = get_uri($graph);
  }
  $vs = array();
  if(isset($graph[$s][$ns->expand($p)])){
    foreach($graph[$s][$ns->expand($p)] as $v){
      $vs[] = $v['value'];
    }
    return $vs;
  }else{
    return null;
  }
}
function get_value($graph, $p, $s=null){
  $ns = namespaces();
  $vs = get_values($graph, $p, $s);
  if(is_array($vs)) { return $vs[0]; }
  else return $vs;
}
function has_type($graph, $type, $s=null){
  $ns = namespaces();
  $vs = get_values($graph, "rdf:type", $s);
  $type = $ns->expand($type);
  if(is_array($vs) && in_array($type, $vs)){
    return true;
  }
  return false;
}
function get_uri($graph){
  if(!is_array($graph)){
    return $graph;
  }
  return array_keys($graph)[0];
}

//////////////////

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

?>