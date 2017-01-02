# delegator/improvedmerge

This Magento CE 1.x extension provides some opinionated improvements for merged JavaScript and CSS. It:

 - Uses file timestamps as part of the asset hash calculation, which makes cachebusting more reliable
 - Upgrades from `md5` to `sha256` hash function when generating filenames
 - Minifies JavaScript and CSS using the same compressors as [Magento 2][magento-2]
 - Writes a `.gz` version of the asset file to disk, so that Nginx can send precompressed files (requires  [`ngx_http_gzip_static_module`][nginx-gzip-static])
 - Adds the `crossorigin="anonymous"` attribute to `<script>` tags. This allows error reporting when scripts are served from a different hostname, such as a CDN

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

Either the "[Merge JavaScript Files][merge-js]" or
"[Merge CSS Files][merge-css]" setting must be enabled to use this
extension.

If you'd like to take advantage of the precompressed asset files under nginx, you should add the following configuration in the appropriate context (i.e. `http, server, location`).

```conf
gzip_static on;
gzip_vary on;
```

# Profiling

Worried about speed? Specify a non-blank value for the environment variable
`DG_IMPROVEDMERGE_DEBUG`, and the extension will log minification timings in the
default Magento log destination. For example,

```
$ export DG_IMPROVEDMERGE_DEBUG=true
$ <run or restart development server>
$ tail -f var/log/system.log
2017-01-02T17:52:42+00:00 DEBUG (7): Minified CSS in 345ms
2017-01-02T17:52:42+00:00 DEBUG (7): Minified JS in 436ms
```

# License

Please see [LICENSE][license].

[license]: LICENSE
[merge-css]: http://docs.magento.com/m1/ce/user_guide/design/merge-css.html
[merge-js]: http://docs.magento.com/m1/ce/user_guide/design/merge-javascript.html
[magento-2]: https://github.com/magento/magento2
[nginx-gzip-static]: http://nginx.org/en/docs/http/ngx_http_gzip_static_module.html
