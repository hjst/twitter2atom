<?php
require_once('config.php');
require_once('lib/twitter/TwitterAPIExchange.php');
require_once('lib/Twitter2Atom.php');
date_default_timezone_set('UTC');

$twitter_api_obj = new TwitterAPIExchange($twitter_config);
$twitter_atom = new Twitter2Atom($twitter_api_obj);

$query = array();
parse_str($_SERVER['QUERY_STRING'], $query);

if (isset($query['op'])) {
  switch ($query['op']) {

    case 'search':
      unset($query['op']);
      if (isset($query['q'])) {
        $q = $query['q']; unset($query['q']);
        $atom = $twitter_atom->search($q, $query);
      } else {
        $error_message = "You must set the 'q' parameter for search.";
      }
      break;

    case 'list':
      unset($query['op']);
      if (isset($query['list_owner']) && isset($query['list_name'])) {
        $list_owner = $query['list_owner']; unset($query['list_owner']);
        $list_name  = $query['list_name']; unset($query['list_name']);
        $atom = $twitter_atom->get_list($list_owner, $list_name, $query);
      } else {
        $error_message = "You must set the 'list_owner' and 'list_name' parameters for lists.";
      }
      break;

    case 'timeline':
      unset($query['op']);
      if (isset($query['user'])) {
        $user = $query['user']; unset($query['user']);
        $atom = $twitter_atom->timeline_user($user, $query);
      } else {
        $atom = $twitter_atom->timeline_home($query);
      }
      break;

    case 'mentions':
      unset($query['op']);
      $atom = $twitter_atom->timeline_mentions($query);
      break;

    default:
      $error_message = "Unrecognised 'op' parameter.";
  }
} else {
  $error_message = "You need to set the 'op' query parameter.";
}

if (isset($error_message)) {
  ob_start();
  include('atom_error.xml');
  $atom = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n" . ob_get_contents();
  ob_end_clean();
}

header('Content-type: application/atom+xml');
print($atom);
