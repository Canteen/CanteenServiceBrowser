#Canteen Service Browser

Plugin for use by the Canteen Framework for browsing and testing Services. For documentation of the codebase, please see [Canteen Service Browser docs](http://canteen.github.io/CanteenServiceBrowser/).

##Installation

Install is available using [Composer](http://getcomposer.org).

```bash
composer require canteen/service-browser dev-master
```

Including using the Composer autoloader in your index.

```php
require 'vendor/autoload.php';
```

###Rebuild Documentation

This library is auto-documented using [YUIDoc](http://yui.github.io/yuidoc/). To install YUIDoc, run `sudo npm install yuidocjs`. Also, this requires the project [CanteenTheme](http://github.com/Canteen/CanteenTheme) be checked-out along-side this repository. To rebuild the docs, run the ant task from the command-line. 

```bash
ant docs
```

##License##

Copyright (c) 2014 [Matt Karl](http://github.com/bigtimebuddy)

Released under the MIT License.