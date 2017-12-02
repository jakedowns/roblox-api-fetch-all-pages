
## Roblox API Fetch All Pages

An example PHP Script using Guzzle, Promises, & CurlMulti to completely fetch multiple pages of a user's full asset inventory at once.

Includes an example for calculating Total Recent Average Price for all of a given user's Collectible "Limiteds" Assets

- Splits each AssetType request into it's own "thread" so all 10 AssetTypes can be fetched in parallel.
- Automatically keeps fetching against `next_page_cursor` until all pages are retreived.

### Usage

1. `composer install`
1. `http://localhost/?userid=SOME_ROBLOX_USERID`