Phin, a simple HTTP Server for developing PHP Applications
==========================

## Requirements

Phin has following requirements:

* PHP > 5.3.0, _for stable, long running scripts_
* PHP's fileinfo Extension (also Part of the core, but must be explicitly enabled on Windows)
* If you're on *nix, the PCNTL Extension is a good Idea, as it provides better Performance
* PEAR's Net_Server

## Install

Copy the `phin.phar` file from the bin directory anywhere in your system's `PATH`.

## Usage 

Start to serve the current working directory with

```sh
php phin.phar start
```

or serve a specific directory with

```sh
php phin.phar start -d <the document root to serve>
```

Phin then shows you a nice message that it listens for requests on your `localhost` on
Port `4020`. You can override the Host and Port with the `-H` and `-p` flags. You can
display call Command Line options with `-h` or `--help`.

To serve PHP Apps, you typically want to point the document root (with `-d`) to the
path where the `.htaccess` resides. Phin automatically forwards all requests to
*non-existing* files to an *Index Script*.
By default, Phin looks for a file named `index.php` in your document root, 
but you can specify the path to the Index Script with the `-i` Flag.

## Hacking

You need the following if you want to hack on Phin:

* PEAR
* [Pundle](http://github.com/T-Moe/Pundle)

Run the following command in your checkout of the Phin Source:

```bash
pundle install
```

This will install all required dependencies for building Phin.
