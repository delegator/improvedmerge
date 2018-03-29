# delegator/improvedmerge

[![Build Status](https://travis-ci.org/delegator/improvedmerge.svg?branch=travis-ci)](https://travis-ci.org/delegator/improvedmerge)

This Magento CE 1.x extension provides some opinionated improvements for merged JavaScript and CSS.

## :white_check_mark: Features

 - No runtime dependencies
 - Uses file contents to compute the asset hash instead of file names, which makes cachebusting more reliable
 - Uses `sha1` hash function to generate filenames
 - Writes a `.gz` version of the asset file to disk, so that Nginx can send precompressed files (requires  [`ngx_http_gzip_static_module`][nginx-gzip-static])

## :warning: Features that can easily break things if misconfigured

 - Adds the `crossorigin="anonymous"` attribute to all `<script>` tags. This allows error reporting when scripts are served from a different hostname, such as a CDN.

 When serving `.js` assets, You MUST provide a value for the `Access-Control-Allow-Origin` HTTP header, otherwise browsers will reject the download.

## :no_entry: Non-Features

 - Asset minification. PHP is not good at performing this task.

# Installing

This module is installable via composer. Add the Delegator repository to your
`composer.json` file:

```json
{
  "repositories": [
    {
      "type": "composer",
      "url": "https://packages.delegator.com"
    }
  ]
}
```

Then you may:

```bash
$ composer require delegator/improvedmerge
```

Installing this module via Magento connect will not be supported, ever.

# Configuration

In the Magento admin panel, enable one or more of these settings:

 - [Merge JavaScript Files][merge-js]
 - [Merge CSS Files][merge-css]

If you'd like to take advantage of the precompressed asset files under nginx, add the following configuration in the appropriate context (i.e. `http, server, location`).

```conf
gzip_static on;
gzip_vary on;
```

If you don't want to break your site's JavaScript, configure headers as follows:

```conf
location ~* \.(?:css|js)$ {
  add_header 'Access-Control-Allow-Origin' '*';
  add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS';
}
```

# Profiling

Worried about speed? Specify a non-blank value for the environment variable
`DG_IMPROVEDMERGE_DEBUG`, and the extension will log timings in the
default Magento log destination. For example,

```bash
$ export DG_IMPROVEDMERGE_DEBUG=true
$ <run or restart development server>
$ tail -f var/log/system.log
2018-03-29T21:39:07+00:00 DEBUG (7): Wrote compressed file in 5ms
2018-03-29T21:39:07+00:00 DEBUG (7): Concat and hash for 0ad0b7c23680e47320a4937d1145feb765f90aed.js completed in 10ms
```

# License

Please see [LICENSE][license].

[license]: LICENSE
[merge-css]: http://docs.magento.com/m1/ce/user_guide/design/merge-css.html
[merge-js]: http://docs.magento.com/m1/ce/user_guide/design/merge-javascript.html
[magento-2]: https://github.com/magento/magento2
[nginx-gzip-static]: http://nginx.org/en/docs/http/ngx_http_gzip_static_module.html
