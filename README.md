# delegator/improvedmerge

This extension provides more robust cachebusting by using additional
file metadata when generating merged URLs for JavaScript and CSS.

Either the "[Merge JavaScript Files][merge-js]" or
"[Merge CSS Files][merge-css]" setting must be enabled to take advantage of this
extension.

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

# License

Please see [LICENSE][license].

[license]: LICENSE
[merge-css]: http://docs.magento.com/m1/ce/user_guide/design/merge-css.html
[merge-js]: http://docs.magento.com/m1/ce/user_guide/design/merge-javascript.html
