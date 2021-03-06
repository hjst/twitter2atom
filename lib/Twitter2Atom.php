<?php
/**
 * Create Atom link feeds from the Twitter REST API v1.1
 *
 * Dependencies: (these classes must be loaded)
 *    TwitterAPIExchange: https://github.com/J7mbo/twitter-api-php
 *    RollingCurl:        https://github.com/hjst/rolling-curl
 *    RollingCurlRequest: https://github.com/hjst/rolling-curl
 *
 * @author Henry Todd <henry@hjst.org>
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @copyright 2013 Henry Todd
 */
class Twitter2Atom
{
  /**
   * Twitter REST API v1.1 resource URLs
   * 
   * For full details see: https://dev.twitter.com/docs/api/1.1
   */
  protected $resource_url = array(
    'lists/statuses'              => 'https://api.twitter.com/1.1/lists/statuses.json'
    ,'search/tweets'              => 'https://api.twitter.com/1.1/search/tweets.json'
    ,'statuses/home_timeline'     => 'https://api.twitter.com/1.1/statuses/home_timeline.json'
    ,'statuses/mentions_timeline' => 'https://api.twitter.com/1.1/statuses/mentions_timeline.json'
    ,'statuses/user_timeline'     => 'https://api.twitter.com/1.1/statuses/user_timeline.json'
  );

  /**
   * Array containing instance-specific config
   */
  protected $config = array();

  /**
   * Twitter API handler object (TwitterAPIExchange)
   */
  protected $twitter;

  /**
   * Used by the unshorten_callback to stash links from async curl responses
   */
  protected $shortened_links = array();

  /**
   * Implements Josh Fraser's "RollingCurl" API
   */
  protected $async_curl = null;

  /**
   * Constructor, simply pass in a TwitterAPIExchange object
   */
  public function __construct($config) {
    $this->config = $config;
    $this->twitter = new TwitterAPIExchange($this->config);
    $this->async_curl = new RollingCurl(array($this, 'unshorten_callback'));
    $this->async_curl->options = array(CURLOPT_USERAGENT => $this->config['user_agent']);
    $this->async_curl->window_size = $this->config['max_concurrent_curl_connections'];
  }

  /**
   * Fetch links from a Twitter List and convert to Atom
   *
   * This method will pull tweets (a 'count' can be specified in $opts) from the
   * specified Twitter List, discard any that don't contain links, and return an
   * Atom XML feed.
   *
   * API docs: https://dev.twitter.com/docs/api/1.1/get/lists/statuses
   *
   * @todo Find a way around PHP's reserved keyword, this method should be list()
   *       See: http://www.php.net/manual/en/reserved.keywords.php#93368
   * @param string $list_owner Twitter username of the list owner.
   * @param string $list_name Twitter URL slug of the list, i.e. not a numeric id
   * @param array $opts Optional parameters, see Twitter API docs.
   * @return string Atom XML.
   */
  public function get_list($list_owner, $list_name, $opts=array()) {
    $api_params = array_merge($opts, array(
      'slug' => $list_name,
      'owner_screen_name' => $list_owner
    ));
    $links = $this->get_links_from_api(
      $this->resource_url['lists/statuses'],
      $api_params
    );
    return $this->render_as_atom(
      $feed_title = "$list_name list by $list_owner",
      $feed_url = "https://twitter.com/$list_owner/lists/$list_name",
      $links
    );
  }

  /**
   * Fetch links from a Twitter Search and convert to Atom
   *
   * This method will pull tweets (a 'count' can be specified in $opts) from the
   * specified Twitter Search (adding a filter:links to eliminate tweets without
   * links) and return an Atom XML feed.
   *
   * API docs: https://dev.twitter.com/docs/api/1.1/get/search/tweets
   *
   * @param string $query The search query, same as https://twitter.com/search?q=
   * @param array $opts Optional parameters, see Twitter API docs.
   * @return string Atom XML.
   */
  public function search($query, $opts=array()) {
    $api_params = array_merge($opts, array(
      'q' => $query .'+filter:links' // safe, Twitter ignores repeat filters
    ));
    $links = $this->get_links_from_api(
      $this->resource_url['search/tweets'],
      $api_params
    );
    return $this->render_as_atom(
      $feed_title = "Twitter search: $query",
      $feed_url = "https://twitter.com/search?q=$query",
      $links
    );
  }

  /**
   * Fetch links from the auth'd user's Twitter Timeline and convert to Atom
   *
   * This method will pull tweets (a 'count' can be specified in $opts) from the
   * Timeline of the authenticated user (via OAuth) and return an Atom XML feed.
   *
   * API docs: https://dev.twitter.com/docs/api/1.1/get/statuses/home_timeline
   *
   * @param array $opts Optional parameters, see Twitter API docs.
   * @return string Atom XML.
   */
  public function timeline_home($opts=array()) {
    $links = $this->get_links_from_api(
      $this->resource_url['statuses/home_timeline'],
      $opts
    );
    return $this->render_as_atom(
      $feed_title = "Twitter Timeline",
      $feed_url = "https://twitter.com",
      $links
    );
  }

  /**
   * Fetch links from the auth'd user's Twitter Mentions and convert to Atom
   *
   * This method will pull tweets (a 'count' can be specified in $opts) from the
   * Mentions of the authenticated user (via OAuth) and return an Atom XML feed.
   *
   * API docs: https://dev.twitter.com/docs/api/1.1/get/statuses/mentions_timeline
   *
   * @param array $opts Optional parameters, see Twitter API docs.
   * @return string Atom XML.
   */
  public function timeline_mentions($opts=array()) {
    $links = $this->get_links_from_api(
      $this->resource_url['statuses/mentions_timeline'],
      $opts
    );
    return $this->render_as_atom(
      $feed_title = "Twitter Mentions",
      $feed_url = "https://twitter.com/mentions",
      $links
    );
  }

  /**
   * Fetch links from a user's Twitter Timeline and convert to Atom
   *
   * This method will pull tweets (a 'count' can be specified in $opts) from the
   * Timeline of the specified user and return an Atom XML feed.
   *
   * API docs: https://dev.twitter.com/docs/api/1.1/get/statuses/user_timeline
   *
   * @param string $user The Twitter username of the Timeline.
   * @param array $opts Optional parameters, see Twitter API docs.
   * @return string Atom XML.
   */
  public function timeline_user($user, $opts=array()) {
    $api_params = array_merge($opts, array(
      'screen_name' => $user
    ));
    $links = $this->get_links_from_api(
      $this->resource_url['statuses/user_timeline'],
      $api_params
    );
    return $this->render_as_atom(
      $feed_title = "Twitter: @$user",
      $feed_url = "https://twitter.com/$user",
      $links
    );
  }

  /**
   * Call the Twitter API and convert the result to links
   *
   * @todo Add some error checking/logging to API responses
   * @param string $resource The URL for the API resource.
   * @param array $params Array of query params to pass to the API.
   * @param string $method The HTTP method to use, defaults to GET.
   * @return array Link objects suitable for rendering as Atom.
   */
  protected function get_links_from_api($resource, $params, $method='GET') {
    if (count($params) > 0) {
      $query_string = '?' . http_build_query($params);
      $this->twitter->setGetfield($query_string);
    }
    $api_response = $this->twitter->buildOauth($resource, $method)->performRequest();
    $tweets = json_decode($api_response);
    if (is_object($tweets) && property_exists($tweets, 'statuses')) {
      // HACK: normalise API responses (compare search & list results for example)
      $tweets = $tweets->statuses;
    }
    // build an array of link objects from the url entities in the Twitter API response
    $links = array();
    date_default_timezone_set('UTC'); // HACK: stops timezone warnings with date/strtotime
    foreach ($tweets as $tweet) {
      foreach ($tweet->entities->urls as $url_entity) {
        $atom_entry = new StdClass();
        $atom_entry->id = "https://twitter.com/{$tweet->user->screen_name}/statuses/{$tweet->id_str}";
        $atom_entry->link = $url_entity->expanded_url;
        $atom_entry->title = $this->clean_title($tweet->text, $url_entity->indices);
        $atom_entry->content = "<b>{$tweet->user->name} (@{$tweet->user->screen_name}):</b> ". $tweet->text;
        $atom_entry->updated = date('c', strtotime($tweet->created_at));
        $atom_entry->author_name = $tweet->user->name;
        $links[] = $atom_entry;
      }
    }
    // filter the link objects against the domain blacklist
    if (isset($this->config['domain_blacklist'])) {
      $links = array_filter($links, array($this, 'blacklist_callback'));
    }
    // optionally unshorten the URLs in the link objects
    if (isset($params['unshorten_links']) && $params['unshorten_links'] == 1) {
      $links = $this->unshorten($links);
    }
    return $links;
  }

  /**
   * Expand URLs using parallel/recursive curl
   *
   * Use Josh Fraser's RollingCurl lib to recursively follow the
   * links to remove all URL shortening and redirects. This has
   * the potential to trigger hundreds of HTTP requests so use with
   * care.
   *
   * @param array $links An array of link objects.
   * @return array Modified array of link objects.
   */
  protected function unshorten($links) {
    $this->async_curl->options = array(CURLOPT_HEADER => true, CURLOPT_NOBODY => true); 
    foreach($links as $link) {
      $req = new RollingCurlRequest($link->link);
      $req->callback_payload = $link;
      $this->async_curl->add($req);
    }
    $this->async_curl->execute();
    // The callback method stashes the shortened link objects in
    // $this->shortened_links. After async_curl->execute() returns
    // we can now sort & return the array of shortened links:
    usort($this->shortened_links, array($this, 'sort_links'));
    // return a reverse-sorted array of links, most recent first
    return array_reverse($this->shortened_links);
  }

  /**
   * Helper callback function for use with unshorten()
   */
  public function unshorten_callback($response, $info, $request) {
    $shortened = $request->callback_payload;
    $shortened->link = $info['url'];
    $this->shortened_links[] = $shortened;
  }

  /**
   * Helper comparison function for use with usort()
   */
  static public function sort_links($a, $b) {
    $a_timestamp = strtotime($a->updated);
    $b_timestamp = strtotime($b->updated);
    if ($a_timestamp === $b_timestamp) {
      return 0;
    }
    return ($a_timestamp > $b_timestamp) ? 1 : -1;
  }

  /**
   * Generic rendering function for Atom feeds
   *
   * Applies a template and returns a string ready for sending to a user.
   * See: https://tools.ietf.org/html/rfc4287
   *
   * @param string $title Human-readable title for the feed.
   * @param string $link "reference from a feed to a Web resource"
   * @param array $entries An array of link objects.
   * @param string $template Path to the Atom template file to use.
   * @return string Complete Atom feed as a string, ready to send.
   */
  protected function render_as_atom($title, $link, $entries, $template='atom_template.xml') {
    ob_start();
    include($template);
    $rendered = ob_get_contents();
    ob_end_clean();
    return "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n" . $rendered;
  }

  /**
   * Utility method to make atom:entry:title more presentable
   *
   * @todo Improve this method for tweets with 2+ links (mb_substr?)
   * @param string $text The raw 'text' element from a tweet object.
   * @param array $indices The url entity indices array of a tweet object.
   * @return string A title string with some extraneous stuff parsed out.
   */
  protected function clean_title($text, $indices) {
    // remove t.co URLs
    $title = preg_replace('/http[s]*:\\/\/t.co\/[a-zA-Z0-9]+[\/]*/', "", $text);
    $title = trim($title);
    // remove RT/MT
    $title = preg_replace('/^[RM]T[:]* /m', "", $title);
    $title = trim($title);
    return $title;
  }

  /**
   * Utility callback method to filter out links to blacklisted domains.
   *
   * @param object $link A link object as generated by get_links_from_api()
   * @return bool Returns false if the link URL is blacklisted.
   */
  public function blacklist_callback($link) {
    return !(in_array(
      parse_url($link->link, PHP_URL_HOST),
      $this->config['domain_blacklist']
    ));
  }
}
