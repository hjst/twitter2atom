This script extracts links from Twitter and presents them as an Atom feed for
aggregation.

It supports the most obvious bits of the Twitter API: searches, lists, user
timelines, mentions etc.

An RSS/Atom feed of tweets is usually pretty useless because the feed items
point to tweets, which typically aren't things you wish to bother opening in a
browser after you've read them in a feed reader. It is more useful to examine
the tweets, strip out the links, and have the feed items point to the links
themselves rather than the tweets.


For instance
============

This tweet:

> Study: People Far Away From You Not Actually Smaller http://t.co/123456789

Becomes:

    <entry>
      <title>Study: People Far Away From You Not Actually Smaller</title>
      <link>http://www.theonion.com/articles/...</link>
      <content>
        <b>The Onion (@theonion)</b>
        Study: People Far Away From You Not Actually Smaller
        http://www.theonion.com/articles/...
      </content>
    </entry>

Rather than:

    <entry>
      <title>Study: People Far Away From You Not Actually Smaller http://t.co/123456789</title>
      <link>http://twitter.com/theonion/statuses/123456789</link>
      <content>
        Study: People Far Away From You Not Actually Smaller http://t.co/123456789
      </content>
    </entry>

Tweets which don't contain links simply don't appear in the resultant feed.


Installation
============

It is designed to have minimal requirements (PHP >= 5.2 & libcurl) and run on
common shared hosting. It doesn't include any caching so it is the user's
responsibility to configure their feed reader/RSS client responsibly (so that
you don't fall afoul of Twitter's API rate restrictions). It is also assumed that
the user will run it from a protected location (e.g.  http-auth) and thus it does
not perform authentication/authorisation.

You need to create an app with a Twitter Dev account (which is simple & free,
in case you've never done it before) to use this script. One upshot of this is
that you are authenticated as *you* when you use it, so you can do things like
query your own home timeline or fetch tweets from protected accounts that you
are authorised for.

1. If you haven't already done so, register for a [Twitter Dev][] account.
2. Create a [new Twitter app][] and make note of the OAuth tokens & keys.
3. `git clone https://github.com/hjst/twitter2atom.git`
4. `cd twitter2atom && git submodule init && git submodule update`
5. Rename `config.php-dist` to `config.php` and copy/paste the tokens & keys
   from step 2.
6. That's it, you should now be able to query feed.php and get Atom data.


Usage
=====

Once installed on your web server, you can add URLs like these to your feed
reader:

    http://.../feed.php?op=search&q=infosec
    http://.../feed.php?op=list&list_name=china&list_owner=henryto_dd

The full list of options/parameters is as follows:

Search
------

    feed.php?op=search&q=SEARCHTERM

You can copy/paste the `SEARCHTERM` straight from https://twitter.com/search

Lists
-----

    feed.php?op=list&list_name=LIST&list_owner=OWNER

For example the list:

    https://twitter.com/henryto_dd/lists/china

OWNER=henryto_dd and LIST=china

Home Timeline
-------------

    feed.php?op=timeline

This is your "home" timeline when you're logged in to Twitter, i.e. the people
you follow.

User Timeline
-------------

    feed.php?op=timeline&user=USER

For example the profile:

    https://twitter.com/ells

USER=ells

Mentions
--------

    feed.php?op=mentions

The tweets directed at you, i.e. what you see at twitter.com/mentions

Optional parameters
-------------------

Each method will also pass through whatever else you put in the query
parameters. Common examples being "count=50" to get more results, or
"until=2013-08-08" to limit them. Check the [REST API v1.1 docs][] for the full
details.

Removing URL shorteners
-----------------------

By default one layer of t.co URL shortening is removed, and there is an
optional setting to recursively strip *all* URL shortening (to handle cases
where tweets contain URLs wrapped by bit.ly/goo.gl/whatever *before* they
are auto-wrapped with t.co by twitter):

    feed.php?op=WHATEVER&unshorten_links=1

Be aware though that this causes at least one HTTP HEAD request for each link
in the feed, so it significantly increases the amount of time & resources
required to generate the feed.


Acknowledgments
===============

* James Mallison's [twitter-api-php][] wrapper handles Twitter's v1.1 OAuth stuff.
* Josh Fraser's [rolling-curl][] lib is used for parallel HTTP HEAD requests.


Todo
====

* Coming up with good titles for links is difficult when you have to parse them
  somehow out of something as free-form as a tweet (especially when there is
  more than one link in a tweet). My naïve attempt is hardly brilliant so if you
  see any particularly bad titles (or have a suggestion for an improvement) then
  please get in touch (or submit a pull request - look in the `clean_title`
  method in `Twitter2Atom.php`).

* I've only implemented the Twitter API sources that I'm immediately interested
  in. If you would like to use other sources (something with Trending perhaps?)
  then get in touch.

* I had a thought that in some cases it may be useful to de-duplicate the list
  of links prior to rendering them in the Atom feed. I'm not sure though. For
  my own purposes ([Fever][] "spark" feeds) it is better to retain duplicates
  (as they increase the link's prominence). I'd welcome suggestions/use cases
  for this.


[twitter-api-php]: https://github.com/J7mbo/twitter-api-php
[Fever]: http://feedafever.com/
[Twitter Dev]: https://dev.twitter.com/
[new Twitter app]: https://dev.twitter.com/apps/new
[REST API v1.1 docs]: https://dev.twitter.com/docs/api/1.1
[rolling-curl]: https://github.com/joshfraser/rolling-curl
