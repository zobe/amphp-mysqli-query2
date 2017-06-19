# amphp-mysqli-query2

`zobe/amphp-mysqli-query2` is a non-blocking mysqli query processor built on the [amp concurrency framework](https://github.com/amphp/amp).


**Requirements**

- PHP 7.0+
- [`amphp/amp`](https://github.com/amphp/amp) ^2
- mysqli
- mysqlnd


**Project Goal**

- Perform parallel processing of QUERIES only.
- Do not crash.
- Catch all errors reasonably and deliver to caller.


**Installation**

```bash
$ composer require zobe/amphp-mysqli-query2
```

**License**

The MIT License (MIT). Please see [LICENSE](./LICENSE) for more information.

