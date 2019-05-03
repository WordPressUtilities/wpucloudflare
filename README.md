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

## Todolist

* [ ] Replace curl by WP equivalent.
* [ ] Hook to disable singular url purge for some post types.
* [ ] Hook to purge another URLs when saving a post ( archives, home, etc ).

