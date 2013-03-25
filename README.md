Regreph
=======

Regreph is a performance regression analysis tool for PHP + XHProf.

Screenshot
----------

Showing the build screen from a performance regression in Pheasant:

![Screenshot](https://dl.dropbox.com/u/632579/Screenshots/dqEEc.png "Screenshot")

Running
-------

```bash
php bin/regreph.php <testfile> <projectdir> <refspec>
```

An example test file is https://gist.github.com/lox/33ab5a91edfaf487acf9

The refspec refers to a particular git revision, for instance HEAD~10 or a SHA1 hash.

Installing
----------

```bash
git clone git://github.com/lox/regreph.git
cd regreph
composer install
```

Requires XHProf from PECL. Suggest having a copy of the FB xhprof project running somewhere to
use the web view for comparison:

```
git clone git://github.com/facebook/xhprof.git
phpup xhprof/xhprof_html/index.php
```

