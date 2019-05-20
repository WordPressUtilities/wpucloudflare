# WPU Cloudflare

Handle Cloudflare reverse proxy

## Features

* Purge cloudflare cache for an URL when saving a post from the admin.
* Manually purge cloudflare cache for all pages.

## Translations

* English
* French

## Hooks

* `wpucloudflare__purge_everything` (Action) : Purge all cache.
* `wpucloudflare__save_post__can_clear` (Filter) : Bool (post id) : disable cache purge for a page.
* `wpucloudflare__save_post__urls` (Filter) : Array (urls, post_id) : add more urls to purge.


