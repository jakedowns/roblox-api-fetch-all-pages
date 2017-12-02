
## Roblox API Fetch All Pages

PHP Script using Guzzle, Promises, & CurlMulti to completely fetch paginated results from Roblox Web APIs in a single request

Includes an example for calculating Total Recent Average Price for all of a given user's Collectible "Limiteds" Assets

- Splits each AssetType request into it's own "thread" so all 10 AssetTypes can be fetched in parallel.
- Automatically keeps fetching against `next_page_cursor` until all pages are retreived.

### Usage

1. `composer install`
1. `http://localhost/?userid=SOME_ROBLOX_USERID`

![example output](https://raw.githubusercontent.com/jakedowns/roblox-api-fetch-all-pages/master/screenshots/example.png)

### Index.php

`index.php` includes an `original_example` a `basic_solution` and the final `advanced_solution`

- *original_example* comes from this stackoverflow question: [How do i loop through each page by result of cursor](https://stackoverflow.com/questions/43483509/how-do-i-loop-through-each-page-by-result-of-cursor) which is limited to 1 page of 100 assets per AssetType

- *basic_solution* is a naive while-loop based approach, calls API sequentially while `next_page_cursor` is present for each AssetType

- *advanced_solution* is a slightly optimized version which introduces `->getAsync` and `CurlMulti` into the mix for concurrent web requests

```
// ~4 seconds for 10 pages of 607 items (incomplete)
// original_example();

// ~15 seconds for 31 pages of 2483
//basic_solution();

// ~4 seconds for 31 pages of 2483 items
advanced_solution();
```