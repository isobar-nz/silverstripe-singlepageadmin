# SilverStripe Single Page Administration

Single page administration via a LeftAndMain like interface. Let your clients edit pages without seeing the site tree.

## Features

 * Single page administration
 * Only allows publish functionality for the page
 * You can hide the site tree from everybody (when used in conjunction with silverstripe-catalogmanager)

## Installation

Installation via composer

## How to use

Simply extend the SinglePageAdmin class instead of ModelAdmin and include the class via the `tree_class` static.

```php

class HomePageAdmin extends SinglePageAdmin
{
    private static $menu_title = "Home Page";
    private static $tree_class = 'HomePage';
    private static $url_segment = "home-page";

}
```

The single page admin assumes you have one and only have one item of the class which you are trying to administrate and
makes no attempt to try and check if this is the case, naively getting the first object which matches. It is up to you
to ensure that the class has one and only one instance created, usually via canCreate() functionality.

## License

SilverStripe Single Page Administration is released under the MIT license

## Contributing


### Code guidelines

This project follows the standards defined in:

* [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md)
* [PSR-1](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md)
* [PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)
