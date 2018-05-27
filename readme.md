# Salvage

Decentralised budgeting.

## Development with Docker

(note to self)

```
$ docker run --rm -it -v /path/to/salvage:/app composer install
$ docker run -d -v /path/to/salvage:/var/www/html -p 80:80 --name salvage php:5.6-apache
```